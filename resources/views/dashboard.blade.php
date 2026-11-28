@extends('layouts.app')

@section('title', 'Bảng điều khiển')
@section('subtitle', now()->translatedFormat('l, d/m/Y') . ' · Xin chào ' . $user->name)

@section('actions')
    @can('leave.create')
        <a href="{{ route('leave.create') }}" class="btn btn-primary btn-sm">
            <svg class="icon"><use href="#i-plus"></use></svg>
            Đơn nghỉ phép
        </a>
    @endcan
    @can('ot.create')
        <a href="{{ route('overtime.create') }}" class="btn btn-orange btn-sm">
            <svg class="icon"><use href="#i-clock"></use></svg>
            Đăng ký OT
        </a>
    @endcan
@endsection

@php
    $greeting = now()->hour < 12 ? 'Chào buổi sáng' : (now()->hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối');
    $trendMax = max(1, max(array_merge($monthlyOt, $monthlyLeave)));

    $segments = [];
    $acc = 0;
    foreach ($leaveByType as $slice) {
        $pct = $typeTotal > 0 ? ($slice['count'] / $typeTotal) * 100 : 0;
        $segments[] = $slice['color'] . ' ' . round($acc, 2) . '% ' . round($acc + $pct, 2) . '%';
        $acc += $pct;
    }
    $donutStyle = $typeTotal > 0 ? implode(', ', $segments) : '#e7edf8 0 100%';

    $iconMap = ['palm' => 'i-sun', 'inbox' => 'i-inbox', 'clock' => 'i-clock', 'users' => 'i-users'];
    $toneMap = ['primary' => '', 'warning' => 'amber', 'success' => 'green', 'info' => 'info', 'danger' => 'red'];
@endphp

@section('content')
    <section class="hero animate-in">
        <div class="hero-content">
            <div class="hero-eyebrow">Everland Group · HR Workspace</div>
            <h1>{{ $greeting }}, <span>{{ $user->name }}</span></h1>
            <p>
                @if ($seesAll)
                    Bạn đang theo dõi toàn bộ hoạt động nghỉ phép và làm thêm giờ của công ty. Hôm nay có {{ $onLeaveToday->count() }} nhân sự đang nghỉ và {{ $pendingApprovals->count() }} đơn đang chờ bạn xử lý.
                @else
                    Đây là tóm tắt hoạt động nghỉ phép và OT của bạn. Bạn còn <strong style="color:#ffc46b">{{ days_label($annualRemaining) }}</strong> phép năm và {{ $myPendingCount ?? 0 }} đơn đang chờ duyệt.
                @endif
            </p>
            <div class="hero-meta">
                <span class="chip"><svg class="icon"><use href="#i-briefcase"></use></svg>{{ $user->employee_code }}</span>
                <span class="chip"><svg class="icon"><use href="#i-branch"></use></svg>{{ $user->department?->name ?? 'Chưa xếp phòng ban' }}</span>
                <span class="chip"><svg class="icon"><use href="#i-shield"></use></svg>{{ $user->role?->name ?? 'Nhân viên' }}</span>
                <span class="chip"><svg class="icon"><use href="#i-calendar"></use></svg>Vào làm {{ vn_date($user->join_date, 'm/Y') }}</span>
            </div>
            <div class="hero-cta">
                @can('leave.create')
                    <a href="{{ route('leave.create') }}" class="btn btn-orange">
                        <svg class="icon"><use href="#i-plus"></use></svg>
                        Tạo đơn nghỉ phép
                    </a>
                @endcan
                @can('ot.create')
                    <a href="{{ route('overtime.create') }}" class="btn btn-outline">
                        <svg class="icon"><use href="#i-clock"></use></svg>
                        Đăng ký làm thêm giờ
                    </a>
                @endcan
                <a href="{{ route('calendar.index') }}" class="btn btn-outline">
                    <svg class="icon"><use href="#i-calendar"></use></svg>
                    Xem lịch tháng
                </a>
            </div>
        </div>
        <div class="hero-profile">
            <span class="avatar">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                @else
                    {{ initials($user->name) }}
                @endif
            </span>
            <strong>{{ $user->name }}</strong>
            <span>{{ $user->position ?: 'Nhân viên' }}</span>
        </div>
    </section>

    <div class="stat-grid animate-in" style="animation-delay: .04s">
        @foreach ($cards as $card)
            <a href="{{ $card['url'] }}" class="stat-card" style="color: inherit">
                <span class="stat-icon {{ $toneMap[$card['tone']] ?? '' }}">
                    <svg class="icon"><use href="#{{ $iconMap[$card['icon']] ?? 'i-grid' }}"></use></svg>
                </span>
                <span class="stat-body">
                    <span class="stat-label">{{ $card['label'] }}</span>
                    <span class="stat-value">{{ $card['value'] }} <small>{{ $card['suffix'] }}</small></span>
                    <span class="stat-hint">{{ $card['hint'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="grid grid-2-1 mt-20">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-inbox"></use></svg> Đơn gần đây của tôi</div>
                    <div class="card-sub">5 đơn nghỉ phép và OT mới nhất</div>
                </div>
                <a href="{{ route('leave.index') }}" class="btn btn-outline btn-sm">Xem tất cả</a>
            </div>
            <div class="card-body flush">
                @forelse ($recentRequests as $item)
                    @php $meta = status_meta($item['status']); @endphp
                    <a href="{{ $item['url'] }}" class="list-item" style="padding: 14px 22px; border-bottom: 1px solid var(--line-soft); color: inherit; display: flex">
                        <span class="list-icon {{ $item['type'] === 'overtime' ? 'orange' : '' }}">
                            <svg class="icon"><use href="#{{ $item['type'] === 'overtime' ? 'i-clock' : 'i-sun' }}"></use></svg>
                        </span>
                        <span class="list-body">
                            <span class="list-title">{{ $item['title'] }} <span class="muted" style="font-weight: 600">· {{ $item['code'] }}</span></span>
                            <span class="list-meta">{{ $item['period'] }}</span>
                        </span>
                        <span class="list-actions"><span class="badge badge-{{ $meta['tone'] }}">{{ $meta['label'] }}</span></span>
                    </a>
                @empty
                    <div class="empty">
                        <div class="empty-icon"><svg class="icon"><use href="#i-inbox"></use></svg></div>
                        <strong>Chưa có đơn nào</strong>
                        <p>Bắt đầu bằng cách tạo đơn nghỉ phép hoặc đăng ký OT.</p>
                    </div>
                @endforelse
            </div>
            <div class="card-foot flex gap-10">
                @can('leave.create')
                    <a href="{{ route('leave.create') }}" class="btn btn-soft btn-sm"><svg class="icon"><use href="#i-plus"></use></svg> Nghỉ phép mới</a>
                @endcan
                @can('ot.create')
                    <a href="{{ route('overtime.create') }}" class="btn btn-outline btn-sm"><svg class="icon"><use href="#i-plus"></use></svg> OT mới</a>
                @endcan
            </div>
        </div>

        <div class="grid" style="align-content: start">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-wallet"></use></svg> Quỹ phép năm {{ now()->year }}</div>
                        <div class="card-sub">{{ $annualType?->name ?? 'Phép năm' }} và các loại có trừ quỹ</div>
                    </div>
                    <a href="{{ route('balances.index') }}" class="btn btn-ghost btn-sm">Chi tiết</a>
                </div>
                <div class="card-body">
                    @forelse ($myBalances as $item)
                        @php
                            $percent = $item['entitled'] > 0 ? min(100, ($item['used'] / $item['entitled']) * 100) : 0;
                        @endphp
                        <div class="mb-16" style="margin-bottom: 16px">
                            <div class="flex items-center justify-between mb-8">
                                <span class="text-sm strong" style="color: var(--ink-soft)">
                                    <span class="legend-dot" style="display:inline-block;margin-right:6px;background: {{ $item['type']->color }}"></span>
                                    {{ $item['type']->name }}
                                </span>
                                <span class="text-xs muted"><b style="color: var(--navy-900)">{{ rtrim(rtrim(number_format($item['remaining'], 1, ',', '.'), '0'), ',') }}</b> / {{ rtrim(rtrim(number_format($item['entitled'], 1, ',', '.'), '0'), ',') }} ngày</span>
                            </div>
                            <div class="progress"><span style="width: {{ $percent }}%; background: {{ $item['type']->color }}"></span></div>
                        </div>
                    @empty
                        <div class="empty" style="padding: 18px 6px">
                            <strong>Chưa cấu hình quỹ phép</strong>
                            <p>Liên hệ HR để được khởi tạo quỹ phép năm.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-calendar"></use></svg> Ngày lễ sắp tới</div>
                        <div class="card-sub">Theo lịch nghỉ của công ty</div>
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($upcomingHolidays as $holiday)
                        <div class="flex items-center gap-12" style="padding: 9px 0; border-bottom: 1px dashed var(--line-soft)">
                            <span class="date-badge orange">
                                <span class="db-month">T{{ $holiday->holiday_date->format('m') }}</span>
                                <span class="db-day">{{ $holiday->holiday_date->format('d') }}</span>
                            </span>
                            <span style="min-width: 0">
                                <span class="strong" style="display: block; font-size: 12.8px">{{ $holiday->name }}</span>
                                <span class="text-xs muted">{{ $holiday->holiday_date->translatedFormat('l') }}</span>
                            </span>
                            <span class="badge badge-muted ml-auto no-dot">{{ $holiday->holiday_date->diffForHumans(null, true) }}</span>
                        </div>
                    @empty
                        <div class="empty" style="padding: 18px 6px">
                            <strong>Không có ngày lễ sắp tới</strong>
                            <p>Lịch lễ đã được cập nhật đầy đủ.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2 mt-20">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-layers"></use></svg> Cơ cấu nghỉ phép {{ now()->year }}</div>
                    <div class="card-sub">Đơn chờ duyệt và đã duyệt theo loại phép</div>
                </div>
                @can('report.view')
                    <a href="{{ route('reports.index') }}" class="btn btn-ghost btn-sm">Báo cáo</a>
                @endcan
            </div>
            <div class="card-body">
                <div class="donut-wrap">
                    <div class="donut" style="background: conic-gradient({{ $donutStyle }})">
                        <div class="donut-center">
                            <strong>{{ $typeTotal }}</strong>
                            <span>lượt nghỉ</span>
                        </div>
                    </div>
                    <div class="legend">
                        @forelse ($leaveByType as $slice)
                            <div class="legend-item">
                                <span class="legend-dot" style="background: {{ $slice['color'] }}"></span>
                                <span class="legend-name">{{ $slice['name'] }}</span>
                                <span class="legend-value">{{ $slice['count'] }} <small>lượt</small></span>
                            </div>
                        @empty
                            <div class="empty" style="padding: 10px 0">
                                <strong>Chưa có dữ liệu nghỉ phép</strong>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-trend"></use></svg> Xu hướng 6 tháng</div>
                    <div class="card-sub">So sánh giờ OT và ngày nghỉ đã duyệt</div>
                </div>
            </div>
            <div class="card-body">
                <div class="bar-chart">
                    @foreach ($monthlyLabels as $index => $label)
                        <div class="bar-col">
                            <div class="bar-duo" title="OT: {{ $monthlyOt[$index] }} giờ · Nghỉ: {{ $monthlyLeave[$index] }} ngày">
                                <span class="bar" style="height: {{ max(3, ($monthlyOt[$index] / $trendMax) * 100) }}%"></span>
                                <span class="bar orange" style="height: {{ max(3, ($monthlyLeave[$index] / $trendMax) * 100) }}%"></span>
                            </div>
                            <span class="bar-label">T{{ (int) substr($label, 0, 2) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="chart-legend-inline">
                    <span><i style="background: #2f6bff"></i>Giờ OT (giờ)</span>
                    <span><i style="background: #f7941d"></i>Ngày nghỉ (ngày)</span>
                    <span class="ml-auto">Tháng {{ substr($monthlyLabels[0] ?? '', 0, 2) }} – {{ substr(end($monthlyLabels) ?: '', 0, 2) }}/{{ now()->year }}</span>
                </div>
            </div>
        </div>
    </div>

    @if ($isApprover && $pendingApprovals->isNotEmpty())
        <div class="card mt-20">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-check"></use></svg> Đơn đang chờ bạn phê duyệt</div>
                    <div class="card-sub">Bước duyệt hiện tại thuộc thẩm quyền của bạn</div>
                </div>
                <a href="{{ route('approvals.index') }}" class="btn btn-primary btn-sm">Vào trang duyệt</a>
            </div>
            <div class="card-body flush">
                @foreach ($pendingApprovals as $approval)
                    @php
                        $doc = $approval->approvable;
                        $isLeaveDoc = $doc instanceof \App\Models\LeaveRequest;
                        $period = $isLeaveDoc
                            ? vn_date($doc->start_date, 'd/m') . ' – ' . vn_date($doc->end_date, 'd/m/Y')
                            : vn_date($doc->ot_date, 'd/m/Y') . ' · ' . substr($doc->start_time, 0, 5) . '–' . substr($doc->end_time, 0, 5);
                    @endphp
                    <div class="list-item" style="padding: 14px 22px; border-bottom: 1px solid var(--line-soft); display: flex">
                        <span class="avatar {{ $isLeaveDoc ? '' : 'orange' }}">
                            @if ($doc->user?->avatarUrl())
                                <img src="{{ $doc->user->avatarUrl() }}" alt="">
                            @else
                                {{ initials($doc->user?->name) }}
                            @endif
                        </span>
                        <div class="list-body">
                            <div class="list-title">
                                {{ $doc->user?->name }}
                                <span class="badge badge-{{ $isLeaveDoc ? 'info' : 'orange' }} no-dot" style="margin-left: 6px">{{ $isLeaveDoc ? 'Nghỉ phép' : 'OT' }}</span>
                            </div>
                            <div class="list-meta">{{ $doc->code }} · {{ $period }} · Bước {{ $doc->current_step }}/{{ $doc->total_steps }}</div>
                        </div>
                        <div class="list-actions flex gap-8">
                            @if ($isLeaveDoc)
                                <a href="{{ route('leave.show', $doc) }}" class="btn btn-outline btn-sm">Xem đơn</a>
                            @else
                                <a href="{{ route('overtime.show', $doc) }}" class="btn btn-outline btn-sm">Xem đơn</a>
                            @endif
                            <form method="POST" action="{{ route('approvals.approve', $approval) }}"
                                  data-confirm="Duyệt {{ $isLeaveDoc ? 'đơn nghỉ phép' : 'đơn OT' }} {{ $doc->code }} ở bước {{ $doc->current_step }}/{{ $doc->total_steps }}?">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <svg class="icon"><use href="#i-check"></use></svg>
                                    Duyệt ngay
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-2 mt-20">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-users"></use></svg> {{ $seesAll ? 'Nhân sự nghỉ hôm nay' : 'Đồng đội nghỉ hôm nay' }}</div>
                    <div class="card-sub">{{ $onLeaveToday->count() }} nhân sự đang nghỉ · {{ now()->format('d/m/Y') }}</div>
                </div>
                <a href="{{ route('calendar.index') }}" class="btn btn-ghost btn-sm">Lịch tháng</a>
            </div>
            <div class="card-body">
                @forelse ($onLeaveToday->take(6) as $leave)
                    <div class="flex items-center gap-12" style="padding: 9px 0; border-bottom: 1px dashed var(--line-soft)">
                        <span class="avatar avatar-sm">
                            @if ($leave->user?->avatarUrl())
                                <img src="{{ $leave->user->avatarUrl() }}" alt="">
                            @else
                                {{ initials($leave->user?->name) }}
                            @endif
                        </span>
                        <div style="min-width: 0">
                            <div class="text-sm strong">{{ $leave->user?->name }}</div>
                            <div class="text-xs muted">{{ $leave->user?->department?->name }} · {{ $leave->code }}</div>
                        </div>
                        <span class="chip ml-auto" style="background: {{ $leave->leaveType?->color }}14; border-color: {{ $leave->leaveType?->color }}33; color: {{ $leave->leaveType?->color }}">
                            {{ $leave->leaveType?->name }}
                        </span>
                        <span class="text-xs muted nowrap">{{ vn_date($leave->start_date, 'd/m') }} – {{ vn_date($leave->end_date, 'd/m') }}</span>
                    </div>
                    @if ($loop->last && $onLeaveToday->count() > 6)
                        <div class="text-xs muted mt-12">+ {{ $onLeaveToday->count() - 6 }} nhân sự khác đang nghỉ</div>
                    @endif
                @empty
                    <div class="empty">
                        <div class="empty-icon"><svg class="icon"><use href="#i-sun"></use></svg></div>
                        <strong>Hôm nay không ai nghỉ</strong>
                        <p>Toàn bộ đội ngũ đang có mặt làm việc.</p>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($seesAll)
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-branch"></use></svg> Nhân sự theo phòng ban</div>
                        <div class="card-sub">Top {{ $headcount->count() }} phòng ban đông nhân sự nhất</div>
                    </div>
                    @can('department.view')
                        <a href="{{ route('admin.departments.index') }}" class="btn btn-ghost btn-sm">Cơ cấu</a>
                    @endcan
                </div>
                <div class="card-body">
                    @php $maxHeadcount = max(1, $headcount->max('users_count') ?? 1); @endphp
                    @forelse ($headcount as $department)
                        <div class="mb-12" style="margin-bottom: 13px">
                            <div class="flex items-center justify-between mb-8">
                                <span class="text-sm strong">{{ $department->name }}</span>
                                <span class="text-xs muted"><b style="color: var(--navy-900)">{{ $department->users_count }}</b> người</span>
                            </div>
                            <div class="progress"><span style="width: {{ ($department->users_count / $maxHeadcount) * 100 }}%"></span></div>
                        </div>
                    @empty
                        <div class="empty" style="padding: 18px 6px">
                            <strong>Chưa có dữ liệu phòng ban</strong>
                        </div>
                    @endforelse
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-bell"></use></svg> Thông báo gần đây</div>
                        <div class="card-sub">Cập nhật mới nhất dành cho bạn</div>
                    </div>
                    <a href="{{ route('notifications.index') }}" class="btn btn-ghost btn-sm">Tất cả</a>
                </div>
                <div class="card-body">
                    @forelse ($recentNotifications as $notification)
                        <div class="flex gap-10" style="padding: 9px 0; border-bottom: 1px dashed var(--line-soft); align-items: flex-start">
                            <span class="list-icon" style="width: 32px; height: 32px; border-radius: 9px">
                                <svg class="icon" style="width: 15px; height: 15px"><use href="#i-bell"></use></svg>
                            </span>
                            <div style="min-width: 0">
                                <div class="text-sm strong" style="font-weight: 650">{{ $notification->title }}</div>
                                <div class="text-xs muted">{{ $notification->created_at?->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty">
                            <div class="empty-icon"><svg class="icon"><use href="#i-bell"></use></svg></div>
                            <strong>Không có thông báo</strong>
                            <p>Bạn sẽ nhận thông báo khi có đơn cần duyệt.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
@endsection
