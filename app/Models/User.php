<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'employee_code', 'role_id', 'department_id',
        'manager_id', 'position', 'phone', 'avatar', 'join_date', 'date_of_birth',
        'address', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'join_date' => 'date',
            'date_of_birth' => 'date',
            'is_protected' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'approver_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isRole(string ...$slugs): bool
    {
        return $this->role !== null && in_array($this->role->slug, $slugs, true);
    }

    public function canPurgeRecords(): bool
    {
        return (bool) $this->is_protected;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->role) {
            return false;
        }

        if ($this->role->slug === 'admin') {
            return true;
        }

        return $this->role->permissions->contains('slug', $permission);
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return asset($this->avatar);
    }

    public function managerChain(): array
    {
        $chain = [];
        $current = $this->manager;
        $guard = 0;

        while ($current && $guard < 10) {
            $chain[] = $current;
            $current = $current->manager;
            $guard++;
        }

        return $chain;
    }

    public function teamIds(): array
    {
        $ids = [];
        $queue = [$this->id];
        $guard = 0;

        while ($queue && $guard < 200) {
            $current = array_shift($queue);
            $reports = static::where('manager_id', $current)->pluck('id')->all();
            foreach ($reports as $id) {
                if ($id !== $this->id && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $queue[] = $id;
                }
            }
            $guard++;
        }

        return $ids;
    }

    public function leaveBalance(int $leaveTypeId, ?int $year = null): ?LeaveBalance
    {
        return $this->leaveBalances()
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year ?? now()->year)
            ->first();
    }

    public function pendingApprovalCount(): int
    {
        $isHr = $this->isRole('hr');

        return Approval::where(function ($query) use ($isHr) {
            $query->where('approver_id', $this->id);

            if ($isHr) {
                $query->orWhere('approver_role', 'hr');
            }
        })
            ->where('status', 'pending')
            ->whereHas('approvable', function ($query) {
                $query->where('status', 'pending')
                    ->whereColumn('current_step', 'approvals.step');
            })
            ->count();
    }
}
