<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $groupedPermissions = config('permissions.groups');
        $permissionIds = [];

        foreach ($groupedPermissions as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                $permission = Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'group' => $group]
                );
                $permissionIds[$slug] = $permission->id;
            }
        }

        foreach (config('permissions.roles') as $slug => $roleData) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $roleData['name'],
                    'description' => $roleData['description'],
                    'level'       => $roleData['level'],
                ]
            );

            $defaults = $slug === 'admin'
                ? array_keys($permissionIds)
                : (config('permissions.defaults.' . $slug) ?? []);

            $ids = collect($defaults)->map(fn ($permission) => $permissionIds[$permission] ?? null)->filter()->all();

            $role->permissions()->sync($ids);
        }
    }
}
