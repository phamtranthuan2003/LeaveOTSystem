<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    protected $fillable = [
        'approvable_type', 'approvable_id', 'step', 'approver_id',
        'approver_role', 'status', 'note', 'acted_at', 'acted_by',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /**
     * Bước quản lý cao nhất trong chuỗi (bước cuối trước HR chốt).
     */
    public function isFinalManagementStep(): bool
    {
        $requestable = $this->approvable;

        if (! $requestable) {
            return false;
        }

        $this->loadMissing('approver.role', 'approvable.approvals.approver.role');

        if (! $this->approver || $this->approver->isRole('hr')) {
            return false;
        }

        $lastManagement = $requestable->approvals
            ->filter(fn (self $step) => $step->approver && ! $step->approver->isRole('hr'))
            ->sortByDesc('step')
            ->first();

        return $lastManagement !== null && $lastManagement->id === $this->id;
    }
}
