<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'slug' => 'admin',
            'name' => 'Quản trị hệ thống',
        ]);

        Role::create([
            'slug' => 'employee',
            'name' => 'Nhân viên',
        ]);

        $this->admin = $this->makeUser('admin@test.vn', $adminRole);

        Permission::updateOrCreate(['slug' => 'calendar.view'], ['name' => 'Xem lịch nghỉ / OT', 'group' => 'Đội nhóm & lịch']);
        Permission::updateOrCreate(['slug' => 'dashboard.view'], ['name' => 'Xem bảng điều khiển', 'group' => 'Tổng quan']);
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name'        => 'Trưởng ca',
            'description' => 'Điều phối ca làm việc',
            'permissions' => ['calendar.view'],
        ])->assertRedirect(route('admin.roles.index'))->assertSessionHas('success');

        $role = Role::where('name', 'Trưởng ca')->first();

        $this->assertNotNull($role);
        $this->assertSame('truong-ca', $role->slug);
        $this->assertTrue($role->permissions()->where('slug', 'calendar.view')->exists());
    }

    public function test_role_slugs_are_unique_and_never_use_admin_slug(): void
    {
        $this->actingAs($this->admin)->post(route('admin.roles.store'), ['name' => 'Admin']);
        $this->actingAs($this->admin)->post(route('admin.roles.store'), ['name' => 'Trưởng ca']);
        $this->actingAs($this->admin)->post(route('admin.roles.store'), ['name' => 'Truong Ca']);

        $this->assertDatabaseHas('roles', ['name' => 'Admin', 'slug' => 'admin-role']);
        $this->assertDatabaseHas('roles', ['name' => 'Trưởng ca', 'slug' => 'truong-ca']);
        $this->assertDatabaseHas('roles', ['name' => 'Truong Ca', 'slug' => 'truong-ca-2']);
    }

    public function test_non_admin_cannot_create_or_update_roles(): void
    {
        $employeeRole = Role::where('slug', 'employee')->firstOrFail();
        $employee = $this->makeUser('nhanvien@test.vn', $employeeRole);

        $this->actingAs($employee)->post(route('admin.roles.store'), [
            'name' => 'Tự tạo',
        ])->assertForbidden();

        $this->actingAs($employee)->put(route('admin.roles.update', $employeeRole), [
            'permissions' => ['calendar.view'],
        ])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Tự tạo']);
        $this->assertFalse($employeeRole->permissions()->exists());
    }

    public function test_feature_access_follows_role_permissions(): void
    {
        $auditorRole = $this->createRoleWithPermissions('auditor', 'Kiểm toán', ['calendar.view']);
        $auditor = $this->makeUser('auditor@test.vn', $auditorRole);

        $this->actingAs($auditor)->get(route('calendar.index'))->assertOk();
        $this->actingAs($auditor)->get(route('dashboard'))->assertForbidden();

        $this->actingAs($this->admin)->put(route('admin.roles.update', $auditorRole), [
            'permissions' => ['calendar.view', 'dashboard.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $auditor->unsetRelation('role');

        $this->actingAs($auditor)->get(route('dashboard'))->assertOk();
    }

    public function test_approval_queue_requires_an_approval_permission(): void
    {
        $otRole = $this->createRoleWithPermissions('ot_approver', 'Duyệt OT', ['ot.approve']);
        $otApprover = $this->makeUser('ot.approver@test.vn', $otRole);

        $this->actingAs($otApprover)->get(route('approvals.index'))->assertOk();

        $employee = $this->makeUser('nhanvien2@test.vn', Role::where('slug', 'employee')->firstOrFail());
        $this->actingAs($employee)->get(route('approvals.index'))->assertForbidden();
    }

    public function test_login_lands_on_first_accessible_page_without_dashboard_permission(): void
    {
        $role = $this->createRoleWithPermissions('calendar_only', 'Chỉ xem lịch', ['calendar.view']);
        $this->makeUser('calendar.only@test.vn', $role);

        $this->post(route('login.attempt'), [
            'email'    => 'calendar.only@test.vn',
            'password' => 'password123',
        ])->assertRedirect(route('calendar.index'));
    }

    public function test_staff_permissions_are_split_by_specific_action(): void
    {
        $employeeRole = Role::where('slug', 'employee')->firstOrFail();
        $viewerRole = $this->createRoleWithPermissions('staff_viewer', 'Chỉ xem nhân sự', ['user.view']);
        $viewer = $this->makeUser('staff.viewer@test.vn', $viewerRole);

        $this->actingAs($viewer)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.users.toggle', $this->admin))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.users.destroy', $this->admin))->assertForbidden();

        $editorRole = $this->createRoleWithPermissions('staff_editor', 'Sửa nhân sự', ['user.view', 'user.update']);
        $editor = $this->makeUser('staff.editor@test.vn', $editorRole);
        $target = $this->makeUser('staff.target@test.vn', $employeeRole);

        $this->actingAs($editor)->get(route('admin.users.edit', $target))->assertOk();
        $this->actingAs($editor)->post(route('admin.users.toggle', $target))->assertForbidden();
        $this->actingAs($editor)->delete(route('admin.users.destroy', $target))->assertForbidden();
    }

    public function test_catalog_permissions_are_split_by_specific_action(): void
    {
        $viewerRole = $this->createRoleWithPermissions('catalog_viewer', 'Chỉ xem danh mục', ['leavetype.view', 'holiday.view']);
        $viewer = $this->makeUser('catalog.viewer@test.vn', $viewerRole);

        $leaveType = \App\Models\LeaveType::create([
            'code'           => 'AL',
            'name'           => 'Phép năm',
            'color'          => '#2f6bff',
            'default_days'   => 12,
            'deduct_balance' => true,
            'is_paid'        => true,
            'active'         => true,
        ]);

        $this->actingAs($viewer)->get(route('admin.leave-types.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.holidays.index'))->assertOk();
        $this->actingAs($viewer)->post(route('admin.leave-types.store'), [])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.leave-types.destroy', $leaveType))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.holidays.store'), [])->assertForbidden();
    }

    private function createRoleWithPermissions(string $slug, string $name, array $permissionSlugs): Role
    {
        $role = Role::create([
            'slug' => $slug,
            'name' => $name,
        ]);

        $ids = collect($permissionSlugs)->map(fn (string $permission) => Permission::firstOrCreate(
            ['slug' => $permission],
            ['name' => $permission, 'group' => 'Test']
        )->id);

        $role->permissions()->sync($ids);

        return $role;
    }

    private function makeUser(string $email, Role $role): User
    {
        return User::create([
            'name'     => 'Người dùng ' . $email,
            'email'    => $email,
            'password' => 'password123',
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }
}
