<?php

namespace Tests\Feature;

use App\Mail\ApprovalPendingMail;
use App\Mail\RequestStatusMail;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeaveApprovalEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $leader;

    private User $manager;

    private User $director;

    private User $hr;

    private User $admin;

    private LeaveType $leaveType;

    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeRole = $this->makeRole('employee', [
            'leave.create', 'leave.view.own', 'leave.cancel.own',
            'ot.create', 'ot.view.own', 'ot.cancel.own',
        ]);
        $leaderRole = $this->makeRole('leader', ['leave.approve', 'ot.approve']);
        $managerRole = $this->makeRole('manager', ['leave.approve', 'ot.approve']);
        $directorRole = $this->makeRole('director', ['leave.approve', 'ot.approve']);
        $hrRole = $this->makeRole('hr', ['leave.approve']);
        $adminRole = $this->makeRole('admin', []);

        $this->director = $this->makeUser('director@test.vn', $directorRole, 'Tổng Giám đốc');
        $this->manager = $this->makeUser('manager@test.vn', $managerRole, 'Giám đốc khối', $this->director->id);
        $this->leader = $this->makeUser('leader@test.vn', $leaderRole, 'Trưởng nhóm', $this->manager->id);
        $this->hr = $this->makeUser('hr@test.vn', $hrRole, 'Nhân sự', $this->director->id);
        $this->admin = $this->makeUser('admin@test.vn', $adminRole, 'Quản trị viên');
        $this->employee = $this->makeUser('employee@test.vn', $employeeRole, 'Nhân viên', $this->leader->id);

        $this->leaveType = LeaveType::create([
            'code' => 'AL',
            'name' => 'Phép năm',
            'color' => '#2f6bff',
            'default_days' => 12,
            'deduct_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);

        $this->start = now()->startOfDay()->next(Carbon::MONDAY);

        LeaveBalance::create([
            'user_id' => $this->employee->id,
            'year' => $this->start->year,
            'leave_type_id' => $this->leaveType->id,
            'entitled_days' => 10,
            'used_days' => 0,
            'carried_over' => 0,
        ]);
    }

    public function test_submitting_leave_emails_direct_manager_and_requester(): void
    {
        Mail::fake();

        $this->submitLeave();

        Mail::assertSentCount(2);

        Mail::assertSent(ApprovalPendingMail::class, function (ApprovalPendingMail $mail) {
            return $mail->hasTo($this->leader->email)
                && $mail->approver->is($this->leader)
                && $mail->approvedBy === null
                && $mail->requestable->is(LeaveRequest::firstOrFail());
        });

        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'submitted'
                && $mail->pendingWith?->is($this->leader);
        });
    }

    public function test_each_approval_emails_next_approver_and_requester(): void
    {
        Mail::fake();

        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $this->approveNext($leave, $this->leader);

        Mail::assertSentCount(4);
        Mail::assertSent(ApprovalPendingMail::class, function (ApprovalPendingMail $mail) {
            return $mail->hasTo($this->manager->email)
                && $mail->approver->is($this->manager)
                && $mail->approvedBy?->is($this->leader);
        });
        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'progressed'
                && $mail->approvedBy?->is($this->leader)
                && $mail->pendingWith?->is($this->manager);
        });

        $this->approveNext($leave, $this->manager);
        $this->approveNext($leave, $this->director);

        Mail::assertSent(ApprovalPendingMail::class, function (ApprovalPendingMail $mail) {
            return $mail->hasTo($this->hr->email)
                && $mail->approver->is($this->hr)
                && $mail->approvedBy?->is($this->director);
        });

        $this->approveNext($leave, $this->hr);

        Mail::assertSentCount(9);
        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'completed'
                && $mail->approvedBy?->is($this->hr);
        });

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_overtime_submission_emails_direct_manager_and_requester(): void
    {
        Mail::fake();

        $overtime = $this->createOvertime();
        app(ApprovalService::class)->submit($overtime, 'overtime');

        Mail::assertSentCount(2);
        Mail::assertSent(ApprovalPendingMail::class, function (ApprovalPendingMail $mail) use ($overtime) {
            return $mail->hasTo($this->leader->email)
                && $mail->approver->is($this->leader)
                && $mail->requestable->is($overtime);
        });
        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) use ($overtime) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'submitted'
                && $mail->pendingWith?->is($this->leader)
                && $mail->requestable->is($overtime);
        });
    }

    public function test_overtime_final_approval_emails_requester_completion(): void
    {
        Mail::fake();

        $overtime = $this->createOvertime();
        app(ApprovalService::class)->submit($overtime, 'overtime');

        $this->approveNext($overtime, $this->leader);
        $this->approveNext($overtime, $this->manager);
        $this->approveNext($overtime, $this->director);

        Mail::assertSentCount(7);
        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) use ($overtime) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'completed'
                && $mail->approvedBy?->is($this->director)
                && $mail->requestable->is($overtime);
        });

        $this->assertSame('approved', $overtime->fresh()->status);
    }

    public function test_rejection_emails_requester_with_reason(): void
    {
        Mail::fake();

        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();
        $approval = $leave->approvals()->where('status', 'pending')->firstOrFail();

        $this->actingAs($this->leader)
            ->post(route('approvals.reject', $approval), [
                'note' => 'Trùng lịch giao hàng quan trọng của nhóm.',
            ])
            ->assertRedirect();

        Mail::assertSentCount(3);
        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'rejected'
                && $mail->approvedBy?->is($this->leader)
                && $mail->reason === 'Trùng lịch giao hàng quan trọng của nhóm.';
        });

        $this->assertSame('rejected', $leave->fresh()->status);
    }

    public function test_highest_manager_can_approve_all_but_hr_must_still_approve(): void
    {
        Mail::fake();

        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $this->approveNext($leave, $this->leader);
        $this->approveNext($leave, $this->manager);

        $directorApproval = $leave->fresh()->approvals()->where('status', 'pending')->firstOrFail();

        $this->actingAs($this->director)
            ->post(route('approvals.approve-all', $directorApproval), [
                'note' => 'Đồng ý toàn bộ, chuyển HR chốt.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $leave->refresh();
        $hrApproval = $leave->approvals()->where('status', 'pending')->firstOrFail();

        $this->assertSame('pending', $leave->status);
        $this->assertSame($hrApproval->step, (int) $leave->current_step);
        $this->assertSame($this->hr->id, $hrApproval->approver_id);
        $this->assertSame(0, $leave->approvals()->where('status', 'pending')->where('step', '!=', $hrApproval->step)->count());

        Mail::assertSent(ApprovalPendingMail::class, function (ApprovalPendingMail $mail) {
            return $mail->hasTo($this->hr->email)
                && $mail->approver->is($this->hr)
                && $mail->approvedBy?->is($this->director)
                && $mail->approvedAllLevels;
        });

        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'progressed'
                && $mail->approvedBy?->is($this->director)
                && $mail->pendingWith?->is($this->hr)
                && $mail->approvedAllLevels;
        });

        $this->approveNext($leave, $this->hr);

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_regular_manager_cannot_approve_all(): void
    {
        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();
        $leaderApproval = $leave->approvals()->where('status', 'pending')->firstOrFail();

        $this->actingAs($this->leader)
            ->post(route('approvals.approve-all', $leaderApproval))
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertSame(4, $leave->approvals()->where('status', 'pending')->count());
    }

    public function test_admin_approve_all_completes_even_hr_step(): void
    {
        Mail::fake();

        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();
        $approval = $leave->approvals()->where('status', 'pending')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('approvals.approve-all', $approval))
            ->assertRedirect()
            ->assertSessionHas('success');

        $leave->refresh();

        $this->assertSame('approved', $leave->status);
        $this->assertSame(0, $leave->approvals()->where('status', 'pending')->count());

        Mail::assertSent(RequestStatusMail::class, function (RequestStatusMail $mail) {
            return $mail->hasTo($this->employee->email)
                && $mail->event === 'completed'
                && $mail->byAdmin;
        });

        Mail::assertNotSent(ApprovalPendingMail::class, function (ApprovalPendingMail $mail) {
            return $mail->hasTo($this->hr->email);
        });
    }

    public function test_leave_chain_always_ends_with_hr(): void
    {
        $hrReport = $this->makeUser('hr-report@test.vn', $this->employee->role, 'Nhân viên HCNS', $this->hr->id);

        $chain = app(ApprovalService::class)->buildChain($hrReport, 'leave');

        $this->assertCount(2, $chain);
        $this->assertTrue($chain[0]->is($this->director));
        $this->assertTrue($chain[1]->is($this->hr));
    }

    public function test_mail_body_renders_request_details(): void
    {
        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $html = (new ApprovalPendingMail($leave, $this->leader))->render();

        $this->assertStringContainsString($leave->code, $html);
        $this->assertStringContainsString($this->employee->name, $html);
        $this->assertStringContainsString($this->leaveType->name, $html);
        $this->assertStringContainsString(route('leave.show', $leave), $html);

        $statusHtml = (new RequestStatusMail(
            $leave,
            $this->employee,
            'progressed',
            $this->leader,
            $this->manager
        ))->render();

        $this->assertStringContainsString($this->leader->name, $statusHtml);
        $this->assertStringContainsString($this->manager->name, $statusHtml);
    }

    public function test_mail_failure_does_not_block_leave_submission(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $this->submitLeave();

        $leave = LeaveRequest::firstOrFail();

        $this->assertSame('pending', $leave->status);
        $this->assertCount(4, $leave->approvals);
    }

    private function submitLeave(): void
    {
        $this->actingAs($this->employee)->post(route('leave.store'), [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => $this->start->toDateString(),
            'end_date' => $this->start->toDateString(),
            'day_type' => 'full',
            'reason' => 'Nghỉ phép để xử lý việc gia đình',
        ])->assertRedirect();
    }

    private function approveNext(Model $requestable, User $approver): void
    {
        $requestable->refresh();
        $approval = $requestable->approvals()->where('status', 'pending')->orderBy('step')->firstOrFail();

        $this->actingAs($approver)
            ->post(route('approvals.approve', $approval))
            ->assertRedirect();
    }

    private function createOvertime(): OvertimeRequest
    {
        return OvertimeRequest::create([
            'code' => OvertimeRequest::nextCode(),
            'user_id' => $this->employee->id,
            'ot_date' => $this->start->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'hours' => 2,
            'ot_type' => 'weekday',
            'multiplier' => 1.5,
            'reason' => 'Chạy báo cáo cuối ngày',
            'status' => 'pending',
        ]);
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
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role_id' => $role->id,
            'manager_id' => $managerId,
            'status' => 'active',
        ]);
    }
}
