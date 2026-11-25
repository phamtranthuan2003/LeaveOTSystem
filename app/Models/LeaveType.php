<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'code', 'name', 'color', 'default_days', 'deduct_balance',
        'requires_attachment', 'is_paid', 'description', 'active',
    ];

    protected $casts = [
        'deduct_balance'      => 'boolean',
        'requires_attachment' => 'boolean',
        'is_paid'             => 'boolean',
        'active'              => 'boolean',
        'default_days'        => 'float',
    ];

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
