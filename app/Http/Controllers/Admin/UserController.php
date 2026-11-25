<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        $base = User::query();

        if (!$actor->isRole('admin')) {
            $base->whereHas('role', fn ($q) => $q->where('slug', '!=', 'admin'));
        }

        $stats = [
            'total'    => (clone $base)->count(),
            'active'   => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
        ];

        $users = (clone $base)
            ->with(['role', 'department', 'manager'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->input('q'));
                $q->where(function ($w) use ($keyword) {
                    $w->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('employee_code', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->integer('role_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();
        $roles = $this->assignableRoles($actor);
        $allRoles = Role::orderBy('id')->get();

        return view('admin.users.index', compact('actor', 'users', 'stats', 'departments', 'roles', 'allRoles'));
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        $roles = $this->assignableRoles($actor);
        $departments = Department::where('active', true)->orderBy('name')->get();
        $managers = $this->managerOptions();
        $nextCode = $this->nextEmployeeCode();
        $defaultRoleId = ($roles->firstWhere('slug', 'employee') ?? $roles->first(fn (Role $role) => $role->slug !== 'admin'))?->id;

        return view('admin.users.create', compact('actor', 'roles', 'departments', 'managers', 'nextCode', 'defaultRoleId'));
    }

    public function store(Request $request, BalanceService $balances): RedirectResponse
    {
        $actor = $request->user();
        $roles = $this->assignableRoles($actor);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'employee_code' => ['nullable', 'string', 'max:30', 'unique:users,employee_code'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'role_id'       => ['required', Rule::in($roles->pluck('id')->all())],
            'department_id' => ['nullable', 'exists:departments,id'],
            'manager_id'    => ['nullable', 'exists:users,id'],
            'position'      => ['nullable', 'string', 'max:150'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'join_date'     => ['nullable', 'date'],
            'status'        => ['required', 'in:active,inactive'],
        ], [
            'role_id.in'        => 'Bạn không được phép gán vai trò này.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'email.unique'      => 'Email này đã được sử dụng.',
        ]);

        $data['employee_code'] = ($data['employee_code'] ?? null) ?: $this->nextEmployeeCode();

        $user = User::create($data);
        $balances->ensureForUser($user, now()->year);

        AuditLogger::log('user.create', 'users', "Tạo tài khoản {$user->name} ({$user->employee_code})", $user);

        return redirect()->route('admin.users.index')->with('success', "Đã tạo tài khoản cho {$user->name}.");
    }

    public function edit(Request $request, User $user): View
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        $roles = $this->assignableRoles($actor);
        $departments = Department::orderBy('name')->get();

        $managers = User::active()
            ->where('id', '!=', $user->id)
            ->whereNotIn('id', $user->teamIds())
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'admin'))
            ->orderBy('name')
            ->get();

        return view('admin.users.edit', compact('actor', 'user', 'roles', 'departments', 'managers'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        $roles = $this->assignableRoles($actor);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('users', 'employee_code')->ignore($user->id)],
            'password'      => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id'       => ['required', Rule::in($roles->pluck('id')->all())],
            'department_id' => ['nullable', 'exists:departments,id'],
            'manager_id'    => ['nullable', 'exists:users,id'],
            'position'      => ['nullable', 'string', 'max:150'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'join_date'     => ['nullable', 'date'],
            'status'        => ['required', 'in:active,inactive'],
        ], [
            'role_id.in' => 'Bạn không được phép gán vai trò này.',
        ]);

        if ($user->id === $actor->id && $data['status'] === 'inactive') {
            return back()->withInput()->withErrors(['status' => 'Bạn không thể tự khoá tài khoản của mình.']);
        }

        if (!empty($data['manager_id'])) {
            if ((int) $data['manager_id'] === $user->id || in_array((int) $data['manager_id'], $user->teamIds(), true)) {
                return back()->withInput()->withErrors(['manager_id' => 'Người quản lý không hợp lệ (gây vòng lặp cấp bậc).']);
            }
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        AuditLogger::log('user.update', 'users', "Cập nhật tài khoản {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('success', "Đã cập nhật tài khoản {$user->name}.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->id === $actor->id) {
            return back()->with('error', 'Bạn không thể tự khoá tài khoản của mình.');
        }

        if ($user->status === 'active' && $user->isRole('admin')) {
            $activeAdmins = User::active()->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->count();
            if ($activeAdmins <= 1) {
                return back()->with('error', 'Không thể khoá quản trị viên hoạt động cuối cùng.');
            }
        }

        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);

        AuditLogger::log('user.toggle', 'users', "Đổi trạng thái tài khoản {$user->name} thành {$user->status}", $user);

        return back()->with('success', "Đã " . ($user->status === 'active' ? 'mở khoá' : 'khoá') . " tài khoản {$user->name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        if ($user->id === $actor->id) {
            return back()->with('error', 'Bạn không thể xoá tài khoản của chính mình.');
        }

        if ($user->status === 'active' && $user->isRole('admin')) {
            $activeAdmins = User::active()->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->count();
            if ($activeAdmins <= 1) {
                return back()->with('error', 'Không thể xoá quản trị viên hoạt động cuối cùng.');
            }
        }

        if ($user->leaveRequests()->exists() || $user->overtimeRequests()->exists()) {
            return back()->with('error', "Không thể xoá {$user->name} vì đã phát sinh đơn nghỉ/OT. Hãy khoá tài khoản thay vì xoá.");
        }

        if ($user->approvals()->exists()) {
            return back()->with('error', "Không thể xoá {$user->name} vì đã tham gia luồng duyệt đơn của người khác. Hãy khoá tài khoản thay vì xoá.");
        }

        Department::where('manager_id', $user->id)->update(['manager_id' => null]);

        $name = $user->name;
        $user->delete();

        AuditLogger::log('user.delete', 'users', "Xoá tài khoản {$name}");

        return back()->with('success', "Đã xoá tài khoản {$name}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'new_password.required'  => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min'       => 'Mật khẩu tối thiểu 8 ký tự.',
            'new_password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $user->update(['password' => $data['new_password']]);

        AuditLogger::log('user.reset_password', 'users', "Đặt lại mật khẩu cho {$user->name}", $user);

        return back()->with('success', "Đã đặt lại mật khẩu cho {$user->name}.");
    }

    protected function assignableRoles(User $actor): Collection
    {
        $roles = Role::orderBy('id')->get();

        if ($actor->isRole('admin')) {
            return $roles;
        }

        return $roles->filter(fn (Role $role) => $role->slug !== 'admin')->values();
    }

    protected function authorizeTarget(User $actor, User $target): void
    {
        if ($actor->isRole('admin')) {
            return;
        }

        if ($target->isRole('admin')) {
            abort(403, 'Chỉ quản trị hệ thống mới có thể thao tác trên tài khoản admin.');
        }
    }

    protected function managerOptions(): Collection
    {
        return User::active()
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'admin'))
            ->orderBy('name')
            ->get();
    }

    protected function nextEmployeeCode(): string
    {
        $number = User::count() + 1;

        do {
            $code = 'EVG-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (User::where('employee_code', $code)->exists());

        return $code;
    }
}
