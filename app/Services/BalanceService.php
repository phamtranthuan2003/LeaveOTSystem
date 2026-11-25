<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;

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
}
