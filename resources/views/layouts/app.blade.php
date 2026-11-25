<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a2759">
    <title>@yield('title', 'Tổng quan') · Everland HR</title>
    <link rel="icon" href="{{ asset('img/everland-1668155348.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
@include('partials.icons')

<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <span class="brand-logo"><img src="{{ asset('img/everland-1668155348.png') }}" alt="Everland Group"></span>
            <span class="brand-text">
                <strong>EVERLAND</strong>
                <span>Leave &amp; OT</span>
            </span>
        </a>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-label">Tổng quan</div>
                @can('dashboard.view')
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-grid"></use></svg>
                    Bảng điều khiển
                </a>
                @endcan
                @can('leave.approve')
                <a href="{{ route('approvals.index') }}" class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-check"></use></svg>
                    Duyệt đơn
                    @if ($layoutPending > 0)
                        <span class="nav-badge">{{ $layoutPending > 99 ? '99+' : $layoutPending }}</span>
                    @endif
                </a>
                @endcan
            </div>

            <div class="nav-section">
                <div class="nav-label">Nghỉ phép &amp; OT</div>
                @can('leave.view.own')
                <a href="{{ route('leave.index') }}" class="nav-link {{ request()->routeIs('leave.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-sun"></use></svg>
                    Đơn nghỉ phép
                </a>
                @endcan
                @can('ot.create')
                <a href="{{ route('overtime.index') }}" class="nav-link {{ request()->routeIs('overtime.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-clock"></use></svg>
                    Đăng ký làm thêm giờ
                </a>
                @endcan
            </div>

            <div class="nav-section">
                <div class="nav-label">Đội nhóm &amp; lịch</div>
                @can('calendar.view')
                <a href="{{ route('calendar.index') }}" class="nav-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-calendar"></use></svg>
                    Lịch nghỉ &amp; OT
                </a>
                @endcan
                @can('team.view')
                <a href="{{ route('team.index') }}" class="nav-link {{ request()->routeIs('team.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-users"></use></svg>
                    Đội nhóm của tôi
                </a>
                @endcan
                @can('balance.view.own')
                <a href="{{ route('balances.index') }}" class="nav-link {{ request()->routeIs('balances.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-wallet"></use></svg>
                    Quỹ phép của tôi
                </a>
                @endcan
            </div>

            @can('report.view')
            <div class="nav-section">
                <div class="nav-label">Báo cáo</div>
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-trend"></use></svg>
                    Thống kê &amp; báo cáo
                </a>
            </div>
            @endcan

            @if ($layoutUser->hasPermission('user.view') || $layoutUser->hasPermission('department.manage') || $layoutUser->hasPermission('leavetype.manage') || $layoutUser->hasPermission('holiday.manage') || $layoutUser->hasPermission('balance.manage') || $layoutUser->hasPermission('role.manage') || $layoutUser->hasPermission('setting.manage') || $layoutUser->hasPermission('audit.view'))
            <div class="nav-section">
                <div class="nav-label">Quản trị</div>
                @can('user.view')
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-user"></use></svg>
                    Nhân sự
                </a>
                @endcan
                @can('department.manage')
                <a href="{{ route('admin.departments.index') }}" class="nav-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-branch"></use></svg>
                    Cơ cấu phòng ban
                </a>
                @endcan
                @can('leavetype.manage')
                <a href="{{ route('admin.leave-types.index') }}" class="nav-link {{ request()->routeIs('admin.leave-types.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-file"></use></svg>
                    Loại phép
                </a>
                @endcan
                @can('holiday.manage')
                <a href="{{ route('admin.holidays.index') }}" class="nav-link {{ request()->routeIs('admin.holidays.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-calendar"></use></svg>
                    Ngày lễ
                </a>
                @endcan
                @can('balance.manage')
                <a href="{{ route('admin.balances.index') }}" class="nav-link {{ request()->routeIs('admin.balances.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-layers"></use></svg>
                    Quỹ phép nhân sự
                </a>
                @endcan
                @can('role.manage')
                <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-shield"></use></svg>
                    Vai trò &amp; quyền
                </a>
                @endcan
                @can('setting.manage')
                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-sliders"></use></svg>
                    Cấu hình hệ thống
                </a>
                @endcan
                @can('audit.view')
                <a href="{{ route('admin.audit.index') }}" class="nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-eye"></use></svg>
                    Nhật ký hoạt động
                </a>
                @endcan
            </div>
            @endif

            <div class="nav-section">
                <div class="nav-label">Tài khoản</div>
                <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-bell"></use></svg>
                    Thông báo
                    @if ($layoutUnread > 0)
                        <span class="nav-badge">{{ $layoutUnread > 99 ? '99+' : $layoutUnread }}</span>
                    @endif
                </a>
                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-user"></use></svg>
                    Hồ sơ cá nhân
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="sf-title">Hỗ trợ nhân sự</div>
            <div class="sf-value">{{ setting('company_email', 'hr@everland.vn') }}</div>
            <div class="sf-value" style="opacity:.6;margin-top:4px">Everland Group · {{ date('Y') }}</div>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="hamburger" id="sidebar-toggle" aria-label="Mở menu">
                <svg class="icon"><use href="#i-menu"></use></svg>
            </button>

            <div class="topbar-title">
                <h1>@yield('title', 'Tổng quan')</h1>
                <p>@yield('subtitle', now()->translatedFormat('l, d/m/Y'))</p>
            </div>

            <div class="topbar-right">
                @hasSection('actions')
                <div class="topbar-actions">@yield('actions')</div>
                @endif

                <div class="dropdown" data-dropdown>
                    <button type="button" class="icon-btn" data-dropdown-toggle aria-label="Thông báo">
                        <svg class="icon"><use href="#i-bell"></use></svg>
                        @if ($layoutUnread > 0)
                            <span class="dot-badge">{{ $layoutUnread > 99 ? '99+' : $layoutUnread }}</span>
                        @endif
                    </button>
                    <div class="dropdown-menu" style="min-width: 330px">
                        <div class="dropdown-head flex items-center justify-between">
                            <strong>Thông báo mới</strong>
                            <a href="{{ route('notifications.index') }}" class="text-xs strong">Xem tất cả</a>
                        </div>
                        @forelse ($layoutNotifications as $notification)
                            <a href="{{ $notification->link ? url($notification->link) : route('notifications.index') }}" class="dropdown-item" style="align-items: flex-start">
                                <span class="list-icon" style="width: 32px; height: 32px; border-radius: 9px">
                                    <svg class="icon" style="width: 15px; height: 15px"><use href="#i-bell"></use></svg>
                                </span>
                                <span style="min-width: 0">
                                    <span class="strong" style="display: block; font-size: 12.5px; color: var(--navy-900)">{{ Str::limit($notification->title, 64) }}</span>
                                    <span class="text-xs muted" style="display: block; margin-top: 2px">{{ $notification->created_at?->diffForHumans() }}</span>
                                </span>
                            </a>
                        @empty
                            <div class="empty" style="padding: 22px 12px">
                                <strong>Không có thông báo mới</strong>
                                <p>Bạn đã xử lý hết thông báo.</p>
                            </div>
                        @endforelse
                        @if ($layoutUnread > 0)
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <svg class="icon"><use href="#i-check"></use></svg>
                                    Đánh dấu tất cả đã đọc
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="dropdown" data-dropdown>
                    <button type="button" class="user-chip" data-dropdown-toggle>
                        <span class="avatar avatar-sm {{ $layoutUser->avatarTone ?? '' }}">
                            @if ($layoutUser->avatarUrl())
                                <img src="{{ $layoutUser->avatarUrl() }}" alt="{{ $layoutUser->name }}">
                            @else
                                {{ initials($layoutUser->name) }}
                            @endif
                        </span>
                        <span class="user-chip-text">
                            <strong>{{ $layoutUser->name }}</strong>
                            <span>{{ $layoutUser->role?->name ?? 'Nhân viên' }}</span>
                        </span>
                        <svg class="icon icon-sm muted"><use href="#i-chevron-down"></use></svg>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-head">
                            <strong>{{ $layoutUser->name }}</strong>
                            <span>{{ $layoutUser->position ?: ($layoutUser->role?->name ?? '') }}{{ $layoutUser->department ? ' · ' . $layoutUser->department->name : '' }}</span>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="dropdown-item">
                            <svg class="icon"><use href="#i-user"></use></svg>
                            Hồ sơ cá nhân
                        </a>
                        <a href="{{ route('balances.index') }}" class="dropdown-item">
                            <svg class="icon"><use href="#i-wallet"></use></svg>
                            Quỹ phép của tôi
                        </a>
                        <a href="{{ route('notifications.index') }}" class="dropdown-item">
                            <svg class="icon"><use href="#i-bell"></use></svg>
                            Trung tâm thông báo
                            @if ($layoutUnread > 0)
                                <span class="count-pill orange" style="margin-left: auto">{{ $layoutUnread }}</span>
                            @endif
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item danger">
                                <svg class="icon"><use href="#i-logout"></use></svg>
                                Đăng xuất
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="alert alert-success" data-auto-dismiss>
                    <svg class="icon"><use href="#i-check"></use></svg>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="alert-close" data-dismiss-alert aria-label="Đóng">
                        <svg class="icon icon-sm"><use href="#i-x"></use></svg>
                    </button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-error" data-auto-dismiss>
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <div>{{ session('error') }}</div>
                    <button type="button" class="alert-close" data-dismiss-alert aria-label="Đóng">
                        <svg class="icon icon-sm"><use href="#i-x"></use></svg>
                    </button>
                </div>
            @endif
            @if (session('status'))
                <div class="alert alert-info" data-auto-dismiss>
                    <svg class="icon"><use href="#i-info"></use></svg>
                    <div>{{ session('status') }}</div>
                    <button type="button" class="alert-close" data-dismiss-alert aria-label="Đóng">
                        <svg class="icon icon-sm"><use href="#i-x"></use></svg>
                    </button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <div>
                        <strong>Vui lòng kiểm tra lại thông tin:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="footer">
            <span>© {{ date('Y') }} Everland Group — Hệ thống quản lý nghỉ phép &amp; làm thêm giờ.</span>
            <span>Phiên bản 1.0 · Dữ liệu hiển thị theo giờ Việt Nam (GMT+7)</span>
        </footer>
    </div>
</div>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
