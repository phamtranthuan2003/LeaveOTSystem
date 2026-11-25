<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BalanceController as AdminBalanceController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LeaveTypeController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OvertimeRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')->name('dashboard');

    // Nghỉ phép
    Route::get('/leave', [LeaveRequestController::class, 'index'])
        ->middleware('can:leave.view.own')->name('leave.index');
    Route::get('/leave/create', [LeaveRequestController::class, 'create'])
        ->middleware('can:leave.create')->name('leave.create');
    Route::post('/leave', [LeaveRequestController::class, 'store'])
        ->middleware('can:leave.create')->name('leave.store');
    Route::get('/leave/{leave}', [LeaveRequestController::class, 'show'])
        ->middleware('can:leave.view.own')->name('leave.show');
    Route::post('/leave/{leave}/cancel', [LeaveRequestController::class, 'cancel'])
        ->middleware('can:leave.cancel.own')->name('leave.cancel');

    // Làm thêm giờ
    Route::get('/overtime', [OvertimeRequestController::class, 'index'])
        ->middleware('can:ot.create')->name('overtime.index');
    Route::get('/overtime/create', [OvertimeRequestController::class, 'create'])
        ->middleware('can:ot.create')->name('overtime.create');
    Route::post('/overtime', [OvertimeRequestController::class, 'store'])
        ->middleware('can:ot.create')->name('overtime.store');
    Route::get('/overtime/{overtime}', [OvertimeRequestController::class, 'show'])
        ->middleware('can:ot.create')->name('overtime.show');
    Route::post('/overtime/{overtime}/cancel', [OvertimeRequestController::class, 'cancel'])
        ->middleware('can:ot.create')->name('overtime.cancel');

    // Duyệt thác nước
    Route::get('/approvals', [ApprovalController::class, 'index'])
        ->middleware('can:leave.approve')->name('approvals.index');
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])
        ->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])
        ->name('approvals.reject');
    Route::post('/approvals/{approval}/approve-all', [ApprovalController::class, 'approveAll'])
        ->name('approvals.approve-all');

    // Lịch, đội nhóm, quỹ phép
    Route::get('/calendar', [CalendarController::class, 'index'])
        ->middleware('can:calendar.view')->name('calendar.index');
    Route::get('/team', [TeamController::class, 'index'])
        ->middleware('can:team.view')->name('team.index');
    Route::get('/balances', [BalanceController::class, 'index'])
        ->middleware('can:balance.view.own')->name('balances.index');

    // Thông báo
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Hồ sơ cá nhân
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Báo cáo
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('can:report.view')->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])
        ->middleware('can:report.export')->name('reports.export');

    // Quản trị
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('can:user.view')->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])
            ->middleware('can:user.manage')->name('users.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('can:user.manage')->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('can:user.manage')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('can:user.manage')->name('users.update');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleStatus'])
            ->middleware('can:user.manage')->name('users.toggle');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('can:user.manage')->name('users.reset-password');

        Route::get('/departments', [DepartmentController::class, 'index'])
            ->middleware('can:department.manage')->name('departments.index');
        Route::get('/departments/create', [DepartmentController::class, 'create'])
            ->middleware('can:department.manage')->name('departments.create');
        Route::post('/departments', [DepartmentController::class, 'store'])
            ->middleware('can:department.manage')->name('departments.store');
        Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])
            ->middleware('can:department.manage')->name('departments.edit');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])
            ->middleware('can:department.manage')->name('departments.update');
        Route::post('/departments/{department}/toggle', [DepartmentController::class, 'toggle'])
            ->middleware('can:department.manage')->name('departments.toggle');

        Route::get('/leave-types', [LeaveTypeController::class, 'index'])
            ->middleware('can:leavetype.manage')->name('leave-types.index');
        Route::post('/leave-types', [LeaveTypeController::class, 'store'])
            ->middleware('can:leavetype.manage')->name('leave-types.store');
        Route::put('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])
            ->middleware('can:leavetype.manage')->name('leave-types.update');
        Route::post('/leave-types/{leaveType}/toggle', [LeaveTypeController::class, 'toggle'])
            ->middleware('can:leavetype.manage')->name('leave-types.toggle');

        Route::get('/holidays', [HolidayController::class, 'index'])
            ->middleware('can:holiday.manage')->name('holidays.index');
        Route::post('/holidays', [HolidayController::class, 'store'])
            ->middleware('can:holiday.manage')->name('holidays.store');
        Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])
            ->middleware('can:holiday.manage')->name('holidays.update');
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])
            ->middleware('can:holiday.manage')->name('holidays.destroy');

        Route::get('/balances', [AdminBalanceController::class, 'index'])
            ->middleware('can:balance.manage')->name('balances.index');
        Route::post('/balances/generate', [AdminBalanceController::class, 'generate'])
            ->middleware('can:balance.manage')->name('balances.generate');
        Route::post('/balances/{user}', [AdminBalanceController::class, 'update'])
            ->middleware('can:balance.manage')->name('balances.update');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('can:role.manage')->name('roles.index');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('can:role.manage')->name('roles.update');

        Route::get('/settings', [SettingController::class, 'index'])
            ->middleware('can:setting.manage')->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])
            ->middleware('can:setting.manage')->name('settings.update');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('can:audit.view')->name('audit.index');
    });
});
