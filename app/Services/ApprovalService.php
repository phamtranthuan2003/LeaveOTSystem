<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Approval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        while ($current->manager && $guard < 10) {
            $manager = $current->manager;
            if (!$manager) {
                break;
            }

            if ($manager->status === 'active' && !isset($seen[$manager->id])) {
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
            $fallback = User::active()
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['director', 'admin']))
                ->where('id', '!=', $requester->id)
                ->first();

            if ($fallback) {
                $chain[] = $fallback;
                $seen[$fallback->id] = true;
            }
        }

        if ($type === 'leave' && !$requester->isRole('hr')) {
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

        DB::transaction(function () use ($requestable, $type, $chain, $requester) {
            $requestable->update([
                'status'       => 'pending',
                'current_step' => 1,
                'total_steps'  => count($chain),
            ]);

            foreach ($chain as $index => $approver) {
                Approval::create([
                    'approvable_type' => $requestable->getMorphClass(),
                    'approvable_id'   => $requestable->getKey(),
                    'step'            => $index + 1,
                    'approver_id'     => $approver->id,
                    'approver_role'   => $approver->role?->slug,
                    'status'          => 'pending',
                ]);
            }

            $label = $type === 'leave' ? 'đơn nghỉ phép' : 'đơn đăng ký OT';
            $first = $chain[0];

            $this->notify(
                $first,
                'Cần bạn duyệt ' . $label,
                "{$requester->name} vừa gửi {$label} {$requestable->code}. Vui lòng xem xét và phê duyệt.",
                $this->requestLink($requestable),
                'approval'
            );

            $this->notify(
                $requester,
                'Đã gửi ' . $label . ' thành công',
                "{$requestable->code} đang chờ {$first->name} duyệt (bước 1/" . count($chain) . ').',
                $this->requestLink($requestable),
                'info'
            );

            AuditLogger::log('submit', $type, "Gửi {$label} {$requestable->code}", $requestable);
        });

        return count($chain);
    }

    public function approve(Approval $approval, User $actor, ?string $note = null): array
    {
        return DB::transaction(function () use ($approval, $actor, $note) {
            $approval = Approval::whereKey($approval->id)->lockForUpdate()->first();

            if (!$approval || $approval->status !== 'pending') {
                return ['ok' => false, 'message' => 'Bước duyệt này đã được xử lý.'];
            }

            $isAdminOverride = $actor->isRole('admin') && $approval->approver_id !== $actor->id;

            if ($approval->approver_id !== $actor->id && !$isAdminOverride) {
                return ['ok' => false, 'message' => 'Bạn không phải người duyệt của bước này.'];
            }

            $requestable = $approval->approvable;

            if (!$requestable || $requestable->status !== 'pending') {
                return ['ok' => false, 'message' => 'Đơn không còn ở trạng thái chờ duyệt.'];
            }

            $current = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();

            if (!$current || $current->id !== $approval->id) {
                return ['ok' => false, 'message' => 'Chưa đến lượt duyệt của bước này trong chuỗi phê duyệt.'];
            }

            $approval->update([
                'status'   => 'approved',
                'note'     => $note,
                'acted_at' => now(),
                'acted_by' => $actor->id,
            ]);

            $next = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();
            $requester = $requestable->user;
            $label = $requestable instanceof LeaveRequest ? 'đơn nghỉ phép' : 'đơn OT';

            if ($next) {
                $requestable->update(['current_step' => $next->step]);

                $this->notify(
                    $next->approver,
                    'Cần bạn duyệt tiếp ' . $label,
                    "{$requestable->code} của {$requester->name} đã được {$actor->name} duyệt và chuyển đến bạn.",
                    $this->requestLink($requestable),
                    'approval'
                );

                $this->notify(
                    $requester,
                    'Đơn của bạn đã được duyệt một phần',
                    "{$requestable->code} đã qua bước {$approval->step}/{$requestable->total_steps}. Đang chờ {$next->approver->name} duyệt.",
                    $this->requestLink($requestable),
                    'info'
                );

                AuditLogger::log(
                    'approve_step',
                    $requestable->getMorphClass(),
                    ($isAdminOverride ? 'Quản trị viên duyệt thay bước ' : 'Duyệt bước ') . "{$approval->step} cho {$requestable->code}",
                    $requestable
                );

                return [
                    'ok'      => true,
                    'message' => "Đã duyệt. Đơn chuyển đến {$next->approver->name} (bước {$next->step}/{$requestable->total_steps}).",
                ];
            }

            $requestable->update([
                'status'       => 'approved',
                'decided_at'   => now(),
                'current_step' => $requestable->total_steps ?: 1,
            ]);

            $this->finalizeLeaveBalance($requestable);

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
                ($isAdminOverride ? 'Quản trị viên duyệt thay hoàn tất ' : 'Hoàn tất duyệt ') . $requestable->code,
                $requestable
            );

            return ['ok' => true, 'message' => 'Đã duyệt hoàn tất đơn.'];
        });
    }

    public function reject(Approval $approval, User $actor, string $note): array
    {
        return DB::transaction(function () use ($approval, $actor, $note) {
            $approval = Approval::whereKey($approval->id)->lockForUpdate()->first();

            if (!$approval || $approval->status !== 'pending') {
                return ['ok' => false, 'message' => 'Bước duyệt này đã được xử lý.'];
            }

            $isAdminOverride = $actor->isRole('admin') && $approval->approver_id !== $actor->id;

            if ($approval->approver_id !== $actor->id && !$isAdminOverride) {
                return ['ok' => false, 'message' => 'Bạn không phải người duyệt của bước này.'];
            }

            $requestable = $approval->approvable;

            if (!$requestable || $requestable->status !== 'pending') {
                return ['ok' => false, 'message' => 'Đơn không còn ở trạng thái chờ duyệt.'];
            }

            $current = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();

            if (!$current || $current->id !== $approval->id) {
                return ['ok' => false, 'message' => 'Chưa đến lượt duyệt của bước này trong chuỗi phê duyệt.'];
            }

            $approval->update([
                'status'   => 'rejected',
                'note'     => $note,
                'acted_at' => now(),
                'acted_by' => $actor->id,
            ]);

            $requestable->approvals()->where('status', 'pending')->update(['status' => 'skipped']);
            $requestable->update(['status' => 'rejected', 'decided_at' => now()]);

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
                ($isAdminOverride ? 'Quản trị viên từ chối thay ' : 'Từ chối ') . "{$requestable->code}: {$note}",
                $requestable
            );

            return ['ok' => true, 'message' => 'Đã từ chối đơn và thông báo đến nhân viên.'];
        });
    }

    /**
     * Quản trị viên duyệt toàn bộ các bước còn lại của một đơn trong một lần.
     */
    public function approveAll(Model $requestable, User $actor, ?string $note = null): array
    {
        if (!$actor->isRole('admin')) {
            return ['ok' => false, 'message' => 'Chỉ quản trị viên mới có quyền duyệt toàn bộ đơn.'];
        }

        return DB::transaction(function () use ($requestable, $actor, $note) {
            if ($requestable->status !== 'pending') {
                return ['ok' => false, 'message' => 'Đơn không còn ở trạng thái chờ duyệt.'];
            }

            $pending = $requestable->approvals()->where('status', 'pending')->orderBy('step')->get();

            if ($pending->isEmpty()) {
                return ['ok' => false, 'message' => 'Không còn bước duyệt nào cần xử lý.'];
            }

            foreach ($pending as $step) {
                $step->update([
                    'status'   => 'approved',
                    'note'     => $note ?: 'Quản trị viên duyệt toàn bộ chuỗi.',
                    'acted_at' => now(),
                    'acted_by' => $actor->id,
                ]);
            }

            $requestable->update([
                'status'       => 'approved',
                'decided_at'   => now(),
                'current_step' => $requestable->total_steps ?: 1,
            ]);

            $this->finalizeLeaveBalance($requestable);

            $label = $requestable instanceof LeaveRequest ? 'đơn nghỉ phép' : 'đơn OT';

            $this->notify(
                $requestable->user,
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

            return ['ok' => true, 'message' => "Đã duyệt toàn bộ {$pending->count()} bước còn lại. Đơn đã hoàn tất."];
        });
    }

    public function cancel(Model $requestable, User $actor): array
    {
        if ($requestable->user_id !== $actor->id) {
            return ['ok' => false, 'message' => 'Bạn chỉ có thể huỷ đơn của mình.'];
        }

        if (!in_array($requestable->status, ['pending', 'approved'], true)) {
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

            if ($requestable->status === 'approved') {
                $this->refundLeaveBalance($requestable);
            }

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

    protected function finalizeLeaveBalance(Model $requestable): void
    {
        if (!$requestable instanceof LeaveRequest) {
            return;
        }

        $leaveType = $requestable->leaveType;

        if (!$leaveType || !$leaveType->deduct_balance) {
            return;
        }

        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id'       => $requestable->user_id,
                'year'          => $requestable->start_date->year,
                'leave_type_id' => $requestable->leave_type_id,
            ],
            [
                'entitled_days' => $leaveType->default_days,
                'used_days'     => 0,
                'carried_over'  => 0,
            ]
        );

        $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->first();
        $balance->used_days = $balance->used_days + $requestable->total_days;
        $balance->save();

        $requestable->update(['balance_deducted' => true]);
    }

    protected function refundLeaveBalance(Model $requestable): void
    {
        if (!$requestable instanceof LeaveRequest || !$requestable->balance_deducted) {
            return;
        }

        $balance = LeaveBalance::where([
            'user_id'       => $requestable->user_id,
            'year'          => $requestable->start_date->year,
            'leave_type_id' => $requestable->leave_type_id,
        ])->lockForUpdate()->first();

        if ($balance) {
            $balance->used_days = max(0, $balance->used_days - $requestable->total_days);
            $balance->save();
        }

        $requestable->update(['balance_deducted' => false]);
    }

    protected function requestLink(Model $requestable): string
    {
        return $requestable instanceof LeaveRequest
            ? route('leave.show', $requestable)
            : route('overtime.show', $requestable);
    }

    protected function notify(User $user, string $title, string $body, ?string $link = null, string $type = 'info'): void
    {
        if (!$user) {
            return;
        }

        AppNotification::create([
            'user_id' => $user->id,
            'title'   => $title,
            'body'    => $body,
            'link'    => $link,
            'type'    => $type,
        ]);
    }
}
