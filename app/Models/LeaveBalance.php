<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'user_id', 'year', 'leave_type_id', 'entitled_days', 'used_days', 'carried_over',
        'last_accrual_month', 'note',
    ];

    protected $casts = [
        'entitled_days' => 'float',
        'used_days' => 'float',
        'carried_over' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function remaining(): float
    {
        return max(0, $this->entitled_days + $this->carried_over - $this->used_days);
    }

    public function totalEntitled(): float
    {
        return $this->entitled_days + $this->carried_over;
    }

    /**
     * Năm phép của công ty bắt đầu từ tháng 3 và kết thúc vào tháng 2 năm sau.
     */
    public static function cycleYear(?CarbonInterface $date = null): int
    {
        $date ??= now();

        return $date->month >= 3 ? (int) $date->year : (int) $date->year - 1;
    }

    public static function yearFor(LeaveType $type, CarbonInterface $date): int
    {
        return $type->isAnnual() ? static::cycleYear($date) : (int) $date->year;
    }
}
