<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
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

            if ($balance->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public function generateYear(int $year, bool $carryOver = true): int
    {
        $maxCarry = (float) setting('max_carry_over', 5);
        $created = 0;

        $types = LeaveType::where('active', true)->where('deduct_balance', true)->get();
        $users = User::active()->get();

        foreach ($users as $user) {
            foreach ($types as $type) {
                $exists = LeaveBalance::where([
                    'user_id'       => $user->id,
                    'year'          => $year,
                    'leave_type_id' => $type->id,
                ])->exists();

                if ($exists) {
                    continue;
                }

                $carried = 0.0;

                if ($carryOver) {
                    $previous = LeaveBalance::where([
                        'user_id'       => $user->id,
                        'year'          => $year - 1,
                        'leave_type_id' => $type->id,
                    ])->first();

                    if ($previous) {
                        $carried = min($maxCarry, $previous->remaining());
                    }
                }

                LeaveBalance::create([
                    'user_id'       => $user->id,
                    'year'          => $year,
                    'leave_type_id' => $type->id,
                    'entitled_days' => $this->entitlementFor($type),
                    'used_days'     => 0,
                    'carried_over'  => $carried,
                    'note'          => $carried > 0 ? 'Chuyển từ năm ' . ($year - 1) : null,
                ]);

                $created++;
            }
        }

        return $created;
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
