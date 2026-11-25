<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $adminRole;
    private Role $employeeRole;
    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['slug' => 'admin', 'name' => 'Quản trị hệ thống']);
        $this->employeeRole = Role::create(['slug' => 'employee', 'name' => 'Nhân viên']);

        $this->admin = User::create([
            'name'     => 'Quản trị viên',
            'email'    => 'admin@test.vn',
            'password' => 'password123',
            'role_id'  => $this->adminRole->id,
            'status'   => 'active',
        ]);

        $this->leaveType = LeaveType::create([
            'code'           => 'AL',
            'name'           => 'Phép năm',
            'color'          => '#2f6bff',
            'default_days'   => 12,
            'deduct_balance' => true,
            'is_paid'        => true,
            'active'         => true,
        ]);
    }

    public function test_admin_can_delete_unused_staff_account(): void
    {
        $employee = $this->makeEmployee('nhanvien.xoa@test.vn');

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $employee))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
        $this->assertSame(1, AuditLog::where('action', 'user.delete')->count());
    }

    public function test_cannot_delete_own_account_or_account_with_requests(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        $employee = $this->makeEmployee('nhanvien.don@test.vn');
        $this->makeLeaveRequest($employee);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $employee))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $employee->id]);

        $approver = $this->makeEmployee('nhanvien.duyet@test.vn');
        $requester = $this->makeEmployee('nhanvien.yeucau@test.vn');
        $leaveRequest = $this->makeLeaveRequest($requester);
        Approval::create([
            'approvable_type' => LeaveRequest::class,
            'approvable_id'   => $leaveRequest->id,
            'step'            => 1,
            'approver_id'     => $approver->id,
            'status'          => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $approver))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $approver->id]);
    }

    public function test_department_delete_respects_members_and_children(): void
    {
        $parent = Department::create(['code' => 'ROOT', 'name' => 'Tập đoàn', 'level' => 1, 'active' => true]);
        $child = Department::create(['code' => 'CHILD', 'name' => 'Khối con', 'parent_id' => $parent->id, 'level' => 2, 'active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $parent))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('departments', ['id' => $parent->id]);

        $employee = $this->makeEmployee('nhanvien.pb@test.vn', ['department_id' => $child->id]);
        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $child))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('departments', ['id' => $child->id]);

        $employee->update(['department_id' => null]);
        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $child))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('departments', ['id' => $child->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.departments.destroy', $parent))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('departments', ['id' => $parent->id]);
    }

    public function test_leave_type_delete_is_blocked_when_requests_exist(): void
    {
        $employee = $this->makeEmployee('nhanvien.loai@test.vn');
        $this->makeLeaveRequest($employee);

        $this->actingAs($this->admin)
            ->delete(route('admin.leave-types.destroy', $this->leaveType))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('leave_types', ['id' => $this->leaveType->id]);

        LeaveRequest::query()->delete();

        LeaveBalance::create([
            'user_id'       => $employee->id,
            'year'          => now()->year,
            'leave_type_id' => $this->leaveType->id,
            'entitled_days' => 12,
            'used_days'     => 0,
            'carried_over'  => 0,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.leave-types.destroy', $this->leaveType))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('leave_types', ['id' => $this->leaveType->id]);
        $this->assertDatabaseMissing('leave_balances', ['leave_type_id' => $this->leaveType->id]);
    }

    public function test_role_delete_is_blocked_for_admin_and_roles_in_use(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $this->adminRole))
            ->assertForbidden();

        $role = Role::create(['slug' => 'truong-ca', 'name' => 'Trưởng ca']);
        $user = $this->makeEmployee('nhanvien.vai@test.vn', ['role_id' => $role->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        $user->update(['role_id' => $this->employeeRole->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_regular_employee_cannot_delete_any_records(): void
    {
        $employee = $this->makeEmployee('nhanvien.thuong@test.vn');
        $other = $this->makeEmployee('nhanvien.khac@test.vn');

        $this->actingAs($employee)
            ->delete(route('admin.users.destroy', $other))
            ->assertForbidden();

        $this->actingAs($employee)
            ->delete(route('admin.leave-types.destroy', $this->leaveType))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('leave_types', ['id' => $this->leaveType->id]);
    }

    public function test_index_pages_render_delete_actions(): void
    {
        $employee = $this->makeEmployee('nhanvien.hienthi@test.vn');
        $department = Department::create(['code' => 'PH-01', 'name' => 'Phòng hiển thị', 'level' => 1, 'active' => true]);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()->assertSee(route('admin.users.destroy', $employee), false);
        $this->actingAs($this->admin)->get(route('admin.departments.index'))
            ->assertOk()->assertSee(route('admin.departments.destroy', $department), false);
        $this->actingAs($this->admin)->get(route('admin.leave-types.index'))
            ->assertOk()->assertSee(route('admin.leave-types.destroy', $this->leaveType), false);
        $this->actingAs($this->admin)->get(route('admin.roles.index'))
            ->assertOk()->assertSee(route('admin.roles.destroy', $this->employeeRole), false);
    }

    private function makeEmployee(string $email, array $attributes = []): User
    {
        return User::create(array_merge([
            'name'     => 'Nhân viên ' . $email,
            'email'    => $email,
            'password' => 'password123',
            'role_id'  => $this->employeeRole->id,
            'status'   => 'active',
        ], $attributes));
    }

    private function makeLeaveRequest(User $user): LeaveRequest
    {
        return LeaveRequest::create([
            'code'          => 'NP-' . now()->year . '-' . str_pad((string) (LeaveRequest::count() + 1), 4, '0', STR_PAD_LEFT),
            'user_id'       => $user->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date'    => now()->addDay()->toDateString(),
            'end_date'      => now()->addDay()->toDateString(),
            'total_days'    => 1,
            'reason'        => 'Việc riêng',
        ]);
    }
}
