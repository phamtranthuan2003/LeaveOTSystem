<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'code', 'user_id', 'ot_date', 'start_time', 'end_time', 'hours', 'ot_type',
        'multiplier', 'project', 'reason', 'status', 'current_step', 'total_steps', 'decided_at',
    ];

    protected $casts = [
        'ot_date'    => 'date',
        'decided_at' => 'datetime',
        'hours'      => 'float',
        'multiplier' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('step');
    }

    public function currentApproval(): ?Approval
    {
        return $this->approvals()->where('status', 'pending')->orderBy('step')->first();
    }

    public function scopePendingForApprover($query, int $userId)
    {
        return $query->where('status', 'pending')
            ->whereHas('approvals', function ($q) use ($userId) {
                $q->where('approver_id', $userId)
                    ->where('status', 'pending')
                    ->whereColumn('approvals.step', 'overtime_requests.current_step');
            });
    }

    public static function nextCode(): string
    {
        $prefix = 'OT-' . now()->year . '-';
        $last = static::where('code', 'like', $prefix . '%')->orderByDesc('code')->value('code');
        $number = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
