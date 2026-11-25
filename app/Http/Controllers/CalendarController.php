<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        try {
            $month = $request->filled('month')
                ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $month = now()->startOfMonth();
        }

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $seesAll = $user->hasPermission('leave.view.all');
        $teamIds = $user->teamIds();
        $scopeIds = $seesAll ? [] : array_values(array_unique(array_merge([$user->id], $teamIds)));

        $leaves = LeaveRequest::with(['user.department', 'leaveType'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $gridEnd)
            ->whereDate('end_date', '>=', $gridStart)
            ->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))
            ->get();

        $pendingOwn = LeaveRequest::with(['user', 'leaveType'])
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereDate('start_date', '<=', $gridEnd)
            ->whereDate('end_date', '>=', $gridStart)
            ->get();

        $overtimes = OvertimeRequest::with('user')
            ->where('status', 'approved')
            ->whereBetween('ot_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when(!$seesAll, fn ($q) => $q->whereIn('user_id', $scopeIds))
            ->get();

        $holidays = Holiday::whereBetween('holiday_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString());

        $days = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $days[$cursor->toDateString()] = [
                'date'      => $cursor->copy(),
                'in_month'  => $cursor->month === $month->month,
                'is_today'  => $cursor->isToday(),
                'is_weekend' => $cursor->isWeekend(),
                'holiday'   => $holidays->get($cursor->toDateString()),
                'leaves'    => [],
                'ots'       => [],
            ];
            $cursor->addDay();
        }

        foreach ($leaves->concat($pendingOwn) as $leave) {
            $event = [
                'type'     => 'leave',
                'status'   => $leave->status,
                'name'     => $leave->user->name,
                'initials' => initials($leave->user->name),
                'color'    => $leave->leaveType?->color ?? '#2f6bff',
                'title'    => $leave->leaveType?->name ?? 'Nghỉ phép',
                'meta'     => $leave->code,
                'url'      => route('leave.show', $leave),
            ];

            $day = $leave->start_date->copy();

            while ($day->lte($leave->end_date)) {
                $key = $day->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['leaves'][] = $event;
                }
                $day->addDay();
            }
        }

        foreach ($overtimes as $overtime) {
            $key = $overtime->ot_date->toDateString();
            if (!isset($days[$key])) {
                continue;
            }
            $days[$key]['ots'][] = [
                'type'     => 'overtime',
                'status'   => $overtime->status,
                'name'     => $overtime->user->name,
                'initials' => initials($overtime->user->name),
                'color'    => '#f7941d',
                'title'    => substr($overtime->start_time, 0, 5) . '–' . substr($overtime->end_time, 0, 5),
                'meta'     => $overtime->code,
                'url'      => route('overtime.show', $overtime),
            ];
        }

        $weeks = array_chunk($days, 7, true);

        $monthLabel = 'Tháng ' . $month->month . ' / ' . $month->year;
        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $nextMonth = $month->copy()->addMonth()->format('Y-m');

        $approvedLeaveCount = $leaves->count();
        $otCount = $overtimes->count();

        return view('calendar.index', compact(
            'user', 'month', 'monthLabel', 'prevMonth', 'nextMonth', 'weeks',
            'approvedLeaveCount', 'otCount', 'seesAll'
        ));
    }
}
