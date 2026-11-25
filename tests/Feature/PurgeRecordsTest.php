<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $protected;
    private User $admin;
    private User $employee;
    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['slug' => 'admin', 'name' => 'Quản trị hệ thống']);
        $employeeRole = Role::create(['slug' => 'employee', 'name' => 'Nhân viên']);

        $employeeRole->permissions()->attach([
            Permission::firstOrCreate(['slug' => 'leave.view.own'], ['name' => 'Xem đơn nghỉ của mình'])->id,
            Permission::firstOrCreate(['slug' => 'audit.view'], ['name' => 'Xem nhật ký hoạt động'])->id,
        ]);

        $this->admin = $this->makeUser('admin@test.vn', $adminRole, 'Quản trị viên');
        $this->protected = $this->makeUser('owner@test.vn', $adminRole, 'Chủ hệ thống');
        $this->protected->forceFill(['is_protected' => true])->save();
        $this->employee = $this->makeUser('employee@test.vn', $employeeRole, 'Nhân viên');

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
    }

    public function test_purge_actions_are_rendered_only_for_the_protected_account(): void
    {
        $leave = $this->makeLeave('pending', true, 2);
        $this->makeAuditLog('Bản ghi để kiểm tra giao diện');

        $this->actingAs($this->protected)
            ->get(route('leave.index', ['scope' => 'all']))
            ->assertOk()
            ->assertSee('Xoá vĩnh viễn đơn ' . $leave->code);

        $this->actingAs($this->protected)
            ->get(route('leave.show', $leave))
            ->assertOk()
            ->assertSee('Xoá đơn');

        $this->actingAs($this->protected)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Xoá toàn bộ')
            ->assertSee('Xoá bản ghi nhật ký này?');
    }

    public function test_purge_actions_are_hidden_from_other_accounts(): void
    {
        $leave = $this->makeLeave('pending', true, 2);
        $this->makeAuditLog('Bản ghi để kiểm tra giao diện');

        foreach ([$this->admin, $this->employee] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('leave.index', ['scope' => 'all']))
                ->assertOk()
                ->assertDontSee('Xoá vĩnh viễn đơn ' . $leave->code);

            $this->actingAs($viewer)
                ->get(route('leave.show', $leave))
                ->assertOk()
                ->assertDontSee('Xoá đơn');

            $this->actingAs($viewer)
                ->get(route('admin.audit.index'))
                ->assertOk()
                ->assertDontSee('Xoá toàn bộ')
                ->assertDontSee('Xoá bản ghi nhật ký này?');
        }
    }

    public function test_purge_permission_is_not_listed_in_the_permission_matrix(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertDontSee('records.purge');
    }

    public function test_protected_account_can_delete_single_audit_log(): void
    {
        $keep = $this->makeAuditLog('Giữ lại');
        $remove = $this->makeAuditLog('Xoá bản ghi này');

        $this->actingAs($this->protected)
            ->delete(route('admin.audit.destroy', $remove))
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', ['id' => $remove->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $keep->id]);
    }

    public function test_protected_account_can_clear_audit_history(): void
    {
        $this->makeAuditLog('Một');
        $this->makeAuditLog('Hai');
        $this->makeAuditLog('Ba');

        $this->actingAs($this->protected)
            ->delete(route('admin.audit.clear'))
            ->assertRedirect();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_regular_admin_cannot_delete_audit_history(): void
    {
        $log = $this->makeAuditLog('Không được xoá');

        $this->actingAs($this->admin)
            ->delete(route('admin.audit.destroy', $log))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->delete(route('admin.audit.clear'))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    public function test_protected_account_can_delete_approved_leave_and_refund_balance(): void
    {
        $leave = $this->makeLeave('approved', true, 2);
        $approval = $this->makeApproval($leave);

        $this->actingAs($this->protected)
            ->delete(route('leave.destroy', $leave), ['scope' => 'all'])
            ->assertRedirect(route('leave.index', ['scope' => 'all']));

        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id]);
        $this->assertDatabaseMissing('approvals', ['id' => $approval->id]);
        $this->assertSame(0.0, $this->usedDays());
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave.delete']);
    }

    public function test_protected_account_can_delete_pending_leave_and_refund_balance(): void
    {
        $leave = $this->makeLeave('pending', true, 2);
        $this->makeApproval($leave);

        $this->actingAs($this->protected)
            ->delete(route('leave.destroy', $leave))
            ->assertRedirect();

        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id]);
        $this->assertSame(0.0, $this->usedDays());
    }

    public function test_regular_admin_cannot_delete_leave_request(): void
    {
        $leave = $this->makeLeave('approved', true, 2);

        $this->actingAs($this->admin)
            ->delete(route('leave.destroy', $leave))
            ->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id]);
        $this->assertSame(2.0, $this->usedDays());
    }

    private function makeLeave(string $status, bool $deducted, float $days): LeaveRequest
    {
        LeaveBalance::updateOrCreate(
            [
                'user_id'       => $this->employee->id,
                'year'          => now()->year,
                'leave_type_id' => $this->leaveType->id,
            ],
            [
                'entitled_days' => 10,
                'used_days'     => $deducted ? $days : 0,
                'carried_over'  => 0,
            ]
        );

        return LeaveRequest::create([
            'code'             => LeaveRequest::nextCode(),
            'user_id'          => $this->employee->id,
            'leave_type_id'    => $this->leaveType->id,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->toDateString(),
            'day_type'         => 'full',
            'total_days'       => $days,
            'reason'           => 'Đơn nghỉ phép dùng để kiểm tra chức năng xoá',
            'status'           => $status,
            'current_step'     => 1,
            'total_steps'      => 1,
            'balance_deducted' => $deducted,
            'decided_at'       => $status === 'approved' ? now() : null,
        ]);
    }

    private function makeApproval(LeaveRequest $leave): Approval
    {
        return Approval::create([
            'approvable_type' => $leave->getMorphClass(),
            'approvable_id'   => $leave->id,
            'step'            => 1,
            'approver_id'     => $this->admin->id,
            'approver_role'   => 'admin',
            'status'          => $leave->status === 'approved' ? 'approved' : 'pending',
        ]);
    }

    private function makeAuditLog(string $description): AuditLog
    {
        return AuditLog::create([
            'user_id'     => $this->admin->id,
            'action'      => 'test',
            'module'      => 'test',
            'description' => $description,
        ]);
    }

    private function usedDays(): float
    {
        return (float) LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->where('year', now()->year)
            ->value('used_days');
    }

    private function makeUser(string $email, Role $role, string $name): User
    {
        return User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => 'password123',
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }
}
