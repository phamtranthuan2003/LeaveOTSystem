<?php

namespace App\Services;

use App\Mail\ApprovalPendingMail;
use App\Mail\RequestStatusMail;
use App\Models\AppNotification;
use App\Models\Approval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ApprovalService
{
    /**
     * Xây chuỗi duyệt thác nước: đi ngược cây quản lý từ trưởng nhóm,
     * trưởng phòng/khối đến Ban Giám đốc; đơn nghỉ phép có thêm bước HR chốt.
     */
    public function buildChain(User $requester, string $type): array
    {
        $chain = [];
        $seen = [$requester->id => true];
        $current = $requester;
        $guard = 0;
        $reserveHr = $type === 'leave' && ! $requester->isRole('hr');

        while ($current->manager && $guard < 10) {
            $manager = $current->manager;
            if (! $manager) {
                break;
            }

            $isReservedHr = $reserveHr && $manager->isRole('hr');

            if ($manager->status === 'active' && ! isset($seen[$manager->id]) && ! $isReservedHr) {
                $chain[] = $manager;
                $seen[$manager->id] = true;
            }

            if ($manager->isRole('director')) {
                break;
            }

            $current = $manager;
            $guard++;
        }

        if (empty($chain)) {
            $fallback = null;

            if ($reserveHr) {
                $fallback = User::active()
                    ->whereHas('role', fn ($q) => $q->where('slug', 'hr'))
                    ->where('id', '!=', $requester->id)
                    ->first();
            }

            $fallback ??= User::active()
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['director', 'admin']))
                ->where('id', '!=', $requester->id)
                ->first();

            if ($fallback) {
                $chain[] = $fallback;
                $seen[$fallback->id] = true;
            }
        }

        $hasHr = collect($chain)->contains(fn (User $approver) => $approver->isRole('hr'));

        if ($reserveHr && ! $hasHr) {
            $hr = User::active()
                ->whereHas('role', fn ($q) => $q->where('slug', 'hr'))
                ->whereNotIn('id', array_keys($seen))
                ->first();

            if ($hr) {
                $chain[] = $hr;
            }
        }

        return $chain;
    }

    public function submit(Model $requestable, string $type): int
    {
        $requester = $requestable->user;
        $chain = $this->buildChain($requester, $type);

        if (empty($chain)) {
            throw new \RuntimeException('Không tìm thấy người duyệt trong cấu trúc tổ chức. Vui lòng liên hệ HR.');
        }

        $first = $chain[0];

        DB::transaction(function () use ($requestable, $type, $chain, $requester, $first) {
            $requestable->update([
                'status' => 'pending',
                'current_step' => 1,
                'total_steps' => count($chain),
            ]);

            foreach ($chain as $index => $approver) {
                Approval::create([
                    'approvable_type' => $requestable->getMorphClass(),
                    'approvable_id' => $requestable->getKey(),
                    'step' => $index + 1,
                    'approver_id' => $approver->id,
                    'approver_role' => $approver->role?->slug,
                    'status' => 'pending',
                ]);
            }

            $this->deductLeaveBalance($requestable, true);

            $label = $type === 'leave' ? 'đơn nghỉ phép' : 'đơn đăng ký OT';

            foreach ($this->approvalRecipients($first) as $recipient) {
                $this->notify(
                    $recipient,
                    'Cần bạn duyệt '.$label,
                    "{$requester->name} vừa gửi {$label} {$requestable->code}. Vui lòng xem xét và phê duyệt.",
                    $this->requestLink($requestable),
                    'approval'
                );
            }

            $this->notify(
                $requester,
                'Đã gửi '.$label.' thành công',
                "{$requestable->code} đang chờ {$this->approverLabel($first)} duyệt (bước 1/".count($chain).').',
                $this->requestLink($requestable),
                'info'
            );

            AuditLogger::log('submit', $type, "Gửi {$label} {$requestable->code}", $requestable);
        });

        $this->sendApprovalPendingEmails($requestable, $first);
        $this->sendStatusEmail($requestable, $requester, 'submitted', null, $first);

        return count($chain);
    }

    public function approve(Approval $approval, User $actor, ?string $note = null): array
    {
        $mailContext = null;

        $result = DB::transaction(function () use ($approval, $actor, $note, &$mailContext) {
            $approval = Approval::whereKey($approval->id)->lockForUpdate()->first();

            if (! $approval || $approval->status !== 'pending') {
                return ['ok' => false, 'message' => 'Bước duyệt này đã được xử lý.'];
            }

            $isAdminOverride = $actor->isRole('admin') && $approval->approver_id !== $actor->id;
            $isHrPeer = $approval->approver_role === 'hr' && $actor->isRole('hr');

            if ($approval->approver_id !== $actor->id && ! $isAdminOverride && ! $isHrPeer) {
                return ['ok' => false, 'message' => 'Bạn không phải người duyệt của bước này.'];
            }

            $requestable = $approval->approvable;

            if (! $requestable || $requestable->status !== 'pending') {
                return ['ok' => false, 'message' => 'Đơn không còn ở trạng thái chờ duyệt.'];
            }

            $current = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();

            if (! $current || $current->id !== $approval->id) {
                return ['ok' => false, 'message' => 'Chưa đến lượt duyệt của bước này trong chuỗi phê duyệt.'];
            }

            $approval->update([
                'status' => 'approved',
                'note' => $note,
                'acted_at' => now(),
                'acted_by' => $actor->id,
            ]);

            $next = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();
            $requester = $requestable->user;
            $label = $requestable instanceof LeaveRequest ? 'đơn nghỉ phép' : 'đơn OT';

            if ($next) {
                $requestable->update(['current_step' => $next->step]);

                foreach ($this->approvalRecipients($next->approver) as $recipient) {
                    $this->notify(
                        $recipient,
                        'Cần bạn duyệt tiếp '.$label,
                        "{$requestable->code} của {$requester->name} đã được {$actor->name} duyệt và chuyển đến bạn.",
                        $this->requestLink($requestable),
                        'approval'
                    );
                }

                $this->notify(
                    $requester,
                    'Đơn của bạn đã được duyệt một phần',
                    "{$requestable->code} đã qua bước {$approval->step}/{$requestable->total_steps}. Đang chờ {$this->approverLabel($next->approver)} duyệt.",
                    $this->requestLink($requestable),
                    'info'
                );

                AuditLogger::log(
                    'approve_step',
                    $requestable->getMorphClass(),
                    ($isAdminOverride ? 'Quản trị viên duyệt thay bước ' : 'Duyệt bước ')."{$approval->step} cho {$requestable->code}",
                    $requestable
                );

                $mailContext = [
                    'event' => 'progressed',
                    'requestable' => $requestable,
                    'requester' => $requester,
                    'approvedBy' => $actor,
                    'pendingWith' => $next->approver,
                ];

                return [
                    'ok' => true,
                    'message' => "Đã duyệt. Đơn chuyển đến {$this->approverLabel($next->approver)} (bước {$next->step}/{$requestable->total_steps}).",
                ];
            }

            $requestable->update([
                'status' => 'approved',
                'decided_at' => now(),
                'current_step' => $requestable->total_steps ?: 1,
            ]);

            // Đơn mới đã trừ quỹ khi gửi; chỉ trừ bù cho đơn cũ tạo trước thay đổi.
            $this->deductLeaveBalance($requestable);

            $this->notify(
                $requester,
                'Đơn đã được duyệt hoàn tất',
                "{$requestable->code} đã được phê duyệt ở bước cuối bởi {$actor->name}.",
                $this->requestLink($requestable),
                'success'
            );

            AuditLogger::log(
                'approve_final',
                $requestable->getMorphClass(),
                ($isAdminOverride ? 'Quản trị viên duyệt thay hoàn tất ' : 'Hoàn tất duyệt ').$requestable->code,
                $requestable
            );

            $mailContext = [
                'event' => 'completed',
                'requestable' => $requestable,
                'requester' => $requester,
                'approvedBy' => $actor,
                'pendingWith' => null,
            ];

            return ['ok' => true, 'message' => 'Đã duyệt hoàn tất đơn.'];
        });

        if ($result['ok'] && $mailContext) {
            if ($mailContext['event'] === 'progressed') {
                $this->sendApprovalPendingEmails(
                    $mailContext['requestable'],
                    $mailContext['pendingWith'],
                    $mailContext['approvedBy']
                );
            }

            $this->sendStatusEmail(
                $mailContext['requestable'],
                $mailContext['requester'],
                $mailContext['event'],
                $mailContext['approvedBy'],
                $mailContext['pendingWith']
            );
        }

        return $result;
    }

    public function reject(Approval $approval, User $actor, string $note): array
    {
        $mailContext = null;

        $result = DB::transaction(function () use ($approval, $actor, $note, &$mailContext) {
            $approval = Approval::whereKey($approval->id)->lockForUpdate()->first();

            if (! $approval || $approval->status !== 'pending') {
                return ['ok' => false, 'message' => 'Bước duyệt này đã được xử lý.'];
            }

            $isAdminOverride = $actor->isRole('admin') && $approval->approver_id !== $actor->id;
            $isHrPeer = $approval->approver_role === 'hr' && $actor->isRole('hr');

            if ($approval->approver_id !== $actor->id && ! $isAdminOverride && ! $isHrPeer) {
                return ['ok' => false, 'message' => 'Bạn không phải người duyệt của bước này.'];
            }

            $requestable = $approval->approvable;

            if (! $requestable || $requestable->status !== 'pending') {
                return ['ok' => false, 'message' => 'Đơn không còn ở trạng thái chờ duyệt.'];
            }

            $current = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();

            if (! $current || $current->id !== $approval->id) {
                return ['ok' => false, 'message' => 'Chưa đến lượt duyệt của bước này trong chuỗi phê duyệt.'];
            }

            $approval->update([
                'status' => 'rejected',
                'note' => $note,
                'acted_at' => now(),
                'acted_by' => $actor->id,
            ]);

            $requestable->approvals()->where('status', 'pending')->update(['status' => 'skipped']);
            $requestable->update(['status' => 'rejected', 'decided_at' => now()]);

            $this->refundLeaveBalance($requestable);

            $this->notify(
                $requestable->user,
                'Đơn bị từ chối',
                "{$requestable->code} đã bị {$actor->name} từ chối. Lý do: {$note}",
                $this->requestLink($requestable),
                'danger'
            );

            AuditLogger::log(
                'reject',
                $requestable->getMorphClass(),
                ($isAdminOverride ? 'Quản trị viên từ chối thay ' : 'Từ chối ')."{$requestable->code}: {$note}",
                $requestable
            );

            $mailContext = [
                'requestable' => $requestable,
                'requester' => $requestable->user,
                'approvedBy' => $actor,
                'reason' => $note,
            ];

            return ['ok' => true, 'message' => 'Đã từ chối đơn và thông báo đến nhân viên.'];
        });

        if ($result['ok'] && $mailContext) {
            $this->sendStatusEmail(
                $mailContext['requestable'],
                $mailContext['requester'],
                'rejected',
                $mailContext['approvedBy'],
                null,
                $mailContext['reason']
            );
        }

        return $result;
    }

    public function canApproveAll(Model $requestable, User $actor): bool
    {
        if ($requestable->status !== 'pending') {
            return false;
        }

        if ($actor->isRole('admin')) {
            return true;
        }

        $permission = $requestable instanceof LeaveRequest ? 'leave.approve' : 'ot.approve';

        if (! $actor->hasPermission($permission)) {
            return false;
        }

        $current = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();

        return $current !== null
            && (int) $current->approver_id === (int) $actor->id
            && $current->isFinalManagementStep();
    }

    /**
     * Quản lý cấp cao nhất duyệt toàn bộ các cấp quản lý nhưng giữ bước HR chốt;
     * quản trị viên duyệt toàn bộ chuỗi kể cả HR.
     */
    public function approveAll(Model $requestable, User $actor, ?string $note = null): array
    {
        $isAdmin = $actor->isRole('admin');

        if (! $this->canApproveAll($requestable, $actor)) {
            return ['ok' => false, 'message' => 'Chỉ quản lý cấp cao nhất hoặc quản trị viên mới có quyền duyệt toàn bộ đơn.'];
        }

        $mailContext = null;

        $result = DB::transaction(function () use ($requestable, $actor, $note, $isAdmin, &$mailContext) {
            if ($requestable->status !== 'pending') {
                return ['ok' => false, 'message' => 'Đơn không còn ở trạng thái chờ duyệt.'];
            }

            $pending = $requestable->approvals()
                ->with('approver.role')
                ->where('status', 'pending')
                ->orderBy('step')
                ->get();

            if ($pending->isEmpty()) {
                return ['ok' => false, 'message' => 'Không còn bước duyệt nào cần xử lý.'];
            }

            $label = $requestable instanceof LeaveRequest ? 'đơn nghỉ phép' : 'đơn OT';
            $requester = $requestable->user;
            $now = now();
            $skipNote = $note ?: ($isAdmin
                ? 'Quản trị viên duyệt toàn bộ chuỗi.'
                : 'Quản lý cấp cao nhất duyệt toàn bộ các cấp.');

            $markApproved = function (Approval $step) use ($actor, $now, $skipNote) {
                $step->update([
                    'status' => 'approved',
                    'note' => $skipNote,
                    'acted_at' => $now,
                    'acted_by' => $actor->id,
                ]);
            };

            if ($isAdmin) {
                foreach ($pending as $step) {
                    $markApproved($step);
                }

                $requestable->update([
                    'status' => 'approved',
                    'decided_at' => $now,
                    'current_step' => $requestable->total_steps ?: 1,
                ]);

                // Đơn mới đã trừ quỹ khi gửi; chỉ trừ bù cho đơn cũ tạo trước thay đổi.
                $this->deductLeaveBalance($requestable);

                $this->notify(
                    $requester,
                    'Đơn đã được duyệt hoàn tất',
                    "{$requestable->code} ({$label}) đã được quản trị viên {$actor->name} duyệt toàn bộ chuỗi và hoàn tất.",
                    $this->requestLink($requestable),
                    'success'
                );

                AuditLogger::log(
                    'approve_all',
                    $requestable->getMorphClass(),
                    "Quản trị viên duyệt toàn bộ {$requestable->code} ({$pending->count()} bước)",
                    $requestable
                );

                $mailContext = [
                    'event' => 'completed',
                    'requester' => $requester,
                    'approvedBy' => $actor,
                    'byAdmin' => true,
                ];

                return ['ok' => true, 'message' => "Đã duyệt toàn bộ {$pending->count()} bước còn lại. Đơn đã hoàn tất."];
            }

            $hrSteps = $pending->filter(fn (Approval $step) => $step->approver?->isRole('hr'));
            $managementSteps = $pending->reject(fn (Approval $step) => $step->approver?->isRole('hr'));

            foreach ($managementSteps as $step) {
                $markApproved($step);
            }

            if ($hrSteps->isEmpty()) {
                $requestable->update([
                    'status' => 'approved',
                    'decided_at' => $now,
                    'current_step' => $requestable->total_steps ?: 1,
                ]);

                $this->deductLeaveBalance($requestable);

                $this->notify(
                    $requester,
                    'Đơn đã được duyệt hoàn tất',
                    "{$requestable->code} đã được {$actor->name} duyệt toàn bộ các cấp và hoàn tất.",
                    $this->requestLink($requestable),
                    'success'
                );

                AuditLogger::log(
                    'approve_all',
                    $requestable->getMorphClass(),
                    "Quản lý cấp cao nhất duyệt toàn bộ {$requestable->code} ({$managementSteps->count()} bước)",
                    $requestable
                );

                $mailContext = [
                    'event' => 'completed',
                    'requester' => $requester,
                    'approvedBy' => $actor,
                    'byAdmin' => false,
                ];

                return ['ok' => true, 'message' => 'Đã duyệt toàn bộ các cấp và hoàn tất đơn.'];
            }

            $hrStep = $hrSteps->first();
            $hr = $hrStep->approver;

            $requestable->update(['current_step' => $hrStep->step]);

            $this->notify(
                $requester,
                'Đơn đã được duyệt toàn bộ các cấp',
                "{$requestable->code} đã được {$actor->name} duyệt toàn bộ các cấp. Đang chờ bộ phận HR duyệt chốt.",
                $this->requestLink($requestable),
                'info'
            );

            foreach ($this->approvalRecipients($hr) as $recipient) {
                $this->notify(
                    $recipient,
                    'Cần bạn duyệt chốt '.$label,
                    "{$requestable->code} của {$requester->name} đã được {$actor->name} duyệt toàn bộ các cấp. Vui lòng duyệt chốt.",
                    $this->requestLink($requestable),
                    'approval'
                );
            }

            AuditLogger::log(
                'approve_all',
                $requestable->getMorphClass(),
                "Quản lý cấp cao nhất duyệt toàn bộ các cấp {$requestable->code}, chờ HR chốt",
                $requestable
            );

            $mailContext = [
                'event' => 'to_hr',
                'requester' => $requester,
                'approvedBy' => $actor,
                'pendingWith' => $hr,
            ];

            return ['ok' => true, 'message' => 'Đã duyệt toàn bộ các cấp. Đơn đang chờ bộ phận HR duyệt chốt.'];
        });

        if ($result['ok'] && $mailContext) {
            if ($mailContext['event'] === 'to_hr') {
                $this->sendApprovalPendingEmails(
                    $requestable,
                    $mailContext['pendingWith'],
                    $mailContext['approvedBy'],
                    true
                );

                $this->sendStatusEmail(
                    $requestable,
                    $mailContext['requester'],
                    'progressed',
                    $mailContext['approvedBy'],
                    $mailContext['pendingWith'],
                    null,
                    true
                );
            } else {
                $this->sendStatusEmail(
                    $requestable,
                    $mailContext['requester'],
                    'completed',
                    $mailContext['approvedBy'],
                    null,
                    null,
                    false,
                    $mailContext['byAdmin']
                );
            }
        }

        return $result;
    }

    public function cancel(Model $requestable, User $actor): array
    {
        if ($requestable->user_id !== $actor->id) {
            return ['ok' => false, 'message' => 'Bạn chỉ có thể huỷ đơn của mình.'];
        }

        if (! in_array($requestable->status, ['pending', 'approved'], true)) {
            return ['ok' => false, 'message' => 'Đơn này không thể huỷ ở trạng thái hiện tại.'];
        }

        $startDate = $requestable instanceof LeaveRequest
            ? $requestable->start_date
            : Carbon::parse($requestable->ot_date);

        if ($requestable->status === 'approved' && $startDate->lt(today())) {
            return ['ok' => false, 'message' => 'Không thể huỷ đơn đã bắt đầu hoặc đã diễn ra.'];
        }

        DB::transaction(function () use ($requestable) {
            $current = $requestable->approvals()->where('status', 'pending')->first();

            $this->refundLeaveBalance($requestable);

            $requestable->approvals()->where('status', 'pending')->update(['status' => 'skipped']);
            $requestable->update(['status' => 'cancelled', 'decided_at' => now()]);

            if ($current && $current->approver) {
                $this->notify(
                    $current->approver,
                    'Đơn đã được nhân viên huỷ',
                    "{$requestable->code} của {$requestable->user->name} đã được huỷ, không cần duyệt nữa.",
                    $this->requestLink($requestable),
                    'info'
                );
            }

            AuditLogger::log('cancel', $requestable->getMorphClass(), "Huỷ {$requestable->code}", $requestable);
        });

        return ['ok' => true, 'message' => 'Đã huỷ đơn thành công.'];
    }

    /**
     * Xoá vĩnh viễn đơn nghỉ kèm hoàn quỹ đã trừ. Chỉ dùng cho tài khoản hệ thống đặc biệt.
     */
    public function deleteLeaveRequest(LeaveRequest $leave): void
    {
        DB::transaction(function () use ($leave) {
            $this->refundLeaveBalance($leave);

            $leave->approvals()->delete();
            $leave->delete();
        });
    }

    /**
     * Trừ quỹ khi gửi đơn; bỏ qua nếu đơn đã trừ hoặc loại phép không theo dõi quỹ.
     */
    protected function deductLeaveBalance(Model $requestable, bool $enforceAvailable = false): void
    {
        if (! $requestable instanceof LeaveRequest || $requestable->balance_deducted) {
            return;
        }

        $leaveType = $requestable->leaveType;

        if (! $leaveType || ! $leaveType->deduct_balance) {
            return;
        }

        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => $requestable->user_id,
                'year' => LeaveBalance::yearFor($leaveType, $requestable->start_date),
                'leave_type_id' => $requestable->leave_type_id,
            ],
            [
                'entitled_days' => $leaveType->isAnnual() ? 0 : $leaveType->default_days,
                'used_days' => 0,
                'carried_over' => 0,
            ]
        );

        $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->first();

        if ($enforceAvailable && $requestable->total_days > $balance->remaining()) {
            throw new \RuntimeException("Quỹ phép không đủ: còn {$balance->remaining()} ngày, đơn cần {$requestable->total_days} ngày.");
        }

        $balance->used_days = $balance->used_days + $requestable->total_days;
        $balance->save();

        $requestable->update(['balance_deducted' => true]);
    }

    protected function refundLeaveBalance(Model $requestable): void
    {
        if (! $requestable instanceof LeaveRequest || ! $requestable->balance_deducted) {
            return;
        }

        $leaveType = $requestable->leaveType;
        $year = $leaveType
            ? LeaveBalance::yearFor($leaveType, $requestable->start_date)
            : (int) $requestable->start_date->year;

        $balance = LeaveBalance::where([
            'user_id' => $requestable->user_id,
            'year' => $year,
            'leave_type_id' => $requestable->leave_type_id,
        ])->lockForUpdate()->first();

        if ($balance) {
            $balance->used_days = max(0, $balance->used_days - $requestable->total_days);
            $balance->save();
        }

        $requestable->update(['balance_deducted' => false]);
    }

    /**
     * Gửi mail cho người duyệt kế tiếp. Lỗi mail không được làm hỏng luồng duyệt.
     */
    /**
     * Bước HR là bước chung: mọi tài khoản HR đang làm việc đều nhận thông báo và có thể duyệt.
     *
     * @return Collection<int, User>
     */
    protected function approvalRecipients(User $approver)
    {
        if ($approver->isRole('hr')) {
            return User::active()
                ->whereHas('role', fn ($q) => $q->where('slug', 'hr'))
                ->orderBy('id')
                ->get();
        }

        return collect([$approver]);
    }

    protected function approverLabel(User $approver): string
    {
        return $approver->isRole('hr') ? 'bộ phận HR' : $approver->name;
    }

    protected function sendApprovalPendingEmails(
        Model $requestable,
        ?User $approver,
        ?User $approvedBy = null,
        bool $approvedAllLevels = false
    ): void {
        if (! $approver) {
            return;
        }

        foreach ($this->approvalRecipients($approver) as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            try {
                Mail::to($recipient->email)->send(
                    new ApprovalPendingMail($requestable, $recipient, $approvedBy, $approvedAllLevels)
                );
            } catch (\Throwable $e) {
                Log::warning('Không gửi được email báo duyệt đơn: '.$e->getMessage());
            }
        }
    }

    /**
     * Gửi mail cập nhật trạng thái đơn cho người gửi.
     */
    protected function sendStatusEmail(
        Model $requestable,
        ?User $requester,
        string $event,
        ?User $approvedBy = null,
        ?User $pendingWith = null,
        ?string $reason = null,
        bool $approvedAllLevels = false,
        bool $byAdmin = false
    ): void {
        if (! $requester || ! $requester->email) {
            return;
        }

        try {
            Mail::to($requester->email)->send(new RequestStatusMail(
                $requestable,
                $requester,
                $event,
                $approvedBy,
                $pendingWith,
                $reason,
                $approvedAllLevels,
                $byAdmin
            ));
        } catch (\Throwable $e) {
            Log::warning('Không gửi được email cập nhật đơn: '.$e->getMessage());
        }
    }

    protected function requestLink(Model $requestable): string
    {
        return $requestable instanceof LeaveRequest
            ? route('leave.show', $requestable)
            : route('overtime.show', $requestable);
    }

    protected function notify(User $user, string $title, string $body, ?string $link = null, string $type = 'info'): void
    {
        if (! $user) {
            return;
        }

        AppNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'type' => $type,
        ]);
    }
}
