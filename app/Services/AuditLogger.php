<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(string $action, string $module, string $description, ?Model $subject = null, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => $action,
                'module'       => $module,
                'description'  => $description,
                'subject_type' => $subject ? $subject->getMorphClass() : null,
                'subject_id'   => $subject?->getKey(),
                'ip'           => request()?->ip(),
                'user_agent'   => substr((string) request()?->userAgent(), 0, 250),
                'meta'         => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
