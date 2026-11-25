<?php

namespace Tests\Feature;

use App\Mail\LeaveApprovalPendingMail;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeaveApprovalEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $manager;
    private User $hr;
    private LeaveType $leaveType;
    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeRole = $this->makeRole('employee', ['leave.create', 'leave.view.own', 'leave.cancel.own']);
        $managerRole = $this->makeRole('manager', ['leave.approve']);
        $hrRole = $this->makeRole('hr', []);

        $this->manager = $this->makeUser('manager@test.vn', $managerRole, 'Trưởng phòng');
        $this->hr = $this->makeUser('hr@test.vn', $hrRole, 'Nhân sự');
        $this->employee = $this->makeUser('employee@test.vn', $employeeRole, 'Nhân viên', $this->manager->id);

        $this->leaveType = LeaveType::create([
            'code'                => 'AL',
            'name'                => 'Phép năm',
            'color'               => '#2f6bff',
            'default_days'        => 12,
            'deduct_balance'      => true,
            'requires_attachment' => false,
            'is_paid'             => true,
            'active'              => true,
        ]);

        $this->start = now()->startOfDay()->next(Carbon::MONDAY);

        LeaveBalance::create([
            'user_id'       => $this->employee->id,
            'year'          => $this->start->year,
            'leave_type_id' => $this->leaveType->id,
            'entitled_days' => 10,
            'used_days'     => 0,
            'carried_over'  => 0,
        ]);
    }

    public function test_submitting_leave_emails_the_first_approver(): void
    {
        Mail::fake();

        $this->submitLeave();

        Mail::assertSentCount(1);
        Mail::assertSent(LeaveApprovalPendingMail::class, function (LeaveApprovalPendingMail $mail) {
            return $mail->hasTo($this->manager->email)
                && $mail->approver->is($this->manager)
                && $mail->leave->is(LeaveRequest::firstOrFail());
        });
    }

    public function test_hand_off_to_next_approver_sends_another_email(): void
    {
        Mail::fake();

        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('approvals.approve', $leave->approvals()->firstOrFail()))
            ->assertRedirect();

        Mail::assertSentCount(2);
        Mail::assertSent(LeaveApprovalPendingMail::class, function (LeaveApprovalPendingMail $mail) {
            return $mail->hasTo($this->hr->email) && $mail->approver->is($this->hr);
        });
    }

    public function test_overtime_submission_sends_no_email(): void
    {
        Mail::fake();

        $overtime = OvertimeRequest::create([
            'code'       => OvertimeRequest::nextCode(),
            'user_id'    => $this->employee->id,
            'ot_date'    => $this->start->toDateString(),
            'start_time' => '18:00',
            'end_time'   => '20:00',
            'hours'      => 2,
            'ot_type'    => 'weekday',
            'multiplier' => 1.5,
            'reason'     => 'Chạy báo cáo cuối ngày',
            'status'     => 'pending',
        ]);

        app(ApprovalService::class)->submit($overtime, 'overtime');

        Mail::assertNothingSent();
    }

    public function test_mail_body_renders_request_details(): void
    {
        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $html = (new LeaveApprovalPendingMail($leave, $this->manager))->render();

        $this->assertStringContainsString($leave->code, $html);
        $this->assertStringContainsString($this->employee->name, $html);
        $this->assertStringContainsString($this->leaveType->name, $html);
        $this->assertStringContainsString(route('leave.show', $leave), $html);
    }

    public function test_mail_failure_does_not_block_leave_submission(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $this->submitLeave();

        $leave = LeaveRequest::firstOrFail();

        $this->assertSame('pending', $leave->status);
        $this->assertCount(2, $leave->approvals);
    }

    private function submitLeave(): void
    {
        $this->actingAs($this->employee)->post(route('leave.store'), [
            'leave_type_id' => $this->leaveType->id,
            'start_date'    => $this->start->toDateString(),
            'end_date'      => $this->start->toDateString(),
            'day_type'      => 'full',
            'reason'        => 'Nghỉ phép để xử lý việc gia đình',
        ])->assertRedirect();
    }

    private function makeRole(string $slug, array $permissionSlugs): Role
    {
        $role = Role::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::updateOrCreate(
                ['slug' => $permissionSlug],
                ['name' => $permissionSlug, 'group' => 'Test']
            );

            $role->permissions()->attach($permission->id);
        }

        return $role;
    }

    private function makeUser(string $email, Role $role, string $name, ?int $managerId = null): User
    {
        return User::create([
            'name'       => $name,
            'email'      => $email,
            'password'   => 'password123',
            'role_id'    => $role->id,
            'manager_id' => $managerId,
            'status'     => 'active',
        ]);
    }
}
