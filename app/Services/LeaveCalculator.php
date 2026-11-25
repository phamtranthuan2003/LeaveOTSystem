<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;

class LeaveCalculator
{
    /**
     * Đếm số ngày làm việc (Mon-Fri), trừ ngày lễ. Hỗ trợ nghỉ nửa ngày cho đơn 1 ngày.
     */
    public function workingDays(Carbon $start, Carbon $end, string $dayType = 'full'): float
    {
        $holidays = $this->holidayDates($start, $end);
        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            if (!$cursor->isWeekend() && !in_array($cursor->toDateString(), $holidays, true)) {
                $count++;
            }
            $cursor->addDay();
        }

        if ($start->isSameDay($end) && in_array($dayType, ['morning', 'afternoon'], true)) {
            return $count > 0 ? 0.5 : 0.0;
        }

        return (float) $count;
    }

    public function holidayDates(Carbon $start, Carbon $end): array
    {
        return Holiday::whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (Holiday $holiday) => $holiday->holiday_date->toDateString())
            ->all();
    }

    public function isHoliday(Carbon $date): bool
    {
        return Holiday::whereDate('holiday_date', $date->toDateString())->exists();
    }

    public function detectOtType(Carbon $date): string
    {
        if ($this->isHoliday($date)) {
            return 'holiday';
        }

        return $date->isWeekend() ? 'weekend' : 'weekday';
    }

    public function otRate(string $type): float
    {
        $defaults = ['weekday' => 1.5, 'weekend' => 2.0, 'holiday' => 3.0];
        $key = 'ot_rate_' . $type;

        return (float) setting($key, $defaults[$type] ?? 1.5);
    }

    /**
     * Số giờ OT; nếu giờ kết thúc nhỏ hơn giờ bắt đầu thì coi là OT qua đêm (kết thúc hôm sau).
     */
    public function otHours(string $startTime, string $endTime): float
    {
        [$sh, $sm] = array_map('intval', explode(':', $startTime));
        [$eh, $em] = array_map('intval', explode(':', $endTime));

        $minutes = ($eh * 60 + $em) - ($sh * 60 + $sm);

        if ($minutes < 0) {
            $minutes += 24 * 60;
        }

        if ($minutes <= 0) {
            return 0.0;
        }

        return round($minutes / 60, 2);
    }

    public function workingDaysInMonth(Carbon $month): int
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $holidays = $this->holidayDates($start, $end);
        $count = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if (!$cursor->isWeekend() && !in_array($cursor->toDateString(), $holidays, true)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
