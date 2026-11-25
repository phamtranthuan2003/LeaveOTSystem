<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public function entitlementFor(LeaveType $type): float
    {
        if ($type->code === 'AL') {
            return (float) setting('annual_leave_default', $type->default_days ?: 12);
        }

        return (float) $type->default_days;
    }

    public function ensureForUser(User $user, int $year): int
    {
        $created = 0;

        $types = LeaveType::where('active', true)->where('deduct_balance', true)->get();

        foreach ($types as $type) {
            if ($this->firstOrCreateBalance($user, $type, $year, false)->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public function generateYear(int $year, bool $carryOver = true): int
    {
        $created = 0;

        $types = LeaveType::where('active', true)->where('deduct_balance', true)->get();
        $users = User::active()->get();

        foreach ($users as $user) {
            foreach ($types as $type) {
                if ($this->firstOrCreateBalance($user, $type, $year, $carryOver)->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    public function firstOrCreateBalance(User $user, LeaveType $type, int $year, bool $carryOver = true): LeaveBalance
    {
        $existing = LeaveBalance::where([
            'user_id'       => $user->id,
            'year'          => $year,
            'leave_type_id' => $type->id,
        ])->first();

        if ($existing) {
            return $existing;
        }

        $carried = 0.0;

        if ($carryOver) {
            $previous = LeaveBalance::where([
                'user_id'       => $user->id,
                'year'          => $year - 1,
                'leave_type_id' => $type->id,
            ])->first();

            if ($previous) {
                $carried = min((float) setting('max_carry_over', 5), $previous->remaining());
            }
        }

        return LeaveBalance::create([
            'user_id'       => $user->id,
            'year'          => $year,
            'leave_type_id' => $type->id,
            'entitled_days' => $this->entitlementFor($type),
            'used_days'     => 0,
            'carried_over'  => $carried,
            'note'          => $carried > 0 ? 'Chuyển từ năm ' . ($year - 1) : null,
        ]);
    }

    /**
     * Cộng 1 ngày cho mỗi nhân sự đang làm việc, mỗi kỳ chỉ cộng một lần.
     */
    public function accrueMonthly(LeaveType $type, CarbonInterface $month): int
    {
        $marker = $month->format('Y-m');
        $year = (int) $month->year;
        $accrued = 0;

        User::active()->chunkById(200, function ($users) use ($type, $year, $marker, &$accrued) {
            foreach ($users as $user) {
                $changed = DB::transaction(function () use ($user, $type, $year, $marker) {
                    $balance = $this->firstOrCreateBalance($user, $type, $year);

                    $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->first();

                    if ($balance->last_accrual_month === $marker) {
                        return false;
                    }

                    $balance->entitled_days = round((float) $balance->entitled_days + 1, 1);
                    $balance->last_accrual_month = $marker;
                    $balance->save();

                    return true;
                });

                if ($changed) {
                    $accrued++;
                }
            }
        });

        return $accrued;
    }

    /**
     * Cộng/trừ số ngày được hưởng của một nhân sự.
     *
     * @return array{balance: LeaveBalance, before: float, after: float}|null Trả về null nếu trừ vượt quá số ngày hiện có.
     */
    public function adjust(User $user, LeaveType $type, int $year, float $days): ?array
    {
        return DB::transaction(function () use ($user, $type, $year, $days) {
            $balance = LeaveBalance::firstOrCreate(
                [
                    'user_id'       => $user->id,
                    'year'          => $year,
                    'leave_type_id' => $type->id,
                ],
                [
                    'entitled_days' => $this->entitlementFor($type),
                    'used_days'     => 0,
                    'carried_over'  => 0,
                ]
            );

            $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->first();
            $before = (float) $balance->entitled_days;
            $after = round($before + $days, 1);

            if ($after < 0) {
                return null;
            }

            $balance->entitled_days = $after;
            $balance->save();

            return ['balance' => $balance, 'before' => $before, 'after' => $after];
        });
    }
}
