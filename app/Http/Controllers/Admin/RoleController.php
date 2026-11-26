<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('id')->get();
        $groups = config('permissions.groups');
        $editableRoles = $roles->where('slug', '!=', 'admin')->values();

        $counts = [
            'roles' => $roles->count(),
            'permissions' => collect($groups)->flatten()->count(),
            'nonAdmin' => $editableRoles->count(),
        ];

        return view('admin.roles.index', compact('roles', 'groups', 'editableRoles', 'counts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ], [
            'name.unique' => 'Tên vai trò này đã tồn tại.',
        ]);

        $role = Role::create([
            'slug' => $this->uniqueSlug($data['name']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync(
            Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id')
        );

        AuditLogger::log('role.create', 'roles', "Tạo vai trò {$role->name}", $role, [
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('admin.roles.index')
            ->with('success', "Đã tạo vai trò {$role->name}. Có thể chỉnh thêm quyền trong ma trận bên dưới.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === 'admin', 403, 'Vai trò Quản trị hệ thống luôn có toàn quyền, không thể chỉnh sửa.');

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);

        $permissions = Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id');
        $role->permissions()->sync($permissions);

        AuditLogger::log('role.update', 'roles', "Cập nhật ma trận quyền cho vai trò {$role->name}", $role, [
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('admin.roles.index')
            ->with('success', "Đã cập nhật quyền cho vai trò {$role->name}.");
    }

    public function updateInfo(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === 'admin', 403, 'Vai trò Quản trị hệ thống luôn có toàn quyền, không thể chỉnh sửa.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Tên vai trò này đã tồn tại.',
        ]);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        AuditLogger::log('role.update', 'roles', "Cập nhật thông tin vai trò {$role->name}", $role, [
            'name' => $role->name,
            'description' => $role->description,
        ]);

        return redirect()->route('admin.roles.index')
            ->with('success', "Đã cập nhật vai trò {$role->name}.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->slug === 'admin', 403, 'Không thể xoá vai trò Quản trị hệ thống.');

        if ($role->users()->exists()) {
            return back()->with('error', 'Không thể xoá vai trò đang có tài khoản sử dụng. Hãy chuyển các tài khoản sang vai trò khác trước.');
        }

        $name = $role->name;
        $role->delete();

        AuditLogger::log('role.delete', 'roles', "Xoá vai trò {$name}");

        return back()->with('success', "Đã xoá vai trò {$name}.");
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';

        if ($base === 'admin') {
            $base = 'admin-role';
        }

        $slug = $base;
        $suffix = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
