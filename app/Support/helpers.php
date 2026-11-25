<?php

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            $settings = Cache::rememberForever('app_settings', function () {
                return Setting::pluck('value', 'key')->all();
            });

            return $settings[$key] ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('home_route_name')) {
    function home_route_name(?\App\Models\User $user = null): string
    {
        $user ??= auth()->user();

        if (!$user) {
            return 'login';
        }

        $map = [
            'dashboard.view'    => 'dashboard',
            'leave.view.own'    => 'leave.index',
            'leave.create'      => 'leave.create',
            'ot.view.own'       => 'overtime.index',
            'ot.create'         => 'overtime.create',
            'leave.approve'     => 'approvals.index',
            'ot.approve'        => 'approvals.index',
            'calendar.view'     => 'calendar.index',
            'team.view'         => 'team.index',
            'balance.view.own'  => 'balances.index',
            'balance.view.all'  => 'admin.balances.index',
            'user.view'         => 'admin.users.index',
            'department.view'   => 'admin.departments.index',
            'role.view'         => 'admin.roles.index',
            'report.view'       => 'reports.index',
            'setting.view'      => 'admin.settings.index',
            'audit.view'        => 'admin.audit.index',
        ];

        foreach ($map as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return $route;
            }
        }

        return 'profile.edit';
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'EV';
        }
        $parts = preg_split('/\s+/u', $name);
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }
        $first = mb_substr(end($parts), 0, 1);
        $last  = mb_substr($parts[0], 0, 1);

        return mb_strtoupper($last . $first);
    }
}

if (!function_exists('vn_date')) {
    function vn_date($date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '—';
        }
        try {
            return Carbon::parse($date)->format($format);
        } catch (\Throwable $e) {
            return '—';
        }
    }
}

if (!function_exists('status_meta')) {
    function status_meta(string $status): array
    {
        return match ($status) {
            'pending'   => ['label' => 'Chờ duyệt', 'tone' => 'warning'],
            'approved'  => ['label' => 'Đã duyệt', 'tone' => 'success'],
            'rejected'  => ['label' => 'Từ chối', 'tone' => 'danger'],
            'cancelled' => ['label' => 'Đã huỷ', 'tone' => 'muted'],
            default     => ['label' => ucfirst($status), 'tone' => 'muted'],
        };
    }
}

if (!function_exists('ot_type_meta')) {
    function ot_type_meta(string $type): array
    {
        return match ($type) {
            'weekend' => ['label' => 'Ngày cuối tuần', 'tone' => 'info'],
            'holiday' => ['label' => 'Ngày lễ', 'tone' => 'danger'],
            default   => ['label' => 'Ngày làm việc', 'tone' => 'muted'],
        };
    }
}

if (!function_exists('role_tone')) {
    function role_tone(?string $role): string
    {
        return match ($role) {
            'admin'    => 'danger',
            'director' => 'warning',
            'hr'       => 'info',
            'manager'  => 'primary',
            'leader'   => 'success',
            default    => 'muted',
        };
    }
}

if (!function_exists('working_day_label')) {
    function working_day_label(string $dayType): string
    {
        return match ($dayType) {
            'morning'   => 'Buổi sáng',
            'afternoon' => 'Buổi chiều',
            default     => 'Cả ngày',
        };
    }
}

if (!function_exists('hours_label')) {
    function hours_label($hours): string
    {
        $h = (float) $hours;
        if ($h == floor($h)) {
            return number_format($h, 0) . ' giờ';
        }

        return rtrim(rtrim(number_format($h, 2, ',', '.'), '0'), ',') . ' giờ';
    }
}

if (!function_exists('days_label')) {
    function days_label($days): string
    {
        $d = (float) $days;
        if ($d == floor($d)) {
            return number_format($d, 0) . ' ngày';
        }

        return rtrim(rtrim(number_format($d, 1, ',', '.'), '0'), ',') . ' ngày';
    }
}
