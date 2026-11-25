<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeaveRequest extends Model
{
    protected $fillable = [
        'code', 'user_id', 'leave_type_id', 'start_date', 'end_date', 'day_type',
        'total_days', 'reason', 'handover_to', 'attachment', 'status', 'current_step',
        'total_steps', 'balance_deducted', 'decided_at',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'decided_at'       => 'datetime',
        'balance_deducted' => 'boolean',
        'total_days'       => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_to');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('step');
    }

    public function currentApproval(): ?Approval
    {
        return $this->approvals()->where('status', 'pending')->orderBy('step')->first();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function scopePendingForApprover($query, int $userId)
    {
        return $query->where('status', 'pending')
            ->whereHas('approvals', function ($q) use ($userId) {
                $q->where('approver_id', $userId)
                    ->where('status', 'pending')
                    ->whereColumn('approvals.step', 'leave_requests.current_step');
            });
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public static function nextCode(): string
    {
        $prefix = 'NP-' . now()->year . '-';
        $last = static::where('code', 'like', $prefix . '%')->orderByDesc('code')->value('code');
        $number = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
