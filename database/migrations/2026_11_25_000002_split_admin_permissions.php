<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $legacy = [
        'user.manage'        => ['user.view', 'user.create', 'user.update', 'user.toggle', 'user.reset_password', 'user.delete'],
        'department.manage'  => ['department.view', 'department.create', 'department.update', 'department.toggle', 'department.delete'],
        'leavetype.manage'   => ['leavetype.view', 'leavetype.create', 'leavetype.update', 'leavetype.toggle', 'leavetype.delete'],
        'holiday.manage'     => ['holiday.view', 'holiday.create', 'holiday.update', 'holiday.delete'],
        'balance.manage'     => ['balance.view.all', 'balance.generate', 'balance.update', 'balance.adjust'],
        'role.manage'        => ['role.view', 'role.create', 'role.update', 'role.delete'],
        'setting.manage'     => ['setting.view', 'setting.update'],
    ];

    private array $expanded = [
        'ot.create'   => ['ot.view.own', 'ot.cancel.own'],
        'ot.view.all' => ['ot.view.own'],
    ];

    public function up(): void
    {
        $this->syncConfigPermissions();

        $map = $this->legacy + $this->expanded;

        foreach ($map as $oldSlug => $newSlugs) {
            $oldId = Permission::where('slug', $oldSlug)->value('id');

            if (!$oldId) {
                continue;
            }

            $roleIds = DB::table('permission_role')->where('permission_id', $oldId)->pluck('role_id');
            $newIds = Permission::whereIn('slug', $newSlugs)->pluck('id');

            $this->attach($roleIds, $newIds);
        }

        $adminId = Role::where('slug', 'admin')->value('id');

        if ($adminId) {
            $this->attach([$adminId], Permission::pluck('id'));
        }

        Permission::whereIn('slug', array_keys($this->legacy))->delete();
    }

    public function down(): void
    {
        $legacyDefinitions = [
            'user.manage'       => ['Thêm / sửa / khoá tài khoản nhân viên', 'Tổ chức & nhân sự'],
            'department.manage' => ['Quản lý sơ đồ phòng ban', 'Tổ chức & nhân sự'],
            'leavetype.manage'  => ['Quản lý loại nghỉ phép', 'Danh mục'],
            'holiday.manage'    => ['Quản lý ngày lễ', 'Danh mục'],
            'balance.manage'    => ['Quản lý quỹ phép toàn công ty', 'Quỹ phép'],
            'role.manage'       => ['Quản lý vai trò & ma trận phân quyền', 'Hệ thống'],
            'setting.manage'    => ['Cấu hình hệ thống', 'Hệ thống'],
        ];

        foreach ($legacyDefinitions as $slug => [$name, $group]) {
            Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
        }

        $reverse = [
            'user.manage'       => ['user.create', 'user.update', 'user.toggle', 'user.reset_password', 'user.delete'],
            'department.manage' => ['department.create', 'department.update', 'department.toggle', 'department.delete'],
            'leavetype.manage'  => ['leavetype.create', 'leavetype.update', 'leavetype.toggle', 'leavetype.delete'],
            'holiday.manage'    => ['holiday.create', 'holiday.update', 'holiday.delete'],
            'balance.manage'    => ['balance.view.all', 'balance.generate', 'balance.update', 'balance.adjust'],
            'role.manage'       => ['role.view', 'role.create', 'role.update', 'role.delete'],
            'setting.manage'    => ['setting.view', 'setting.update'],
        ];

        foreach ($reverse as $legacySlug => $newSlugs) {
            $legacyId = Permission::where('slug', $legacySlug)->value('id');
            $roleIds = DB::table('permission_role')
                ->whereIn('permission_id', Permission::whereIn('slug', $newSlugs)->pluck('id'))
                ->pluck('role_id')
                ->unique();

            $this->attach($roleIds, collect([$legacyId]));
        }

        Permission::whereIn('slug', [
            'user.create', 'user.update', 'user.toggle', 'user.reset_password', 'user.delete',
            'department.view', 'department.create', 'department.update', 'department.toggle', 'department.delete',
            'leavetype.view', 'leavetype.create', 'leavetype.update', 'leavetype.toggle', 'leavetype.delete',
            'holiday.view', 'holiday.create', 'holiday.update', 'holiday.delete',
            'balance.view.all', 'balance.generate', 'balance.update', 'balance.adjust',
            'role.view', 'role.create', 'role.update', 'role.delete',
            'setting.view', 'setting.update',
            'ot.view.own', 'ot.cancel.own',
        ])->delete();
    }

    private function syncConfigPermissions(): void
    {
        foreach (config('permissions.groups') as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
            }
        }
    }

    private function attach(iterable $roleIds, iterable $permissionIds): void
    {
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id'       => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }
};
