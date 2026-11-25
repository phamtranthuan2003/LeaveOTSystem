<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(Request $request): View
    {
        $leaveTypes = LeaveType::withCount('leaveRequests')->orderBy('id')->get();
        $editing = $request->filled('edit') ? LeaveType::find($request->integer('edit')) : null;

        return view('admin.leave-types.index', compact('leaveTypes', 'editing'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $type = LeaveType::create($data);

        AuditLogger::log('leavetype.create', 'leave-types', "Tạo loại nghỉ {$type->name}", $type);

        return redirect()->route('admin.leave-types.index')->with('success', "Đã tạo loại nghỉ {$type->name}.");
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $data = $this->validated($request, $leaveType);

        $leaveType->update($data);

        AuditLogger::log('leavetype.update', 'leave-types', "Cập nhật loại nghỉ {$leaveType->name}", $leaveType);

        return redirect()->route('admin.leave-types.index')->with('success', "Đã cập nhật {$leaveType->name}.");
    }

    public function toggle(LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update(['active' => !$leaveType->active]);

        AuditLogger::log('leavetype.toggle', 'leave-types', "Đổi trạng thái loại nghỉ {$leaveType->name}", $leaveType);

        return back()->with('success', 'Đã cập nhật trạng thái loại nghỉ.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error', 'Không thể xoá loại nghỉ đã phát sinh đơn. Hãy ngưng áp dụng thay vì xoá.');
        }

        $name = $leaveType->name;
        $leaveType->delete();

        AuditLogger::log('leavetype.delete', 'leave-types', "Xoá loại nghỉ {$name}");

        return back()->with('success', "Đã xoá loại nghỉ {$name}.");
    }

    protected function validated(Request $request, ?LeaveType $leaveType = null): array
    {
        $data = $request->validate([
            'code'                => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('leave_types', 'code')->ignore($leaveType?->id)],
            'name'                => ['required', 'string', 'max:255'],
            'color'               => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'default_days'        => ['required', 'numeric', 'min:0', 'max:60'],
            'deduct_balance'      => ['nullable', 'boolean'],
            'requires_attachment' => ['nullable', 'boolean'],
            'is_paid'             => ['nullable', 'boolean'],
            'description'         => ['nullable', 'string', 'max:500'],
        ], [
            'code.unique' => 'Mã loại nghỉ đã tồn tại.',
            'color.regex' => 'Màu sắc phải ở định dạng #RRGGBB.',
        ]);

        return $data + [
            'deduct_balance'      => $request->boolean('deduct_balance'),
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_paid'             => $request->boolean('is_paid'),
            'active'              => $leaveType?->active ?? true,
        ];
    }
}
