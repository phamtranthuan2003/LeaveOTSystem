@extends('layouts.app')

@section('title', 'Thống kê & báo cáo')
@section('subtitle', 'Phân tích dữ liệu nghỉ phép và làm thêm giờ theo phòng ban, thời gian')

@section('actions')
    @can('report.export')
        <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-outline btn-sm">
            <svg class="icon"><use href="#i-download"></use></svg>
            Xuất Excel
        </a>
    @endcan
@endsection

@section('content')
    @php
        $isLeave = $filters['tab'] === 'leave';
        $maxMonthly = max(1, max($monthly['values'] ?: [0]));
        $maxDepartment = max(1, collect($byDepartment)->max('value') ?? 1);
        $typeTotal = collect($byType)->sum('value');
        $tabUrl = fn ($tab) => request()->fullUrlWithQuery(['tab' => $tab, 'page' => null]);
    @endphp

    <div class="tab-link-row">
        <div class="tabs">
            <a href="{{ $tabUrl('leave') }}" class="tab {{ $isLeave ? 'active' : '' }}">
                <svg class="icon"><use href="#i-sun"></use></svg>
                Nghỉ phép
            </a>
            <a href="{{ $tabUrl('overtime') }}" class="tab {{ $isLeave ? '' : 'active' }}">
                <svg class="icon"><use href="#i-clock"></use></svg>
                Làm thêm giờ
            </a>
        </div>
        <span class="text-sm muted">Kỳ báo cáo: {{ $filters['year'] }}{{ $filters['month'] ? ' · Tháng ' . $filters['month'] : '' }}</span>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="filter-bar">
        <input type="hidden" name="tab" value="{{ $filters['tab'] }}">
        <div class="field search">
            <label for="q">Tìm kiếm</label>
            <div class="input-group">
                <svg class="icon"><use href="#i-search"></use></svg>
                <input id="q" name="q" type="text" class="input" value="{{ $filters['q'] }}" placeholder="Mã đơn, tên nhân sự...">
            </div>
        </div>
        <div class="field">
            <label for="year">Năm</label>
            <select id="year" name="year" class="select">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($filters['year'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="month">Tháng</label>
            <select id="month" name="month" class="select">
                <option value="">Cả năm</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($filters['month'] === $m)>Tháng {{ $m }}</option>
                @endfor
            </select>
        </div>
        <div class="field">
            <label for="department_id">Phòng ban</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">Tất cả</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Trạng thái</label>
            <select id="status" name="status" class="select">
                <option value="">Tất cả</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Chờ duyệt</option>
                <option value="approved" @selected($filters['status'] === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected($filters['status'] === 'rejected')>Từ chối</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>Đã huỷ</option>
            </select>
        </div>
        @if ($isLeave)
            <div class="field">
                <label for="leave_type_id">Loại phép</label>
                <select id="leave_type_id" name="leave_type_id" class="select">
                    <option value="">Tất cả</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected($filters['leave_type_id'] === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <svg class="icon"><use href="#i-filter"></use></svg>
                Áp dụng
            </button>
            <a href="{{ route('reports.index', ['tab' => $filters['tab']]) }}" class="btn btn-outline btn-sm">Xoá lọc</a>
        </div>
    </form>

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-file"></use></svg></span>
            <div>
                <div class="it-value">{{ $summary['total'] }}</div>
                <div class="it-label">Tổng số đơn</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon amber"><svg class="icon"><use href="#i-clock"></use></svg></span>
            <div>
                <div class="it-value">{{ $summary['pending'] }}</div>
                <div class="it-label">Chờ duyệt</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon green"><svg class="icon"><use href="#i-check"></use></svg></span>
            <div>
                <div class="it-value">{{ $summary['approved'] }}</div>
                <div class="it-label">Đã duyệt</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon {{ $isLeave ? 'info' : 'orange' }}"><svg class="icon"><use href="#{{ $isLeave ? 'i-sun' : 'i-trend' }}"></use></svg></span>
            <div>
                <div class="it-value">{{ rtrim(rtrim(number_format($summary['value'], 1, ',', '.'), '0'), ',') }}</div>
                <div class="it-label">{{ $isLeave ? 'Ngày nghỉ đã duyệt' : 'Giờ OT đã duyệt' }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-2-1">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-trend"></use></svg> Xu hướng theo tháng · {{ $filters['year'] }}</div>
                    <div class="card-sub">{{ $isLeave ? 'Số ngày nghỉ đã duyệt mỗi tháng' : 'Số giờ OT đã duyệt mỗi tháng' }}</div>
                </div>
            </div>
            <div class="card-body">
                <div class="bar-chart" style="height: 200px">
                    @foreach ($monthly['labels'] as $index => $label)
                        @php $value = $monthly['values'][$index] ?? 0; @endphp
                        <div class="bar-col">
                            <div class="bar-duo" title="{{ $label }}: {{ $value }} {{ $summary['unit'] }}">
                                <span class="bar {{ $isLeave ? '' : 'orange' }}" style="height: {{ max(3, ($value / $maxMonthly) * 100) }}%; width: 18px; max-width: 18px"></span>
                            </div>
                            <span class="bar-label">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="chart-legend-inline">
                    <span><i style="background: {{ $isLeave ? '#2f6bff' : '#f7941d' }}"></i>{{ $isLeave ? 'Ngày nghỉ' : 'Giờ OT' }} · đơn vị {{ $summary['unit'] }}</span>
                    <span class="ml-auto">Cao nhất: {{ rtrim(rtrim(number_format($maxMonthly, 1, ',', '.'), '0'), ',') }} {{ $summary['unit'] }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-branch"></use></svg> Theo phòng ban</div>
                    <div class="card-sub">Top phòng ban có phát sinh nhiều nhất</div>
                </div>
            </div>
            <div class="card-body">
                @forelse ($byDepartment as $row)
                    <div class="mb-12" style="margin-bottom: 13px">
                        <div class="flex justify-between text-sm mb-8">
                            <span class="strong" style="color: var(--ink-soft)">{{ $row['name'] }}</span>
                            <span class="text-xs muted"><b style="color: var(--ink)">{{ rtrim(rtrim(number_format($row['value'], 1, ',', '.'), '0'), ',') }}</b> {{ $summary['unit'] }} · {{ $row['count'] }} đơn</span>
                        </div>
                        <div class="progress"><span class="bar-fill" style="width: {{ ($row['value'] / $maxDepartment) * 100 }}%; background: {{ $isLeave ? 'linear-gradient(90deg,#4b83ff,#1d4ed8)' : 'linear-gradient(90deg,#ffb955,#ef8a08)' }}"></span></div>
                    </div>
                @empty
                    <div class="empty" style="padding: 20px">
                        <strong>Chưa có dữ liệu phòng ban</strong>
                        <p>Chưa có đơn được duyệt trong kỳ báo cáo.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-2 mt-20">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-layers"></use></svg> Phân bổ trạng thái</div>
                    <div class="card-sub">Trong kỳ báo cáo đã chọn</div>
                </div>
            </div>
            <div class="card-body">
                @php
                    $statusRows = [
                        ['key' => 'approved', 'label' => 'Đã duyệt', 'color' => '#0f9d68'],
                        ['key' => 'pending', 'label' => 'Chờ duyệt', 'color' => '#f0a020'],
                        ['key' => 'rejected', 'label' => 'Từ chối', 'color' => '#d83b4b'],
                        ['key' => 'cancelled', 'label' => 'Đã huỷ', 'color' => '#8b98b3'],
                    ];
                @endphp
                @foreach ($statusRows as $row)
                    @php
                        $count = match ($row['key']) {
                            'approved' => $summary['approved'],
                            'pending' => $summary['pending'],
                            'rejected' => $summary['rejected'],
                            default => max(0, $summary['total'] - $summary['approved'] - $summary['pending'] - $summary['rejected']),
                        };
                        $pct = $summary['total'] > 0 ? ($count / $summary['total']) * 100 : 0;
                    @endphp
                    <div class="mb-12" style="margin-bottom: 13px">
                        <div class="flex justify-between text-sm mb-8">
                            <span class="strong" style="color: var(--ink-soft)">{{ $row['label'] }}</span>
                            <span class="text-xs muted"><b style="color: var(--ink)">{{ $count }}</b> đơn · {{ number_format($pct, 0) }}%</span>
                        </div>
                        <div class="progress"><span style="width: {{ $pct }}%; background: {{ $row['color'] }}"></span></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-wallet"></use></svg> {{ $isLeave ? 'Theo loại nghỉ phép' : 'Quy đổi hệ số OT' }}</div>
                    <div class="card-sub">{{ $isLeave ? 'Số ngày nghỉ đã duyệt theo loại' : 'Theo loại ngày làm thêm' }}</div>
                </div>
            </div>
            <div class="card-body">
                @if ($isLeave)
                    @forelse ($byType as $row)
                        @php $pct = $typeTotal > 0 ? ($row['value'] / $typeTotal) * 100 : 0; @endphp
                        <div class="flex items-center gap-12" style="padding: 8px 0; border-bottom: 1px dashed var(--line-soft)">
                            <span class="legend-dot" style="background: {{ $row['color'] }}; width: 10px; height: 10px"></span>
                            <span class="text-sm strong" style="flex: 1">{{ $row['name'] }}</span>
                            <div class="progress" style="width: 110px"><span style="width: {{ $pct }}%; background: {{ $row['color'] }}"></span></div>
                            <span class="text-xs muted" style="min-width: 78px; text-align: right"><b style="color: var(--ink)">{{ rtrim(rtrim(number_format($row['value'], 1, ',', '.'), '0'), ',') }}</b> ngày</span>
                        </div>
                    @empty
                        <div class="empty" style="padding: 20px">
                            <strong>Chưa có dữ liệu loại phép</strong>
                        </div>
                    @endforelse
                @else
                    <div class="list">
                        <div class="list-item">
                            <span class="list-icon"><svg class="icon"><use href="#i-clock"></use></svg></span>
                            <div class="list-body">
                                <div class="list-title">Ngày làm việc bình thường</div>
                                <div class="list-meta">Hệ số x{{ rtrim(rtrim(number_format((float) setting('ot_rate_weekday', 1.5), 1, ',', '.'), '0'), ',') }}</div>
                            </div>
                        </div>
                        <div class="list-item">
                            <span class="list-icon orange"><svg class="icon"><use href="#i-calendar"></use></svg></span>
                            <div class="list-body">
                                <div class="list-title">Ngày cuối tuần</div>
                                <div class="list-meta">Hệ số x{{ rtrim(rtrim(number_format((float) setting('ot_rate_weekend', 2), 1, ',', '.'), '0'), ',') }}</div>
                            </div>
                        </div>
                        <div class="list-item">
                            <span class="list-icon red"><svg class="icon"><use href="#i-sun"></use></svg></span>
                            <div class="list-body">
                                <div class="list-title">Ngày lễ</div>
                                <div class="list-meta">Hệ số x{{ rtrim(rtrim(number_format((float) setting('ot_rate_holiday', 3), 1, ',', '.'), '0'), ',') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="note-box mt-12">
                        Tổng giờ OT đã duyệt trong kỳ: <b>{{ rtrim(rtrim(number_format($summary['value'], 1, ',', '.'), '0'), ',') }} giờ</b>.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card mt-20">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-inbox"></use></svg> Chi tiết {{ $isLeave ? 'đơn nghỉ phép' : 'đơn OT' }}</div>
                <div class="card-sub">{{ $items->total() }} bản ghi phù hợp bộ lọc</div>
            </div>
            @can('report.export')
                <a href="{{ route('reports.export.details', request()->query()) }}" class="btn btn-outline btn-sm">
                    <svg class="icon"><use href="#i-download"></use></svg>
                    Tải Excel
                </a>
            @endcan
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Nhân sự</th>
                            <th>Phòng ban</th>
                            @if ($isLeave)
                                <th>Loại phép</th>
                                <th>Thời gian</th>
                                <th>Số ngày</th>
                            @else
                                <th>Ngày OT</th>
                                <th>Khung giờ</th>
                                <th>Số giờ</th>
                                <th>Loại</th>
                            @endif
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $meta = status_meta($item->status);
                                $docIsLeave = $item instanceof \App\Models\LeaveRequest;
                            @endphp
                            <tr>
                                <td class="cell-main">{{ $item->code }}</td>
                                <td>{{ $item->user?->name }}</td>
                                <td class="text-sm muted">{{ $item->user?->department?->name ?? '—' }}</td>
                                @if ($isLeave)
                                    <td>
                                        <span class="chip">
                                            <span class="legend-dot" style="background: {{ $item->leaveType?->color ?? '#2f6bff' }}"></span>
                                            {{ $item->leaveType?->name }}
                                        </span>
                                    </td>
                                    <td>{{ vn_date($item->start_date, 'd/m') }} – {{ vn_date($item->end_date, 'd/m/Y') }}</td>
                                    <td class="num">{{ rtrim(rtrim(number_format($item->total_days, 1, ',', '.'), '0'), ',') }}</td>
                                @else
                                    <td>{{ vn_date($item->ot_date, 'd/m/Y') }}</td>
                                    <td>{{ substr($item->start_time, 0, 5) }} – {{ substr($item->end_time, 0, 5) }}</td>
                                    <td class="num">{{ rtrim(rtrim(number_format($item->hours, 2, ',', '.'), '0'), ',') }}</td>
                                    <td><span class="badge badge-{{ ot_type_meta($item->ot_type)['tone'] }} no-dot">{{ ot_type_meta($item->ot_type)['label'] }}</span></td>
                                @endif
                                <td><span class="badge badge-{{ $meta['tone'] }}">{{ $meta['label'] }}</span></td>
                                <td>
                                    <div class="cell-actions">
                                        <a href="{{ $docIsLeave ? route('leave.show', $item) : route('overtime.show', $item) }}" class="btn btn-outline btn-sm">Xem</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isLeave ? 8 : 9 }}">
                                    <div class="empty" style="padding: 30px">
                                        <strong>Không có dữ liệu phù hợp</strong>
                                        <p>Thử thay đổi kỳ báo cáo hoặc bộ lọc.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($items->hasPages())
            {{ $items->links() }}
        @endif
    </div>
@endsection
