@extends('layouts.app')

@section('title', 'Đội nhóm của tôi')
@section('subtitle', $isManagerView ? 'Thành viên trong cây quản lý của bạn' : 'Đồng nghiệp cùng phòng ban')

@section('content')
    @php $tones = ['blue', 'orange', 'green', 'purple', 'red']; @endphp

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['members'] }}</div>
                <div class="it-label">{{ $isManagerView ? 'Thành viên cấp dưới' : 'Đồng nghiệp cùng phòng' }}</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon green"><svg class="icon"><use href="#i-sun"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['on_leave'] }}</div>
                <div class="it-label">Đang nghỉ hôm nay</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon amber"><svg class="icon"><use href="#i-inbox"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['pending'] }}</div>
                <div class="it-label">Đơn đang chờ duyệt</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon orange"><svg class="icon"><use href="#i-clock"></use></svg></span>
            <div>
                <div class="it-value">{{ rtrim(rtrim(number_format($stats['ot_hours'], 1, ',', '.'), '0'), ',') }}</div>
                <div class="it-label">Giờ OT đã duyệt tháng này</div>
            </div>
        </div>
    </div>

    <div class="page-head">
        <div>
            <h1>{{ $isManagerView ? 'Cây quản lý của bạn' : 'Đồng nghiệp cùng phòng ban' }}</h1>
            <p>
                {{ $isManagerView
                    ? 'Bao gồm toàn bộ nhân sự trực thuộc nhiều cấp dưới quyền bạn.'
                    : 'Bạn chưa có nhân sự trực thuộc, đang hiển thị đồng nghiệp cùng phòng ban.' }}
                @if ($annualType) Quỹ phép năm {{ now()->year }} theo loại {{ $annualType->name }}. @endif
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('calendar.index') }}" class="btn btn-outline btn-sm">
                <svg class="icon"><use href="#i-calendar"></use></svg>
                Lịch nghỉ tháng
            </a>
        </div>
    </div>

    <div class="grid grid-3">
        @forelse ($members as $member)
            @php
                $balance = $balances->get($member->id);
                $entitled = $balance ? $balance->totalEntitled() : ($annualType?->default_days ?? 0);
                $used = $balance?->used_days ?? 0;
                $remaining = $balance ? $balance->remaining() : $entitled;
                $percent = $entitled > 0 ? min(100, ($used / $entitled) * 100) : 0;
                $pendingCount = (int) ($pendingLeaves[$member->id] ?? 0) + (int) ($pendingOts[$member->id] ?? 0);
                $todayLeave = $onLeaveToday->get($member->id);
            @endphp
            <div class="card">
                <div class="card-body">
                    <div class="flex items-start gap-12">
                        <span class="avatar avatar-lg {{ $tones[$loop->index % count($tones)] }}">
                            @if ($member->avatarUrl())
                                <img src="{{ $member->avatarUrl() }}" alt="{{ $member->name }}">
                            @else
                                {{ initials($member->name) }}
                            @endif
                        </span>
                        <div style="min-width: 0; flex: 1">
                            <div style="font-size: 15px; font-weight: 800; color: var(--navy-900)">{{ $member->name }}</div>
                            <div class="text-xs muted">{{ $member->position ?: 'Nhân viên' }}</div>
                            <div class="text-xs muted">{{ $member->department?->name }}</div>
                        </div>
                        @if ($todayLeave)
                            <span class="badge badge-success no-dot">Đang nghỉ</span>
                        @endif
                    </div>

                    <div class="flex gap-6 flex-wrap mt-12">
                        <span class="badge badge-{{ role_tone($member->role?->slug) }} no-dot">{{ $member->role?->name }}</span>
                        <span class="chip"><svg class="icon"><use href="#i-mail"></use></svg>{{ $member->employee_code }}</span>
                        @if ($pendingCount > 0)
                            <span class="badge badge-warning no-dot">{{ $pendingCount }} đơn chờ duyệt</span>
                        @endif
                    </div>

                    @if ($todayLeave)
                        <div class="note-box mt-12" style="background: #eefaf4; border-color: #bfe9d8; color: #086848">
                            <strong>{{ $todayLeave->leaveType?->name }}</strong>
                            · {{ vn_date($todayLeave->start_date, 'd/m') }} – {{ vn_date($todayLeave->end_date, 'd/m/Y') }}
                        </div>
                    @endif

                    <div class="mt-16">
                        <div class="flex justify-between text-xs muted mb-8">
                            <span>Quỹ phép năm {{ now()->year }}</span>
                            <span><b style="color: var(--navy-900)">{{ rtrim(rtrim(number_format($remaining, 1, ',', '.'), '0'), ',') }}</b> / {{ rtrim(rtrim(number_format($entitled, 1, ',', '.'), '0'), ',') }} ngày</span>
                        </div>
                        <div class="progress"><span style="width: {{ $percent }}%"></span></div>
                    </div>

                    <div class="meta-row mt-12" style="font-size: 11.5px">
                        <span>Cấp trên: <b>{{ $member->manager?->name ?? '—' }}</b></span>
                        <span>Vào làm: <b>{{ vn_date($member->join_date, 'm/Y') }}</b></span>
                    </div>
                </div>
                <div class="card-foot flex justify-between items-center">
                    <span class="text-xs muted">{{ $member->phone ?: $member->email }}</span>
                    <a href="{{ route('calendar.index', ['month' => now()->format('Y-m')]) }}" class="btn btn-ghost btn-sm">
                        <svg class="icon"><use href="#i-calendar"></use></svg>
                        Lịch
                    </a>
                </div>
            </div>
        @empty
            <div class="card span-2">
                <div class="empty" style="padding: 48px 20px">
                    <div class="empty-icon"><svg class="icon"><use href="#i-users"></use></svg></div>
                    <strong>Chưa có thành viên nào</strong>
                    <p>Phòng ban chưa có nhân sự hoặc bạn chưa được phân cấp quản lý.</p>
                </div>
            </div>
        @endforelse
    </div>

    @if ($members->hasPages())
        <div class="card mt-20">{{ $members->links() }}</div>
    @endif
@endsection
