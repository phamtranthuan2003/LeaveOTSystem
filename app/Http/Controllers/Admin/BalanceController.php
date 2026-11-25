<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BalanceController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        if ($year < now()->year - 3 || $year > now()->year + 1) {
            $year = now()->year;
        }

        $leaveTypes = LeaveType::where('deduct_balance', true)->orderBy('id')->get();
        $annual = $leaveTypes->firstWhere('code', 'AL') ?? $leaveTypes->first();
        $leaveTypeId = (int) $request->input('leave_type_id', $annual?->id);
        $selectedType = $leaveTypes->firstWhere('id', $leaveTypeId) ?? $annual;

        $userQuery = User::query()
            ->where('status', 'active')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->input('q'));
                $q->where(fn ($w) => $w->where('name', 'like', "%{$keyword}%")
                    ->orWhere('employee_code', 'like', "%{$keyword}%"));
            });

        $allIds = (clone $userQuery)->pluck('id');

        $allBalances = $selectedType
            ? LeaveBalance::where('year', $year)
                ->where('leave_type_id', $selectedType->id)
                ->whereIn('user_id', $allIds)
                ->get()
                ->keyBy('user_id')
            : collect();

        $summary = [
            'entitled'  => (float) $allBalances->sum(fn ($b) => $b->totalEntitled()),
            'used'      => (float) $allBalances->sum('used_days'),
            'remaining' => (float) $allBalances->sum(fn ($b) => $b->remaining()),
        ];

        $users = $userQuery->with(['department', 'role'])->orderBy('name')->paginate(15)->withQueryString();

        $pageBalances = $selectedType
            ? LeaveBalance::where('year', $year)
                ->where('leave_type_id', $selectedType->id)
                ->whereIn('user_id', $users->pluck('id'))
                ->get()
                ->keyBy('user_id')
            : collect();

        $departments = Department::orderBy('name')->get();
        $years = range(now()->year + 1, now()->year - 3);

        return view('admin.balances.index', compact(
            'year', 'years', 'leaveTypes', 'selectedType', 'users', 'pageBalances',
            'summary', 'departments'
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'year'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:60'],
            'carried_over'  => ['required', 'numeric', 'min:0', 'max:60'],
            'used_days'     => ['required', 'numeric', 'min:0', 'max:120'],
            'note'          => ['nullable', 'string', 'max:255'],
        ], [
            'entitled_days.required' => 'Vui lòng nhập số ngày được hưởng.',
        ]);

        $balance = LeaveBalance::updateOrCreate(
            [
                'user_id'       => $user->id,
                'year'          => $data['year'],
                'leave_type_id' => $data['leave_type_id'],
            ],
            [
                'entitled_days' => $data['entitled_days'],
                'carried_over'  => $data['carried_over'],
                'used_days'     => $data['used_days'],
                'note'          => $data['note'] ?? null,
            ]
        );

        AuditLogger::log('balance.update', 'balances', "Điều chỉnh quỹ phép {$user->name} năm {$data['year']}", $balance, [
            'entitled_days' => $balance->entitled_days,
            'used_days'     => $balance->used_days,
        ]);

        return back()->with('success', "Đã cập nhật quỹ phép cho {$user->name}.");
    }

    public function generate(Request $request, BalanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'year'       => ['required', 'integer', 'min:2000', 'max:2100'],
            'carry_over' => ['nullable', 'boolean'],
        ]);

        $created = $service->generateYear((int) $data['year'], $request->boolean('carry_over'));

        AuditLogger::log('balance.generate', 'balances', "Khởi tạo quỹ phép năm {$data['year']} cho {$created} bản ghi");

        return back()->with('success', $created > 0
            ? "Đã khởi tạo {$created} quỹ phép cho năm {$data['year']}."
            : 'Tất cả nhân sự đã có quỹ phép cho năm này.');
    }
}
