<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

class DemoRequestSeeder extends Seeder
{
    public function run(): void
    {
        // Dữ liệu demo không được gửi mail thật đến hộp thư nhân sự.
        Mail::fake();

        $service = app(ApprovalService::class);
        $calculator = app(LeaveCalculator::class);

        $user = fn (string $email) => User::where('email', $email)->firstOrFail();
        $leaveType = fn (string $code) => LeaveType::where('code', $code)->firstOrFail();

        $nextMonday = now()->addWeek()->startOfWeek(Carbon::MONDAY);
        $nextMonday2 = now()->addWeeks(2)->startOfWeek(Carbon::MONDAY);
        $nextMonday3 = now()->addWeeks(3)->startOfWeek(Carbon::MONDAY);
        $lastMonday = now()->subWeeks(2)->startOfWeek(Carbon::MONDAY);

        $createLeave = function (User $requester, LeaveType $type, Carbon $start, Carbon $end, string $reason, string $dayType = 'full') use ($calculator) {
            return LeaveRequest::create([
                'code' => LeaveRequest::nextCode(),
                'user_id' => $requester->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'day_type' => $dayType,
                'total_days' => $calculator->workingDays($start->copy(), $end->copy(), $dayType),
                'reason' => $reason,
                'status' => 'pending',
                'current_step' => 1,
                'total_steps' => 0,
            ]);
        };

        $approveSteps = function ($requestable, int $steps, ?string $note = null) use ($service) {
            for ($i = 0; $i < $steps; $i++) {
                $requestable->refresh();
                if ($requestable->status !== 'pending') {
                    break;
                }
                $current = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();
                if (! $current) {
                    break;
                }
                $service->approve($current, $current->approver, $note);
            }
        };

        $approveAll = function ($requestable, ?string $note = 'Đồng ý.') use ($service) {
            $guard = 0;
            while ($guard++ < 10) {
                $requestable->refresh();
                if ($requestable->status !== 'pending') {
                    break;
                }
                $current = $requestable->approvals()->where('status', 'pending')->orderBy('step')->first();
                if (! $current) {
                    break;
                }
                $service->approve($current, $current->approver, $note);
            }
        };

        // 1. Đơn nghỉ đang chờ trưởng nhóm
        $leave1 = $createLeave($user('employee@everland.vn'), $leaveType('AL'), $nextMonday->copy(), $nextMonday->copy()->addDay(), 'Về quê giải quyết việc gia đình, đã bàn giao khách hàng cho đồng nghiệp.');
        $service->submit($leave1, 'leave');

        // 2. Đơn nghỉ đang chờ trưởng phòng (trưởng nhóm đã duyệt)
        $leave2 = $createLeave($user('huong.ly@everland.vn'), $leaveType('AL'), $nextMonday2->copy(), $nextMonday2->copy(), 'Nghỉ phép năm đi khám sức khỏe định kỳ.');
        $service->submit($leave2, 'leave');
        $approveSteps($leave2, 1, 'Đồng ý, nhớ bàn giao công việc.');

        // 3. Đơn nghỉ đang chờ Ban Giám đốc
        $leave3 = $createLeave($user('thang.phan@everland.vn'), $leaveType('AL'), $nextMonday3->copy(), $nextMonday3->copy()->addDay(), 'Nghỉ phép năm cùng gia đình, đã hoàn tất báo giá cho khách.');
        $service->submit($leave3, 'leave');
        $approveSteps($leave3, 2, 'Đồng ý.');

        // 4. Đơn nghỉ đang chờ HR chốt
        $leave4 = $createLeave($user('cuong.le@everland.vn'), $leaveType('AL'), $nextMonday2->copy(), $nextMonday2->copy()->addDay(), 'Nghỉ phép năm, đã bàn giao hồ sơ dự án cho bộ phận liên quan.');
        $service->submit($leave4, 'leave');
        $approveSteps($leave4, 2, 'Đồng ý.');

        // 5. Đơn nghỉ đã duyệt hoàn tất (đã trừ quỹ phép)
        $leave5 = $createLeave($user('employee@everland.vn'), $leaveType('AL'), $lastMonday->copy(), $lastMonday->copy()->addDay(), 'Nghỉ phép năm đã đăng ký trước, công việc đã bàn giao đầy đủ.');
        $service->submit($leave5, 'leave');
        $approveAll($leave5, 'Đồng ý, đơn hợp lệ.');

        // 6. Đơn nghỉ nửa ngày đã duyệt
        $leave6 = $createLeave($user('trang.nguyen@everland.vn'), $leaveType('AL'), $lastMonday->copy()->addDays(3), $lastMonday->copy()->addDays(3), 'Nghỉ buổi chiều để giải quyết giấy tờ cá nhân.', 'afternoon');
        $service->submit($leave6, 'leave');
        $approveAll($leave6);

        // 7. Đơn bị trưởng phòng từ chối
        $leave7 = $createLeave($user('khoi.dang@everland.vn'), $leaveType('AL'), $nextMonday2->copy()->addDays(1), $nextMonday2->copy()->addDays(1), 'Nghỉ phép cá nhân trong thời gian cao điểm bán hàng.');
        $service->submit($leave7, 'leave');
        $approveSteps($leave7, 1, 'Đồng ý.');
        $leave7->refresh();
        $current = $leave7->approvals()->where('status', 'pending')->orderBy('step')->first();
        if ($current) {
            $service->reject($current, $current->approver, 'Trùng giai đoạn cao điểm chốt sales, đề nghị dời lịch sang tuần sau.');
        }

        // 8. Đơn nhân viên tự huỷ
        $leave8 = $createLeave($user('employee@everland.vn'), $leaveType('UL'), $nextMonday3->copy()->addDays(2), $nextMonday3->copy()->addDays(2), 'Dự kiến nghỉ không lương để giải quyết việc riêng.');
        $service->submit($leave8, 'leave');
        $service->cancel($leave8->fresh(), $leave8->user);

        // 9. Đơn nghỉ ốm đã duyệt
        $leave9 = $createLeave($user('phuc.mai@everland.vn'), $leaveType('SL'), $lastMonday->copy()->addDays(1), $lastMonday->copy()->addDays(1), 'Nghỉ ốm có giấy xác nhận của bệnh viện, đã nộp bản gốc cho HR.');
        $service->submit($leave9, 'leave');
        $approveAll($leave9);

        // 10. Đơn của nhân sự HR đang chờ Giám đốc
        $leave10 = $createLeave($user('linh.trinh@everland.vn'), $leaveType('AL'), $nextMonday3->copy()->addDays(3), $nextMonday3->copy()->addDays(3), 'Nghỉ phép năm, đã bàn giao công tác tuyển dụng cho đồng nghiệp.');
        $service->submit($leave10, 'leave');
        $approveSteps($leave10, 1, 'Đồng ý.');

        // === OT ===
        $createOt = function (User $requester, Carbon $date, string $start, string $end, string $type, string $project, string $reason) use ($calculator) {
            return OvertimeRequest::create([
                'code' => OvertimeRequest::nextCode(),
                'user_id' => $requester->id,
                'ot_date' => $date->toDateString(),
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'hours' => $calculator->otHours($start, $end),
                'ot_type' => $type,
                'multiplier' => $calculator->otRate($type),
                'project' => $project,
                'reason' => $reason,
                'status' => 'pending',
                'current_step' => 1,
                'total_steps' => 0,
            ]);
        };

        // OT đã duyệt
        $ot1 = $createOt($user('employee@everland.vn'), $lastMonday->copy()->addDays(1), '18:00', '21:00', 'weekday', 'Everland Riverside', 'Hoàn thiện hồ sơ bàn giao căn hộ cho khách hàng ký hợp đồng.');
        $service->submit($ot1, 'overtime');
        $approveAll($ot1, 'Đồng ý.');

        // OT cuối tuần đã duyệt
        $ot2 = $createOt($user('trang.nguyen@everland.vn'), $lastMonday->copy()->addDays(5), '09:00', '12:00', 'weekend', 'Everland City Tour', 'Hỗ trợ sự kiện mở bán cuối tuần tại sàn giao dịch.');
        $service->submit($ot2, 'overtime');
        $approveAll($ot2);

        // OT đang chờ trưởng nhóm
        $ot3 = $createOt($user('trang.nguyen@everland.vn'), $nextMonday2->copy()->addDays(1), '18:00', '20:00', 'weekday', 'Everland Riverside', 'Chuẩn bị tài liệu họp với đối tác nước ngoài.');
        $service->submit($ot3, 'overtime');

        // OT bị từ chối
        $ot4 = $createOt($user('phuc.mai@everland.vn'), $lastMonday->copy()->addDays(2), '18:00', '21:00', 'weekday', 'Văn phòng Kế toán', 'Rà soát bảng lương tháng.');
        $service->submit($ot4, 'overtime');
        $ot4->refresh();
        $currentOt = $ot4->approvals()->where('status', 'pending')->orderBy('step')->first();
        if ($currentOt) {
            $service->reject($currentOt, $currentOt->approver, 'Công việc có thể xử lý trong giờ hành chính, không cần OT.');
        }

        // OT đang chờ trưởng nhóm thứ hai
        $ot5 = $createOt($user('khoi.dang@everland.vn'), $nextMonday2->copy()->addDays(2), '17:30', '20:00', 'weekday', 'Everland Marina', 'Tiếp khách hàng ngoài giờ theo yêu cầu của đối tác.');
        $service->submit($ot5, 'overtime');

        // Thông báo chào mừng
        foreach (User::active()->get() as $member) {
            AppNotification::create([
                'user_id' => $member->id,
                'title' => 'Chào mừng đến với Everland HR',
                'body' => 'Hệ thống quản lý nghỉ phép và làm thêm giờ đã sẵn sàng. Bạn có thể theo dõi quỹ phép, lịch nghỉ và gửi đơn trực tuyến.',
                'type' => 'info',
                'link' => route('dashboard'),
            ]);

            AppNotification::create([
                'user_id' => $member->id,
                'title' => 'Chính sách nghỉ phép năm '.now()->year,
                'body' => 'Mỗi nhân viên có 12 ngày phép năm, tối đa 5 ngày được chuyển sang năm sau. Vui lòng gửi đơn trước ít nhất 2 ngày.',
                'type' => 'info',
                'link' => route('balances.index'),
            ]);
        }
    }
}
