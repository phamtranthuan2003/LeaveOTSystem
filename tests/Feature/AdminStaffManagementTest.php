<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $employeeRole;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'slug' => 'admin',
            'name' => 'Quản trị hệ thống',
        ]);

        $this->employeeRole = Role::create([
            'slug' => 'employee',
            'name' => 'Nhân viên',
        ]);

        $this->admin = User::create([
            'name'     => 'Quản trị viên',
            'email'    => 'admin@test.vn',
            'password' => 'password123',
            'role_id'  => $adminRole->id,
            'status'   => 'active',
        ]);
    }

    public function test_admin_can_create_employee(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name'                  => 'Nguyễn Văn Mới',
            'email'                 => 'nhanvien.moi@test.vn',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role_id'               => $this->employeeRole->id,
            'status'                => 'active',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

        $user = User::where('email', 'nhanvien.moi@test.vn')->first();

        $this->assertNotNull($user);
        $this->assertSame($this->employeeRole->id, $user->role_id);
        $this->assertStringStartsWith('EVG-', $user->employee_code);
    }

    public function test_admin_can_create_department_without_parent_field(): void
    {
        $this->actingAs($this->admin)->post(route('admin.departments.store'), [
            'code'   => 'QA-01',
            'name'   => 'Phòng QA',
            'active' => '1',
        ])->assertRedirect(route('admin.departments.index'))->assertSessionHas('success');

        $department = Department::where('code', 'QA-01')->first();

        $this->assertNotNull($department);
        $this->assertNull($department->parent_id);
        $this->assertSame(1, (int) $department->level);
        $this->assertTrue($department->active);
    }

    public function test_admin_can_create_child_department(): void
    {
        $parent = Department::create([
            'code'  => 'ROOT',
            'name'  => 'Tập đoàn',
            'level' => 1,
            'active' => true,
        ]);

        $this->actingAs($this->admin)->post(route('admin.departments.store'), [
            'code'      => 'CHILD-01',
            'name'      => 'Khối con',
            'parent_id' => $parent->id,
            'active'    => '1',
        ])->assertRedirect(route('admin.departments.index'))->assertSessionHas('success');

        $child = Department::where('code', 'CHILD-01')->first();

        $this->assertNotNull($child);
        $this->assertSame($parent->id, (int) $child->parent_id);
        $this->assertSame(2, (int) $child->level);
    }

    public function test_employee_cannot_create_staff_or_departments(): void
    {
        $employee = User::create([
            'name'     => 'Nhân viên thường',
            'email'    => 'employee@test.vn',
            'password' => 'password123',
            'role_id'  => $this->employeeRole->id,
            'status'   => 'active',
        ]);

        $this->actingAs($employee)->post(route('admin.users.store'), [
            'name'                  => 'Tự tạo tài khoản',
            'email'                 => 'tu.tao@test.vn',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role_id'               => $this->employeeRole->id,
            'status'                => 'active',
        ])->assertForbidden();

        $this->actingAs($employee)->post(route('admin.departments.store'), [
            'code' => 'SELF-01',
            'name' => 'Phòng tự tạo',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'tu.tao@test.vn']);
        $this->assertDatabaseMissing('departments', ['code' => 'SELF-01']);
    }
}
