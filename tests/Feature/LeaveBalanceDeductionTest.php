<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $manager;
    private LeaveType $leaveType;
    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeRole = $this->makeRole('employee', ['leave.create', 'leave.view.own', 'leave.cancel.own']);
        $managerRole = $this->makeRole('manager', ['leave.approve']);

        $this->manager = $this->makeUser('manager@test.vn', $managerRole, 'Trưởng phòng');
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

    public function test_submitting_leave_deducts_balance_immediately(): void
    {
        $this->submitLeave();

        $leave = LeaveRequest::firstOrFail();

        $this->assertSame('pending', $leave->status);
        $this->assertTrue($leave->balance_deducted);
        $this->assertSame(1.0, $this->usedDays());
    }

    public function test_rejecting_leave_refunds_balance(): void
    {
        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('approvals.reject', $leave->approvals()->firstOrFail()), [
                'note' => 'Không đủ người trực ca',
            ])->assertRedirect();

        $leave->refresh();

        $this->assertSame('rejected', $leave->status);
        $this->assertFalse($leave->balance_deducted);
        $this->assertSame(0.0, $this->usedDays());
    }

    public function test_cancelling_pending_leave_refunds_balance(): void
    {
        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $this->actingAs($this->employee)
            ->post(route('leave.cancel', $leave))
            ->assertRedirect();

        $leave->refresh();

        $this->assertSame('cancelled', $leave->status);
        $this->assertFalse($leave->balance_deducted);
        $this->assertSame(0.0, $this->usedDays());
    }

    public function test_final_approval_does_not_deduct_balance_again(): void
    {
        $this->submitLeave();
        $leave = LeaveRequest::firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('approvals.approve', $leave->approvals()->firstOrFail()))
            ->assertRedirect();

        $leave->refresh();

        $this->assertSame('approved', $leave->status);
        $this->assertTrue($leave->balance_deducted);
        $this->assertSame(1.0, $this->usedDays());
    }

    public function test_legacy_pending_leave_is_deducted_when_finally_approved(): void
    {
        $leave = LeaveRequest::create([
            'code'          => LeaveRequest::nextCode(),
            'user_id'       => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date'    => $this->start->toDateString(),
            'end_date'      => $this->start->toDateString(),
            'day_type'      => 'full',
            'total_days'    => 1,
            'reason'        => 'Đơn cũ tạo trước khi đổi cách trừ quỹ',
            'status'        => 'pending',
            'current_step'  => 1,
            'total_steps'   => 1,
        ]);

        $approval = Approval::create([
            'approvable_type' => $leave->getMorphClass(),
            'approvable_id'   => $leave->id,
            'step'            => 1,
            'approver_id'     => $this->manager->id,
            'approver_role'   => 'manager',
            'status'          => 'pending',
        ]);

        $this->actingAs($this->manager)
            ->post(route('approvals.approve', $approval))
            ->assertRedirect();

        $leave->refresh();

        $this->assertSame('approved', $leave->status);
        $this->assertTrue($leave->balance_deducted);
        $this->assertSame(1.0, $this->usedDays());
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

    private function usedDays(): float
    {
        return (float) LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->where('year', $this->start->year)
            ->value('used_days');
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
