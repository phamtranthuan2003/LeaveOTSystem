<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Approval;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $year = now()->year;
        $today = today();

        $seesAll = $user->hasPermission('leave.view.all') || $user->hasPermission('ot.view.all');
        $isApprover = $user->hasPermission('leave.approve') || $user->hasPermission('ot.approve');
        $teamIds = $user->teamIds();
        $scopeIds = $seesAll ? [] : array_values(array_unique(array_merge([$user->id], $teamIds)));

        $scopeLeave = fn ($query) => $query->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds));
        $scopeOt = fn ($query) => $query->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds));

        $pendingLeaveCount = (clone LeaveRequest::where('status', 'pending'))->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))->count();
        $pendingOtCount = (clone OvertimeRequest::where('status', 'pending'))->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))->count();

        $onLeaveToday = LeaveRequest::with(['user.department', 'leaveType'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))
            ->get();

        $otHoursMonth = (float) OvertimeRequest::where('status', 'approved')
            ->when($seesAll, fn ($q) => $q, fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('ot_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('hours');

        $myPendingCount = LeaveRequest::where('user_id', $user->id)->where('status', 'pending')->count()
            + OvertimeRequest::where('user_id', $user->id)->where('status', 'pending')->count();

        $annualType = LeaveType::where('code', 'AL')->first();
        $annualBalance = $annualType ? $user->leaveBalance($annualType->id, $year) : null;
        $annualRemaining = $annualBalance ? $annualBalance->remaining() : ($annualType ? (float) setting('annual_leave_default', 12) : 0);

        $cards = $seesAll
            ? [
                [
                    'icon' => 'users', 'tone' => 'primary', 'label' => 'Nhân sự đang hoạt động',
                    'value' => User::active()->count(), 'suffix' => 'người',
                    'hint' => 'Trên toàn hệ thống', 'url' => route('admin.users.index'),
                ],
                [
                    'icon' => 'inbox', 'tone' => 'warning', 'label' => 'Đơn đang chờ duyệt',
                    'value' => $pendingLeaveCount + $pendingOtCount, 'suffix' => 'đơn',
                    'hint' => 'Nghỉ phép và OT', 'url' => $isApprover ? route('approvals.index') : route('reports.index'),
                ],
                [
                    'icon' => 'palm', 'tone' => 'success', 'label' => 'Đang nghỉ hôm nay',
                    'value' => $onLeaveToday->count(), 'suffix' => 'nhân sự',
                    'hint' => $today->format('d/m/Y'), 'url' => route('calendar.index'),
                ],
                [
                    'icon' => 'clock', 'tone' => 'info', 'label' => 'Giờ OT tháng này',
                    'value' => number_format($otHoursMonth, ($otHoursMonth == floor($otHoursMonth) ? 0 : 1), ',', '.'),
                    'suffix' => 'giờ', 'hint' => 'Đã được duyệt', 'url' => route('overtime.index'),
                ],
            ]
            : [
                [
                    'icon' => 'palm', 'tone' => 'success', 'label' => 'Phép năm còn lại',
                    'value' => number_format($annualRemaining, ($annualRemaining == floor($annualRemaining) ? 0 : 1), ',', '.'),
                    'suffix' => 'ngày', 'hint' => 'Năm ' . $year, 'url' => route('balances.index'),
                ],
                [
                    'icon' => 'inbox', 'tone' => 'warning', 'label' => 'Đơn của tôi chờ duyệt',
                    'value' => $myPendingCount, 'suffix' => 'đơn',
                    'hint' => 'Đang trong quy trình duyệt', 'url' => route('leave.index'),
                ],
                [
                    'icon' => 'users', 'tone' => 'primary', 'label' => 'Đồng đội nghỉ hôm nay',
                    'value' => $onLeaveToday->where('user_id', '!=', $user->id)->count(), 'suffix' => 'người',
                    'hint' => $today->format('d/m/Y'), 'url' => route('team.index'),
                ],
                [
                    'icon' => 'clock', 'tone' => 'info', 'label' => 'Giờ OT tháng này',
                    'value' => number_format($otHoursMonth, ($otHoursMonth == floor($otHoursMonth) ? 0 : 1), ',', '.'),
                    'suffix' => 'giờ', 'hint' => 'Đã được duyệt', 'url' => route('overtime.index'),
                ],
            ];

        $leaveByType = LeaveRequest::with('leaveType')
            ->whereIn('status', ['pending', 'approved'])
            ->whereYear('start_date', $year)
            ->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))
            ->get()
            ->groupBy('leave_type_id')
            ->map(fn ($group) => [
                'name'  => $group->first()->leaveType?->name ?? 'Khác',
                'count' => $group->count(),
                'color' => $group->first()->leaveType?->color ?? '#2f6bff',
            ])
            ->values()
            ->all();

        $typeTotal = array_sum(array_column($leaveByType, 'count'));

        $monthlyOt = [];
        $monthlyLeave = [];
        $monthlyLabels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->copy()->startOfMonth()->subMonths($i);
            $monthlyLabels[] = $month->format('m/Y');

            $monthlyOt[] = round((float) OvertimeRequest::where('status', 'approved')
                ->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))
                ->whereBetween('ot_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->sum('hours'), 1);

            $monthlyLeave[] = round((float) LeaveRequest::where('status', 'approved')
                ->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))
                ->whereBetween('start_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->sum('total_days'), 1);
        }

        $headcount = $seesAll
            ? Department::withCount(['users' => fn ($q) => $q->where('status', 'active')])
                ->where('active', true)
                ->having('users_count', '>', 0)
                ->orderByDesc('users_count')
                ->limit(6)
                ->get()
            : collect();

        $pendingApprovals = collect();

        if ($isApprover) {
            $pendingApprovals = Approval::with(['approvable.user'])
                ->where('approver_id', $user->id)
                ->where('status', 'pending')
                ->latest()
                ->get()
                ->filter(fn (Approval $approval) => $approval->approvable
                    && $approval->approvable->status === 'pending'
                    && (int) $approval->approvable->current_step === (int) $approval->step)
                ->take(5)
                ->values();
        }

        $leaveRecent = LeaveRequest::with('leaveType')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                'type'       => 'leave',
                'code'       => $leave->code,
                'title'      => $leave->leaveType?->name ?? 'Nghỉ phép',
                'period'     => vn_date($leave->start_date, 'd/m') . ' – ' . vn_date($leave->end_date, 'd/m/Y'),
                'status'     => $leave->status,
                'created_at' => $leave->created_at,
                'url'        => route('leave.show', $leave),
            ]);

        $otRecent = OvertimeRequest::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (OvertimeRequest $ot) => [
                'type'       => 'overtime',
                'code'       => $ot->code,
                'title'      => 'Làm thêm giờ',
                'period'     => vn_date($ot->ot_date, 'd/m/Y') . ' • ' . substr($ot->start_time, 0, 5) . '–' . substr($ot->end_time, 0, 5),
                'status'     => $ot->status,
                'created_at' => $ot->created_at,
                'url'        => route('overtime.show', $ot),
            ]);

        $recentRequests = $leaveRecent->concat($otRecent)->sortByDesc('created_at')->take(6)->values();

        $myBalances = LeaveType::where('active', true)->where('deduct_balance', true)->orderBy('id')->get()
            ->map(function (LeaveType $type) use ($user, $year) {
                $balance = $user->leaveBalance($type->id, $year);

                return [
                    'type'      => $type,
                    'balance'   => $balance,
                    'entitled'  => $balance ? $balance->totalEntitled() : (float) $type->default_days,
                    'used'      => $balance?->used_days ?? 0,
                    'remaining' => $balance ? $balance->remaining() : (float) $type->default_days,
                ];
            });

        $upcomingHolidays = Holiday::whereDate('holiday_date', '>=', $today)
            ->orderBy('holiday_date')
            ->take(3)
            ->get();

        $recentNotifications = AppNotification::where('user_id', $user->id)->latest()->take(4)->get();

        return view('dashboard', compact(
            'user', 'seesAll', 'isApprover', 'cards', 'leaveByType', 'typeTotal',
            'monthlyOt', 'monthlyLeave', 'monthlyLabels', 'headcount', 'pendingApprovals',
            'onLeaveToday', 'recentRequests', 'myBalances', 'upcomingHolidays',
            'recentNotifications', 'annualType', 'annualBalance', 'annualRemaining'
        ));
    }
}
