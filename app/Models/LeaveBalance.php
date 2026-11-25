<?php

namespace App\Models;

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
        'used_days'     => 'float',
        'carried_over'  => 'float',
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
}
