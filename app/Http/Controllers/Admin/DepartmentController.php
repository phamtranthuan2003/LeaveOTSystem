<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::with(['manager', 'parent'])
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $grouped = $departments->groupBy(fn (Department $department) => $department->parent_id ?? 'root');
        $tree = $this->buildTree($grouped, 'root', 0);

        $stats = [
            'total'  => $departments->count(),
            'level2' => $departments->where('level', 2)->count(),
            'level3' => $departments->where('level', 3)->count(),
            'active' => $departments->where('active', true)->count(),
        ];

        $unassigned = User::active()->whereNull('department_id')->count();

        return view('admin.departments.index', compact('departments', 'tree', 'stats', 'unassigned'));
    }

    public function create(): View
    {
        $parents = Department::where('active', true)->orderBy('level')->orderBy('name')->get();
        $managers = $this->managerOptions();

        return view('admin.departments.create', compact('parents', 'managers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $parent = $data['parent_id'] ? Department::find($data['parent_id']) : null;
        $data['level'] = $parent ? $parent->level + 1 : 1;

        $department = Department::create($data);

        AuditLogger::log('department.create', 'departments', "Tạo phòng ban {$department->name}", $department);

        return redirect()->route('admin.departments.index')->with('success', "Đã tạo {$department->name}.");
    }

    public function edit(Department $department): View
    {
        $parents = Department::where('active', true)
            ->where('id', '!=', $department->id)
            ->whereNotIn('id', $department->descendantIds())
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $managers = $this->managerOptions();

        return view('admin.departments.edit', compact('department', 'parents', 'managers'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $this->validateData($request, $department);

        if (!empty($data['parent_id'])) {
            if ((int) $data['parent_id'] === $department->id || in_array((int) $data['parent_id'], $department->descendantIds(), true)) {
                return back()->withInput()->withErrors(['parent_id' => 'Không thể chọn phòng ban con hoặc chính nó làm cấp trên.']);
            }
        }

        $parent = $data['parent_id'] ? Department::find($data['parent_id']) : null;
        $data['level'] = $parent ? $parent->level + 1 : 1;

        $department->update($data);
        $this->recalculateLevels($department);

        AuditLogger::log('department.update', 'departments', "Cập nhật phòng ban {$department->name}", $department);

        return redirect()->route('admin.departments.index')->with('success', "Đã cập nhật {$department->name}.");
    }

    public function toggle(Department $department): RedirectResponse
    {
        if ($department->active) {
            if ($department->children()->where('active', true)->exists()) {
                return back()->with('error', 'Không thể ngưng hoạt động khi còn phòng ban trực thuộc đang hoạt động.');
            }

            if ($department->users()->where('status', 'active')->exists()) {
                return back()->with('error', 'Không thể ngưng hoạt động khi còn nhân sự đang làm việc trong phòng ban.');
            }
        }

        $department->update(['active' => !$department->active]);

        AuditLogger::log('department.toggle', 'departments', "Đổi trạng thái phòng ban {$department->name}", $department);

        return back()->with('success', 'Đã cập nhật trạng thái phòng ban.');
    }

    protected function validateData(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'code'        => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('departments', 'code')->ignore($department?->id)],
            'name'        => ['required', 'string', 'max:255'],
            'parent_id'   => ['nullable', 'exists:departments,id'],
            'manager_id'  => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active'      => ['nullable', 'boolean'],
        ], [
            'code.alpha_dash' => 'Mã phòng ban chỉ gồm chữ, số, gạch ngang hoặc gạch dưới.',
            'code.unique'     => 'Mã phòng ban đã tồn tại.',
        ]) + [
            'active'     => $request->boolean('active'),
            'sort_order' => (int) ($request->input('sort_order') ?? 0),
        ];
    }

    protected function recalculateLevels(Department $department): void
    {
        foreach ($department->children as $child) {
            $child->update(['level' => $department->level + 1]);
            $this->recalculateLevels($child);
        }
    }

    protected function managerOptions()
    {
        return User::active()
            ->whereHas('role', fn ($q) => $q->where('slug', '!=', 'admin'))
            ->orderBy('name')
            ->get();
    }

    protected function buildTree($grouped, $parentId, int $depth): array
    {
        $rows = [];

        foreach ($grouped->get($parentId, collect()) as $department) {
            $rows[] = ['department' => $department, 'depth' => $depth];
            $rows = array_merge($rows, $this->buildTree($grouped, $department->id, $depth + 1));
        }

        return $rows;
    }
}
