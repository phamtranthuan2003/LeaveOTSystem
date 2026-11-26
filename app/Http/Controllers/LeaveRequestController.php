<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = $request->input('scope', 'mine');

        if ($scope === 'all' && ! $user->hasPermission('leave.view.all')) {
            $scope = 'mine';
        }

        $year = $request->input('year', (string) now()->year);

        $base = LeaveRequest::query();
        $base->when($scope === 'mine', fn ($q) => $q->where('user_id', $user->id));
        $base->when($year !== 'all', fn ($q) => $q->whereYear('start_date', (int) $year));

        $stats = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $query = (clone $base)
            ->with(['user.department', 'leaveType', 'approvals.approver'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('leave_type_id'), fn ($q) => $q->where('leave_type_id', $request->integer('leave_type_id')))
            ->when($request->filled('month'), fn ($q) => $q->whereMonth('start_date', $request->integer('month')))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    $keyword = trim((string) $request->input('q'));
                    $w->where('code', 'like', "%{$keyword}%")
                        ->orWhere('reason', 'like', "%{$keyword}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$keyword}%")
                            ->orWhere('employee_code', 'like', "%{$keyword}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $leaveTypes = LeaveType::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('leave.index', compact('user', 'scope', 'year', 'stats', 'query', 'leaveTypes', 'departments'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $year = now()->year;

        $leaveTypes = LeaveType::where('active', true)->orderBy('id')->get();
        $balances = $leaveTypes->mapWithKeys(fn (LeaveType $type) => [$type->id => $user->leaveBalance($type->id, $year)]);

        $teamIds = $user->teamIds();
        $handovers = User::active()
            ->where('id', '!=', $user->id)
            ->where(function ($q) use ($teamIds, $user) {
                $q->whereIn('id', $teamIds)
                    ->orWhere('department_id', $user->department_id);
            })
            ->orderBy('name')
            ->get();

        if ($handovers->isEmpty()) {
            $handovers = User::active()->where('id', '!=', $user->id)->orderBy('name')->limit(50)->get();
        }

        $holidays = Holiday::whereYear('holiday_date', $year)->orderBy('holiday_date')->get();
        $approvalChain = app(ApprovalService::class)->buildChain($user, 'leave');

        return view('leave.create', compact('user', 'leaveTypes', 'balances', 'handovers', 'holidays', 'year', 'approvalChain'));
    }

    public function store(Request $request, LeaveCalculator $calculator, ApprovalService $approvals): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'day_type' => ['required', 'in:full,morning,afternoon'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'handover_to' => ['nullable', 'exists:users,id'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'start_date.after_or_equal' => 'Ngày bắt đầu nghỉ không được ở quá khứ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'reason.min' => 'Vui lòng nhập lý do nghỉ tối thiểu 10 ký tự.',
            'attachment.mimes' => 'Tệp đính kèm phải là PDF, JPG, PNG, DOC hoặc DOCX.',
            'attachment.max' => 'Tệp đính kèm tối đa 5MB.',
        ]);

        $leaveType = LeaveType::where('active', true)->find($data['leave_type_id']);

        if (! $leaveType) {
            return back()->withInput()->withErrors(['leave_type_id' => 'Loại nghỉ phép không còn hiệu lực.']);
        }

        if ($data['day_type'] !== 'full' && $data['start_date'] !== $data['end_date']) {
            return back()->withInput()->withErrors(['day_type' => 'Nghỉ nửa ngày chỉ áp dụng cho đơn trong cùng một ngày.']);
        }

        if (($data['handover_to'] ?? null) == $user->id) {
            return back()->withInput()->withErrors(['handover_to' => 'Không thể chọn chính mình làm người bàn giao.']);
        }

        if ($leaveType->requires_attachment && ! $request->hasFile('attachment')) {
            return back()->withInput()->withErrors(['attachment' => 'Loại nghỉ này yêu cầu tệp đính kèm (ví dụ giấy khám bệnh).']);
        }

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $totalDays = $calculator->workingDays($start, $end, $data['day_type']);

        if ($totalDays <= 0) {
            return back()->withInput()->withErrors(['start_date' => 'Khoảng thời gian đã chọn không có ngày làm việc (Chủ nhật, ngày lễ hoặc buổi chiều Thứ 7).']);
        }

        $overlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            return back()->withInput()->withErrors(['start_date' => 'Bạn đã có đơn nghỉ trùng khoảng thời gian này.']);
        }

        if ($leaveType->deduct_balance) {
            $balance = $user->leaveBalance($leaveType->id, $start->year);
            $remaining = $balance ? $balance->remaining() : (float) $leaveType->default_days;

            if ($totalDays > $remaining) {
                return back()->withInput()->withErrors([
                    'leave_type_id' => "Quỹ phép không đủ: còn {$remaining} ngày, đơn cần {$totalDays} ngày.",
                ]);
            }
        }

        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = now()->format('YmdHis').'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('uploads/attachments'), $filename);
            $attachmentPath = 'uploads/attachments/'.$filename;
        }

        try {
            $leave = DB::transaction(function () use ($data, $user, $totalDays, $attachmentPath) {
                return LeaveRequest::create([
                    'code' => LeaveRequest::nextCode(),
                    'user_id' => $user->id,
                    'leave_type_id' => $data['leave_type_id'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'day_type' => $data['day_type'],
                    'total_days' => $totalDays,
                    'reason' => $data['reason'],
                    'handover_to' => $data['handover_to'] ?? null,
                    'attachment' => $attachmentPath,
                    'status' => 'pending',
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['leave_type_id' => 'Không thể tạo đơn nghỉ. Vui lòng thử lại.']);
        }

        try {
            $steps = $approvals->submit($leave, 'leave');
        } catch (\RuntimeException $e) {
            if ($attachmentPath && file_exists(public_path($attachmentPath))) {
                @unlink(public_path($attachmentPath));
            }
            $leave->delete();

            return back()->withInput()->withErrors(['approval' => $e->getMessage()]);
        }

        return redirect()->route('leave.show', $leave)
            ->with('success', "Đã gửi đơn nghỉ phép {$leave->code} qua {$steps} bước duyệt.");
    }

    public function show(Request $request, LeaveRequest $leave, ApprovalService $approvals): View
    {
        $user = $request->user();
        $this->authorizeView($user, $leave);

        $leave->load(['user.department', 'user.manager', 'leaveType', 'handover', 'approvals.approver.role', 'approvals.approver.department', 'approvals.actor']);

        $isAdmin = $user->isRole('admin');
        $currentApproval = $leave->approvals->firstWhere('status', 'pending');

        $canApprove = $leave->status === 'pending'
            && $currentApproval
            && ($isAdmin || $currentApproval->approver_id === $user->id)
            && $user->hasPermission('leave.approve');

        $canApproveAll = $currentApproval !== null && $approvals->canApproveAll($leave, $user);

        $canCancel = $leave->user_id === $user->id
            && $user->hasPermission('leave.cancel.own')
            && in_array($leave->status, ['pending', 'approved'], true)
            && ($leave->status === 'pending' || $leave->start_date->gte(today()));

        return view('leave.show', compact('leave', 'currentApproval', 'canApprove', 'canApproveAll', 'canCancel'));
    }

    public function cancel(Request $request, LeaveRequest $leave, ApprovalService $approvals): RedirectResponse
    {
        $result = $approvals->cancel($leave, $request->user());

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Request $request, LeaveRequest $leave, ApprovalService $approvals): RedirectResponse
    {
        $code = $leave->code;
        $owner = $leave->user?->name;
        $attachment = $leave->attachment;

        $approvals->deleteLeaveRequest($leave);

        if ($attachment && file_exists(public_path($attachment))) {
            @unlink(public_path($attachment));
        }

        AuditLogger::log('leave.delete', 'leave', "Xoá đơn nghỉ {$code} của {$owner}");

        $scope = $request->input('scope') === 'mine' ? 'mine' : 'all';

        return redirect()->route('leave.index', ['scope' => $scope])
            ->with('success', "Đã xoá đơn nghỉ {$code}.");
    }

    protected function authorizeView(User $user, LeaveRequest $leave): void
    {
        if ($user->id === $leave->user_id || $user->hasPermission('leave.view.all')) {
            return;
        }

        if ($leave->approvals()->where('approver_id', $user->id)->exists()) {
            return;
        }

        if ($user->hasPermission('leave.approve') && in_array($leave->user_id, $user->teamIds(), true)) {
            return;
        }

        abort(403, 'Bạn không có quyền xem đơn nghỉ phép này.');
    }
}
