<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions')->orderByDesc('level')->get();
        $groups = config('permissions.groups');
        $editableRoles = $roles->where('slug', '!=', 'admin')->values();

        $counts = [
            'roles'       => $roles->count(),
            'permissions' => collect($groups)->flatten()->count(),
            'nonAdmin'    => $editableRoles->count(),
        ];

        return view('admin.roles.index', compact('roles', 'groups', 'editableRoles', 'counts'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === 'admin', 403, 'Vai trò Quản trị hệ thống luôn có toàn quyền, không thể chỉnh sửa.');

        $data = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);

        $permissions = \App\Models\Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id');
        $role->permissions()->sync($permissions);

        AuditLogger::log('role.update', 'roles', "Cập nhật ma trận quyền cho vai trò {$role->name}", $role, [
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('admin.roles.index')
            ->with('success', "Đã cập nhật quyền cho vai trò {$role->name}.");
    }
}
