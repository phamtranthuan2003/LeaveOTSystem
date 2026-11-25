<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('vi');

        Paginator::defaultView('vendor.pagination.everland');
        Paginator::defaultSimpleView('vendor.pagination.everland');

        Relation::morphMap([
            'leave'    => LeaveRequest::class,
            'overtime' => OvertimeRequest::class,
        ]);

        foreach (config('permissions.groups', []) as $permissions) {
            foreach (array_keys($permissions) as $permission) {
                Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
            }
        }

        View::composer('layouts.app', function ($view) {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();

            $unreadQuery = AppNotification::where('user_id', $user->id)->where('is_read', false);

            $view->with([
                'layoutUser'          => $user,
                'layoutUnread'        => (clone $unreadQuery)->count(),
                'layoutNotifications' => $unreadQuery->latest()->limit(5)->get(),
                'layoutPending'       => ($user->hasPermission('leave.approve') || $user->hasPermission('ot.approve'))
                    ? $user->pendingApprovalCount()
                    : 0,
            ]);
        });
    }
}
