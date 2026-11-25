@extends('layouts.app')

@section('title', 'Quỹ phép của tôi')
@section('subtitle', 'Theo dõi số ngày phép được hưởng, đã dùng và còn lại')

@section('actions')
    @can('leave.create')
        <a href="{{ route('leave.create') }}" class="btn btn-primary btn-sm">
            <svg class="icon"><use href="#i-plus"></use></svg>
            Tạo đơn nghỉ
        </a>
    @endcan
@endsection

@section('content')
    @php
        $sumEntitled = $balances->sum('entitled');
        $sumUsed = $balances->sum('used');
        $sumRemaining = $balances->sum('remaining');
    @endphp

    <div class="flex justify-between items-center flex-wrap gap-12 mb-20">
        <div class="info-strip" style="flex: 1">
            <div class="info-tile">
                <span class="stat-icon"><svg class="icon"><use href="#i-wallet"></use></svg></span>
                <div>
                    <div class="it-value">{{ rtrim(rtrim(number_format($sumEntitled, 1, ',', '.'), '0'), ',') }}</div>
                    <div class="it-label">Ngày được hưởng</div>
                </div>
            </div>
            <div class="info-tile">
                <span class="stat-icon amber"><svg class="icon"><use href="#i-trend"></use></svg></span>
                <div>
                    <div class="it-value">{{ rtrim(rtrim(number_format($sumUsed, 1, ',', '.'), '0'), ',') }}</div>
                    <div class="it-label">Ngày đã dùng</div>
                </div>
            </div>
            <div class="info-tile">
                <span class="stat-icon green"><svg class="icon"><use href="#i-check"></use></svg></span>
                <div>
                    <div class="it-value">{{ rtrim(rtrim(number_format($sumRemaining, 1, ',', '.'), '0'), ',') }}</div>
                    <div class="it-label">Ngày còn lại</div>
                </div>
            </div>
        </div>
        <form method="GET" action="{{ route('balances.index') }}" class="flex gap-8 items-center">
            <label for="year" class="text-xs muted strong" style="text-transform: uppercase; letter-spacing: .06em">Năm</label>
            <select id="year" name="year" class="select" style="min-height: 38px; width: 110px" data-auto-submit>
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-2">
        @forelse ($balances as $item)
            @php
                $percent = $item['entitled'] > 0 ? min(100, ($item['used'] / $item['entitled']) * 100) : 0;
            @endphp
            <div class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-12">
                        <div class="flex items-center gap-10">
                            <span class="legend-dot" style="width: 12px; height: 12px; border-radius: 4px; background: {{ $item['type']->color }}"></span>
                            <div>
                                <div class="card-title" style="font-size: 15px">{{ $item['type']->name }}</div>
                                <div class="card-sub">{{ $item['type']->code }} · Năm {{ $year }}</div>
                            </div>
                        </div>
                        <span class="badge badge-{{ $item['remaining'] > 0 ? 'success' : 'danger' }} no-dot">
                            {{ $item['remaining'] > 0 ? 'Còn khả dụng' : 'Đã hết quỹ' }}
                        </span>
                    </div>

                    <div class="flex items-end justify-between mt-16">
                        <div>
                            <div class="text-xs muted strong" style="text-transform: uppercase; letter-spacing: .06em">Còn lại</div>
                            <div style="font-size: 30px; font-weight: 800; color: var(--navy-900); line-height: 1.15">
                                {{ rtrim(rtrim(number_format($item['remaining'], 1, ',', '.'), '0'), ',') }}
                                <span class="text-sm muted" style="font-weight: 600">ngày</span>
                            </div>
                        </div>
                        <div class="text-right text-xs muted">
                            <div>Được hưởng <b style="color: var(--ink)">{{ rtrim(rtrim(number_format($item['entitled'], 1, ',', '.'), '0'), ',') }}</b></div>
                            <div>Đã dùng <b style="color: var(--ink)">{{ rtrim(rtrim(number_format($item['used'], 1, ',', '.'), '0'), ',') }}</b></div>
                            @if ($item['balance']?->carried_over)
                                <div>Chuyển từ năm trước <b style="color: var(--ink)">{{ rtrim(rtrim(number_format($item['balance']->carried_over, 1, ',', '.'), '0'), ',') }}</b></div>
                            @endif
                        </div>
                    </div>

                    <div class="progress mt-12"><span style="width: {{ $percent }}%; background: {{ $item['type']->color }}"></span></div>
                    <div class="flex justify-between text-xs muted mt-8">
                        <span>{{ $percent > 0 ? number_format($percent, 0) . '% đã sử dụng' : 'Chưa sử dụng ngày nào' }}</span>
                        <span>{{ $item['type']->requires_attachment ? 'Yêu cầu đính kèm giấy tờ' : '' }}</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="card span-2">
                <div class="empty" style="padding: 48px 20px">
                    <div class="empty-icon"><svg class="icon"><use href="#i-wallet"></use></svg></div>
                    <strong>Chưa có quỹ phép cho năm {{ $year }}</strong>
                    <p>Liên hệ HR để được khởi tạo quỹ phép năm.</p>
                </div>
            </div>
        @endforelse
    </div>

    <div class="grid grid-2 mt-20">
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-layers"></use></svg> Cơ cấu sử dụng năm {{ $year }}</div>
                    <div class="card-sub">Chỉ tính các đơn đã được duyệt</div>
                </div>
                <span class="badge badge-muted no-dot">{{ days_label($totalUsed) }} đã dùng</span>
            </div>
            <div class="card-body">
                @forelse ($usage as $row)
                    @php $pct = $totalUsed > 0 ? ($row['days'] / $totalUsed) * 100 : 0; @endphp
                    <div class="mb-12" style="margin-bottom: 13px">
                        <div class="flex justify-between text-sm mb-8">
                            <span class="strong" style="color: var(--ink-soft)">
                                <span class="legend-dot" style="display:inline-block;margin-right:6px;background: {{ $row['color'] }}"></span>
                                {{ $row['name'] }}
                            </span>
                            <span class="text-xs muted"><b style="color: var(--ink)">{{ days_label($row['days']) }}</b> · {{ $row['count'] }} đơn</span>
                        </div>
                        <div class="progress"><span style="width: {{ $pct }}%; background: {{ $row['color'] }}"></span></div>
                    </div>
                @empty
                    <div class="empty" style="padding: 24px">
                        <strong>Chưa dùng ngày phép nào</strong>
                        <p>Quỹ phép của bạn đang được giữ nguyên.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title"><svg class="icon"><use href="#i-info"></use></svg> Quy định quỹ phép</div>
                </div>
            </div>
            <div class="card-body">
                <div class="list">
                    <div class="list-item">
                        <span class="list-icon"><svg class="icon"><use href="#i-calendar"></use></svg></span>
                        <div class="list-body">
                            <div class="list-title">Phép năm mặc định {{ (int) setting('annual_leave_default', 12) }} ngày</div>
                            <div class="list-meta">Số ngày được cấu hình bởi HR, có thể điều chỉnh theo từng nhân sự.</div>
                        </div>
                    </div>
                    <div class="list-item">
                        <span class="list-icon orange"><svg class="icon"><use href="#i-refresh"></use></svg></span>
                        <div class="list-body">
                            <div class="list-title">Chuyển phép tối đa {{ (int) setting('max_carry_over', 5) }} ngày</div>
                            <div class="list-meta">Số ngày phép còn lại được chuyển sang năm sau theo quy chế.</div>
                        </div>
                    </div>
                    <div class="list-item">
                        <span class="list-icon green"><svg class="icon"><use href="#i-check"></use></svg></span>
                        <div class="list-body">
                            <div class="list-title">Chỉ trừ quỹ khi đơn được duyệt</div>
                            <div class="list-meta">Đơn bị từ chối hoặc huỷ không ảnh hưởng đến quỹ phép.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-20">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-inbox"></use></svg> Lịch sử đơn nghỉ năm {{ $year }}</div>
                <div class="card-sub">Tất cả đơn nghỉ phép của bạn trong năm</div>
            </div>
            <a href="{{ route('leave.index', ['year' => $year]) }}" class="btn btn-outline btn-sm">Xem tất cả</a>
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Loại phép</th>
                            <th>Thời gian</th>
                            <th>Số ngày</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $leave)
                            @php $meta = status_meta($leave->status); @endphp
                            <tr>
                                <td class="cell-main">{{ $leave->code }}</td>
                                <td>
                                    <span class="chip">
                                        <span class="legend-dot" style="background: {{ $leave->leaveType?->color ?? '#2f6bff' }}"></span>
                                        {{ $leave->leaveType?->name }}
                                    </span>
                                </td>
                                <td>{{ vn_date($leave->start_date, 'd/m') }} – {{ vn_date($leave->end_date, 'd/m/Y') }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($leave->total_days, 1, ',', '.'), '0'), ',') }}</td>
                                <td><span class="badge badge-{{ $meta['tone'] }}">{{ $meta['label'] }}</span></td>
                                <td class="text-sm muted">{{ vn_date($leave->created_at, 'd/m/Y') }}</td>
                                <td>
                                    <div class="cell-actions">
                                        <a href="{{ route('leave.show', $leave) }}" class="btn btn-outline btn-sm">Xem</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty" style="padding: 26px">
                                        <strong>Chưa có đơn nghỉ nào trong năm {{ $year }}</strong>
                                        <p>Đơn nghỉ phép của bạn sẽ được hiển thị tại đây.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($history->hasPages())
            {{ $history->links() }}
        @endif
    </div>
@endsection
