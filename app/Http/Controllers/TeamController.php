<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $teamIds = $user->teamIds();
        $isManagerView = count($teamIds) > 0;

        if (!$isManagerView) {
            $teamIds = User::active()
                ->where('department_id', $user->department_id)
                ->where('id', '!=', $user->id)
                ->pluck('id')
                ->all();
        }

        $members = User::with(['role', 'department', 'manager'])
            ->whereIn('id', $teamIds)
            ->orderBy('name')
            ->paginate(12);

        $annualType = LeaveType::where('code', 'AL')->first();
        $year = now()->year;

        $balances = $annualType
            ? LeaveBalance::whereIn('user_id', $teamIds)
                ->where('year', $year)
                ->where('leave_type_id', $annualType->id)
                ->get()
                ->keyBy('user_id')
            : collect();

        $pendingLeaves = LeaveRequest::whereIn('user_id', $teamIds)
            ->where('status', 'pending')
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $pendingOts = OvertimeRequest::whereIn('user_id', $teamIds)
            ->where('status', 'pending')
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $onLeaveToday = LeaveRequest::with('leaveType')
            ->whereIn('user_id', $teamIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->get()
            ->keyBy('user_id');

        $otHoursMonth = (float) OvertimeRequest::whereIn('user_id', $teamIds)
            ->where('status', 'approved')
            ->whereBetween('ot_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('hours');

        $stats = [
            'members'     => $members->total(),
            'on_leave'    => $onLeaveToday->count(),
            'pending'     => $pendingLeaves->sum() + $pendingOts->sum(),
            'ot_hours'    => $otHoursMonth,
        ];

        return view('team.index', compact(
            'user', 'isManagerView', 'members', 'balances', 'annualType',
            'pendingLeaves', 'pendingOts', 'onLeaveToday', 'stats'
        ));
    }
}
