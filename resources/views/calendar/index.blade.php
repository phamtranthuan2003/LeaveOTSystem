@extends('layouts.app')

@section('title', 'Lịch nghỉ & làm thêm giờ')
@section('subtitle', $seesAll ? 'Toàn công ty · ' . $monthLabel : 'Bạn và đội nhóm · ' . $monthLabel)

@section('actions')
    @can('leave.create')
        <a href="{{ route('leave.create') }}" class="btn btn-primary btn-sm">
            <svg class="icon"><use href="#i-plus"></use></svg>
            Tạo đơn nghỉ
        </a>
    @endcan
@endsection

@section('content')
    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon green"><svg class="icon"><use href="#i-sun"></use></svg></span>
            <div>
                <div class="it-value">{{ $approvedLeaveCount }}</div>
                <div class="it-label">Đơn nghỉ trong lịch</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon orange"><svg class="icon"><use href="#i-clock"></use></svg></span>
            <div>
                <div class="it-value">{{ $otCount }}</div>
                <div class="it-label">Ca OT đã duyệt</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-calendar"></use></svg></span>
            <div>
                <div class="it-value">{{ $month->copy()->daysInMonth }}</div>
                <div class="it-label">Ngày trong {{ $monthLabel }}</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon info"><svg class="icon"><use href="#i-users"></use></svg></span>
            <div>
                <div class="it-value">{{ $seesAll ? 'Công ty' : 'Đội nhóm' }}</div>
                <div class="it-label">Phạm vi hiển thị</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="cal-nav">
                <a href="{{ route('calendar.index', ['month' => $prevMonth]) }}" class="btn btn-outline btn-icon" aria-label="Tháng trước">
                    <svg class="icon"><use href="#i-chevron-left"></use></svg>
                </a>
                <div class="cal-month">{{ $monthLabel }}</div>
                <a href="{{ route('calendar.index', ['month' => $nextMonth]) }}" class="btn btn-outline btn-icon" aria-label="Tháng sau">
                    <svg class="icon"><use href="#i-chevron-right"></use></svg>
                </a>
                <a href="{{ route('calendar.index') }}" class="btn btn-ghost btn-sm">Hôm nay</a>
            </div>
            <form method="GET" action="{{ route('calendar.index') }}" class="flex gap-8 items-center">
                <input type="month" name="month" class="input" style="min-height: 34px; padding: 6px 10px; width: 165px"
                       value="{{ $month->format('Y-m') }}" data-auto-submit>
            </form>
        </div>

        <div class="card-body tight">
            <div class="cal-legend flex justify-between flex-wrap">
                <div class="flex gap-16 flex-wrap">
                    <span><i style="background: #2f6bff"></i>Nghỉ phép đã duyệt</span>
                    <span><i style="background: #2f6bff; opacity:.45; border:1px dashed #2f6bff"></i>Đơn của tôi chờ duyệt</span>
                    <span><i style="background: #f7941d"></i>Ca OT đã duyệt</span>
                    <span><i style="background: #ffe3b8"></i>Ngày lễ</span>
                    <span><i style="background: #f7faff; border: 1px solid #d8e3f5"></i>Thứ 7 làm buổi sáng</span>
                </div>
                @if ($seesAll)
                    <span class="chip"><svg class="icon"><use href="#i-eye"></use></svg> Bạn có quyền xem toàn công ty</span>
                @else
                    <span class="chip"><svg class="icon"><use href="#i-users"></use></svg> Chỉ hiển thị bạn và đội nhóm</span>
                @endif
            </div>
        </div>

        <div class="calendar-wrap">
            <div class="calendar">
                <div class="cal-head">
                    <div>T2</div>
                    <div>T3</div>
                    <div>T4</div>
                    <div>T5</div>
                    <div>T6</div>
                    <div>T7 <span class="cal-half-day">Sáng</span></div>
                    <div class="weekend">CN</div>
                </div>
                <div class="cal-grid">
                    @foreach ($weeks as $week)
                        @foreach ($week as $key => $day)
                            @php
                                $allEvents = array_merge($day['leaves'], $day['ots']);
                                $visibleEvents = array_slice($allEvents, 0, 3);
                                $remaining = count($allEvents) - count($visibleEvents);
                                $dayClass = trim(
                                    ($day['in_month'] ? '' : 'out')
                                    . ($day['is_weekend'] ? ' weekend' : '')
                                    . ($day['is_saturday'] ? ' saturday' : '')
                                    . ($day['is_today'] ? ' today' : '')
                                    . ($day['holiday'] ? ' holiday' : '')
                                );
                                $dayTitle = collect($allEvents)->map(fn ($e) => $e['name'] . ' · ' . $e['title'])->implode("\n");
                            @endphp
                            <div class="cal-day {{ $dayClass }}" @if ($dayTitle) title="{{ $dayTitle }}" @endif>
                                <div class="cal-date-row">
                                    <span class="cal-date">{{ $day['date']->day }}</span>
                                    @if ($day['is_today'])
                                        <span class="badge badge-primary no-dot" style="font-size: 9px; padding: 2px 5px">Hôm nay</span>
                                    @elseif (count($allEvents) > 0)
                                        <span class="text-xs muted" style="font-size: 9.5px">{{ count($allEvents) }}</span>
                                    @endif
                                </div>
                                @if ($day['holiday'])
                                    <div class="cal-holiday-name">{{ $day['holiday']->name }}</div>
                                @endif
                                <div class="cal-events">
                                    @foreach ($visibleEvents as $event)
                                        <a href="{{ $event['url'] }}"
                                           class="cal-event {{ $event['type'] === 'overtime' ? 'ot' : '' }} {{ $event['status'] === 'pending' ? 'pending' : '' }}"
                                           style="{{ $event['type'] === 'leave' ? 'background:' . $event['color'] . '14;color:' . $event['color'] . ';border-left-color:' . $event['color'] : '' }}"
                                           title="{{ $event['name'] }} · {{ $event['title'] }} · {{ $event['meta'] }}">
                                            <span class="ce-dot"></span>
                                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $event['name'] }}</span>
                                        </a>
                                    @endforeach
                                    @if ($remaining > 0)
                                        <span class="cal-more">+{{ $remaining }} khác</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2 mt-20">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-info"></use></svg> Hướng dẫn sử dụng lịch</div>
                </div>
            </div>
            <div class="card-body">
                <div class="list">
                    <div class="list-item">
                        <span class="list-icon green"><svg class="icon"><use href="#i-sun"></use></svg></span>
                        <div class="list-body">
                            <div class="list-title">Nhấn vào tên nhân sự để xem đơn nghỉ</div>
                            <div class="list-meta">Đơn đang chờ duyệt của bạn hiển thị với viền nét đứt.</div>
                        </div>
                    </div>
                    <div class="list-item">
                        <span class="list-icon orange"><svg class="icon"><use href="#i-clock"></use></svg></span>
                        <div class="list-body">
                            <div class="list-title">Ca OT đã duyệt hiển thị theo khung giờ</div>
                            <div class="list-meta">Rê chuột lên từng ca để xem nhanh người đăng ký và mã đơn.</div>
                        </div>
                    </div>
                    <div class="list-item">
                        <span class="list-icon purple"><svg class="icon"><use href="#i-calendar"></use></svg></span>
                        <div class="list-body">
                            <div class="list-title">Ngày lễ được tô màu cam nhạt</div>
                            <div class="list-meta">Hệ thống tự trừ ngày lễ khi tính số ngày nghỉ và hệ số OT.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-arrow-right"></use></svg> Đi tới</div>
                    <div class="card-sub">Các trang liên quan đến lịch</div>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-2" style="gap: 10px">
                    <a href="{{ route('leave.index') }}" class="btn btn-outline"><svg class="icon"><use href="#i-sun"></use></svg> Đơn nghỉ phép</a>
                    <a href="{{ route('overtime.index') }}" class="btn btn-outline"><svg class="icon"><use href="#i-clock"></use></svg> Đơn OT</a>
                    @can('leave.approve')
                        <a href="{{ route('approvals.index') }}" class="btn btn-outline"><svg class="icon"><use href="#i-check"></use></svg> Duyệt đơn</a>
                    @endcan
                    @can('team.view')
                        <a href="{{ route('team.index') }}" class="btn btn-outline"><svg class="icon"><use href="#i-users"></use></svg> Đội nhóm</a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection
