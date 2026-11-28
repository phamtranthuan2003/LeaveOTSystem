<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $manager;

    private User $hr;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeRole = $this->makeRole('employee', ['calendar.view', 'leave.view.own']);
        $managerRole = $this->makeRole('manager', ['calendar.view', 'leave.view.own', 'leave.approve']);
        $hrRole = $this->makeRole('hr', ['calendar.view', 'leave.view.all']);

        $this->manager = $this->makeUser('manager@test.vn', $managerRole, 'Trưởng nhóm Bình');
        $this->hr = $this->makeUser('hr@test.vn', $hrRole, 'Nhân sự Hoa');
        $this->employee = $this->makeUser('employee@test.vn', $employeeRole, 'Nhân viên Nam', $this->manager->id);

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
    }

    public function test_employee_sees_only_own_entries_on_the_calendar(): void
    {
        $this->makeLeave($this->employee);
        $this->makeLeave($this->manager);

        $this->actingAs($this->employee)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Nhân viên Nam')
            ->assertDontSee('Trưởng nhóm Bình')
            ->assertSee('Chỉ hiển thị đơn của bạn')
            ->assertSee('Cá nhân');
    }

    public function test_manager_without_view_all_permission_sees_only_own_entries(): void
    {
        $this->makeLeave($this->employee);
        $this->makeLeave($this->manager);

        $this->actingAs($this->manager)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Trưởng nhóm Bình')
            ->assertDontSee('Nhân viên Nam');
    }

    public function test_view_all_permission_still_shows_company_entries(): void
    {
        $this->makeLeave($this->employee);
        $this->makeLeave($this->manager);

        $this->actingAs($this->hr)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Nhân viên Nam')
            ->assertSee('Trưởng nhóm Bình')
            ->assertSee('Công ty');
    }

    private function makeLeave(User $user): LeaveRequest
    {
        return LeaveRequest::create([
            'code' => LeaveRequest::nextCode(),
            'user_id' => $user->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'day_type' => 'full',
            'total_days' => 1,
            'reason' => 'Đơn nghỉ phép dùng để kiểm tra phạm vi lịch',
            'status' => 'approved',
            'current_step' => 1,
            'total_steps' => 1,
            'balance_deducted' => false,
            'decided_at' => now(),
        ]);
    }

    private function makeRole(string $slug, array $permissionSlugs): Role
    {
        $role = Role::create(['slug' => $slug, 'name' => ucfirst($slug)]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::firstOrCreate(
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