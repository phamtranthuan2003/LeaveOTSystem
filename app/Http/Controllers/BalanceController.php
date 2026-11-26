<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BalanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $currentYear = LeaveBalance::cycleYear();
        $year = (int) $request->input('year', $currentYear);

        if ($year < $currentYear - 3 || $year > $currentYear + 1) {
            $year = $currentYear;
        }

        $leaveTypes = LeaveType::where('active', true)->where('deduct_balance', true)->orderBy('id')->get();

        $balances = $leaveTypes->map(function (LeaveType $type) use ($user, $year) {
            $balance = $user->leaveBalance($type->id, $year);

            return [
                'type' => $type,
                'balance' => $balance,
                'entitled' => $balance ? $balance->totalEntitled() : (float) $type->default_days,
                'used' => $balance?->used_days ?? 0,
                'remaining' => $balance ? $balance->remaining() : (float) $type->default_days,
            ];
        });

        $usage = LeaveRequest::with('leaveType')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->get()
            ->groupBy('leave_type_id')
            ->map(fn ($group) => [
                'name' => $group->first()->leaveType?->name ?? 'Khác',
                'color' => $group->first()->leaveType?->color ?? '#2f6bff',
                'days' => (float) $group->sum('total_days'),
                'count' => $group->count(),
            ])
            ->values();

        $history = LeaveRequest::with('leaveType')
            ->where('user_id', $user->id)
            ->whereYear('start_date', $year)
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $totalUsed = (float) $usage->sum('days');
        $years = range($currentYear + 1, $currentYear - 2);

        return view('balances.index', compact('user', 'year', 'balances', 'usage', 'history', 'totalUsed', 'years'));
    }
}
