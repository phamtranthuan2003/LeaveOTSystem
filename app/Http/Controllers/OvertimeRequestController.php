<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Holiday;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OvertimeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = $request->input('scope', 'mine');

        if ($scope === 'all' && !$user->hasPermission('ot.view.all')) {
            $scope = 'mine';
        }

        $year = $request->input('year', (string) now()->year);

        $base = OvertimeRequest::query();
        $base->when($scope === 'mine', fn ($q) => $q->where('user_id', $user->id));
        $base->when($year !== 'all', fn ($q) => $q->whereYear('ot_date', (int) $year));

        $stats = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalHours = (float) (clone $base)->where('status', 'approved')->sum('hours');

        $query = (clone $base)
            ->with(['user.department', 'approvals.approver'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('ot_type'), fn ($q) => $q->where('ot_type', $request->input('ot_type')))
            ->when($request->filled('month'), fn ($q) => $q->whereMonth('ot_date', $request->integer('month')))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(function ($w) use ($request) {
                    $keyword = trim((string) $request->input('q'));
                    $w->where('code', 'like', "%{$keyword}%")
                        ->orWhere('project', 'like', "%{$keyword}%")
                        ->orWhere('reason', 'like', "%{$keyword}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$keyword}%")
                            ->orWhere('employee_code', 'like', "%{$keyword}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();

        return view('overtime.index', compact('user', 'scope', 'year', 'stats', 'totalHours', 'query', 'departments'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $holidays = Holiday::whereDate('holiday_date', '>=', now()->startOfYear())
            ->whereDate('holiday_date', '<=', now()->addYear())
            ->orderBy('holiday_date')
            ->get();

        $rates = [
            'weekday' => (float) setting('ot_rate_weekday', 1.5),
            'weekend' => (float) setting('ot_rate_weekend', 2.0),
            'holiday' => (float) setting('ot_rate_holiday', 3.0),
        ];

        $approvalChain = app(ApprovalService::class)->buildChain($user, 'overtime');

        return view('overtime.create', compact('user', 'holidays', 'rates', 'approvalChain'));
    }

    public function store(Request $request, LeaveCalculator $calculator, ApprovalService $approvals): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'ot_date'    => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'ot_type'    => ['required', 'in:weekday,weekend,holiday'],
            'project'    => ['nullable', 'string', 'max:150'],
            'reason'     => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'ot_date.after_or_equal' => 'Ngày đăng ký OT không được ở quá khứ.',
            'end_time.after'         => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'reason.min'             => 'Vui lòng mô tả nội dung công việc tối thiểu 10 ký tự.',
        ]);

        $hours = $calculator->otHours($data['start_time'], $data['end_time']);

        if ($hours <= 0) {
            return back()->withInput()->withErrors(['end_time' => 'Khoảng thời gian OT không hợp lệ.']);
        }

        if ($hours > 16) {
            return back()->withInput()->withErrors(['end_time' => 'Một đơn OT không được vượt quá 16 giờ.']);
        }

        $startTime = Carbon::parse($data['start_time'])->format('H:i:s');
        $endTime = Carbon::parse($data['end_time'])->format('H:i:s');

        $overlap = OvertimeRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('ot_date', $data['ot_date'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($overlap) {
            return back()->withInput()->withErrors(['start_time' => 'Bạn đã có đơn OT trùng khung giờ này.']);
        }

        $ot = DB::transaction(function () use ($data, $user, $hours, $startTime, $endTime, $calculator) {
            return OvertimeRequest::create([
                'code'       => OvertimeRequest::nextCode(),
                'user_id'    => $user->id,
                'ot_date'    => $data['ot_date'],
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'hours'      => $hours,
                'ot_type'    => $data['ot_type'],
                'multiplier' => $calculator->otRate($data['ot_type']),
                'project'    => $data['project'] ?? null,
                'reason'     => $data['reason'],
                'status'     => 'pending',
            ]);
        });

        try {
            $steps = $approvals->submit($ot, 'overtime');
        } catch (\RuntimeException $e) {
            $ot->delete();

            return back()->withInput()->withErrors(['approval' => $e->getMessage()]);
        }

        return redirect()->route('overtime.show', $ot)
            ->with('success', "Đã gửi đơn OT {$ot->code} qua {$steps} bước duyệt.");
    }

    public function show(Request $request, OvertimeRequest $overtime): View
    {
        $user = $request->user();
        $this->authorizeView($user, $overtime);

        $overtime->load(['user.department', 'user.manager', 'approvals.approver.role', 'approvals.approver.department', 'approvals.actor']);

        $isAdmin = $user->isRole('admin');
        $currentApproval = $overtime->approvals->firstWhere('status', 'pending');

        $canApprove = $overtime->status === 'pending'
            && $currentApproval
            && ($isAdmin || $currentApproval->approver_id === $user->id)
            && $user->hasPermission('ot.approve');

        $canApproveAll = $isAdmin && $overtime->status === 'pending' && $currentApproval;

        $canCancel = $overtime->user_id === $user->id
            && $user->hasPermission('ot.create')
            && in_array($overtime->status, ['pending', 'approved'], true)
            && ($overtime->status === 'pending' || $overtime->ot_date->gte(today()));

        return view('overtime.show', compact('overtime', 'currentApproval', 'canApprove', 'canApproveAll', 'canCancel'));
    }

    public function cancel(Request $request, OvertimeRequest $overtime, ApprovalService $approvals): RedirectResponse
    {
        $result = $approvals->cancel($overtime, $request->user());

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    protected function authorizeView(User $user, OvertimeRequest $overtime): void
    {
        if ($user->id === $overtime->user_id || $user->hasPermission('ot.view.all')) {
            return;
        }

        if ($overtime->approvals()->where('approver_id', $user->id)->exists()) {
            return;
        }

        if ($user->hasPermission('ot.approve') && in_array($overtime->user_id, $user->teamIds(), true)) {
            return;
        }

        abort(403, 'Bạn không có quyền xem đơn OT này.');
    }
}
