@extends('layouts.app')

@section('title', 'Đăng ký làm thêm giờ')
@section('subtitle', $scope === 'all' ? 'Toàn bộ đơn OT trong công ty' : 'Các đơn làm thêm giờ của bạn')

@section('actions')
    @can('ot.create')
        <a href="{{ route('overtime.create') }}" class="btn btn-orange btn-sm">
            <svg class="icon"><use href="#i-plus"></use></svg>
            Đăng ký OT
        </a>
    @endcan
@endsection

@section('content')
    @php
        $totalStat = $stats->sum();
        $mineUrl = request()->fullUrlWithQuery(['scope' => 'mine', 'page' => null]);
        $allUrl = request()->fullUrlWithQuery(['scope' => 'all', 'page' => null]);
        $years = [now()->year + 1, now()->year, now()->year - 1];
    @endphp

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-clock"></use></svg></span>
            <div>
                <div class="it-value">{{ $totalStat }}</div>
                <div class="it-label">Tổng số đơn</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon amber"><svg class="icon"><use href="#i-inbox"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['pending'] ?? 0 }}</div>
                <div class="it-label">Chờ duyệt</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon green"><svg class="icon"><use href="#i-check"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['approved'] ?? 0 }}</div>
                <div class="it-label">Đã duyệt</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon orange"><svg class="icon"><use href="#i-trend"></use></svg></span>
            <div>
                <div class="it-value">{{ rtrim(rtrim(number_format($totalHours, 1, ',', '.'), '0'), ',') }}</div>
                <div class="it-label">Tổng giờ đã duyệt</div>
            </div>
        </div>
    </div>

    <div class="tab-link-row">
        <div class="tabs">
            <a href="{{ $mineUrl }}" class="tab {{ $scope === 'mine' ? 'active' : '' }}">
                <svg class="icon"><use href="#i-user"></use></svg>
                Đơn của tôi
            </a>
            @can('ot.view.all')
                <a href="{{ $allUrl }}" class="tab {{ $scope === 'all' ? 'active' : '' }}">
                    <svg class="icon"><use href="#i-users"></use></svg>
                    Toàn công ty
                </a>
            @endcan
        </div>
        @if ($query->total() > 0)
            <span class="text-sm muted">{{ $query->total() }} đơn phù hợp</span>
        @endif
    </div>

    <form method="GET" action="{{ route('overtime.index') }}" class="filter-bar">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <div class="field search">
            <label for="q">Tìm kiếm</label>
            <div class="input-group">
                <svg class="icon"><use href="#i-search"></use></svg>
                <input id="q" name="q" type="text" class="input" value="{{ request('q') }}" placeholder="Mã đơn, dự án, nội dung...">
            </div>
        </div>
        <div class="field">
            <label for="year">Năm</label>
            <select id="year" name="year" class="select">
                <option value="all" @selected($year === 'all')>Tất cả</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected((string) $year === (string) $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Trạng thái</label>
            <select id="status" name="status" class="select">
                <option value="">Tất cả</option>
                <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Đã huỷ</option>
            </select>
        </div>
        <div class="field">
            <label for="ot_type">Loại OT</label>
            <select id="ot_type" name="ot_type" class="select">
                <option value="">Tất cả</option>
                <option value="weekday" @selected(request('ot_type') === 'weekday')>Ngày thường</option>
                <option value="weekend" @selected(request('ot_type') === 'weekend')>Cuối tuần</option>
                <option value="holiday" @selected(request('ot_type') === 'holiday')>Ngày lễ</option>
            </select>
        </div>
        <div class="field">
            <label for="month">Tháng</label>
            <select id="month" name="month" class="select">
                <option value="">Tất cả</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected((string) request('month') === (string) $m)>Tháng {{ $m }}</option>
                @endfor
            </select>
        </div>
        @if ($scope === 'all')
            <div class="field">
                <label for="department_id">Phòng ban</label>
                <select id="department_id" name="department_id" class="select">
                    <option value="">Tất cả</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <svg class="icon"><use href="#i-filter"></use></svg>
                Lọc
            </button>
            <a href="{{ route('overtime.index', ['scope' => $scope]) }}" class="btn btn-outline btn-sm">Xoá lọc</a>
        </div>
    </form>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-clock"></use></svg> Danh sách đơn làm thêm giờ</div>
                <div class="card-sub">Hệ số OT tự động theo loại ngày khi tạo đơn</div>
            </div>
            @if ($query->total() > 0)
                <span class="badge badge-muted no-dot">Trang {{ $query->currentPage() }}/{{ $query->lastPage() }}</span>
            @endif
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table" data-sort-table>
                    <thead>
                        <tr>
                            <th data-sort>Mã đơn</th>
                            @if ($scope === 'all')
                                <th data-sort>Nhân sự</th>
                            @endif
                            <th data-sort>Ngày OT</th>
                            <th>Khung giờ</th>
                            <th data-sort>Số giờ</th>
                            <th data-sort>Loại</th>
                            <th data-sort>Trạng thái</th>
                            <th>Bước duyệt</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($query as $ot)
                            @php
                                $meta = status_meta($ot->status);
                                $typeMeta = ot_type_meta($ot->ot_type);
                                $pendingApproval = $ot->approvals->firstWhere('status', 'pending');
                            @endphp
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $ot->code }}</div>
                                    <div class="cell-sub">{{ vn_date($ot->created_at, 'd/m/Y H:i') }}</div>
                                </td>
                                @if ($scope === 'all')
                                    <td>
                                        <div class="cell-user">
                                            <span class="avatar avatar-sm orange">
                                                @if ($ot->user?->avatarUrl())
                                                    <img src="{{ $ot->user->avatarUrl() }}" alt="">
                                                @else
                                                    {{ initials($ot->user?->name) }}
                                                @endif
                                            </span>
                                            <span>
                                                <span class="cell-main" style="display: block">{{ $ot->user?->name }}</span>
                                                <span class="cell-sub">{{ $ot->user?->department?->name }}</span>
                                            </span>
                                        </div>
                                    </td>
                                @endif
                                <td>
                                    <div class="cell-main" style="font-size: 13px">{{ vn_date($ot->ot_date, 'd/m/Y') }}</div>
                                    <div class="cell-sub">{{ $ot->ot_date->translatedFormat('l') }}</div>
                                </td>
                                <td class="nowrap">{{ substr($ot->start_time, 0, 5) }} – {{ substr($ot->end_time, 0, 5) }}</td>
                                <td><span class="num">{{ rtrim(rtrim(number_format($ot->hours, 2, ',', '.'), '0'), ',') }}</span> <span class="text-xs muted">giờ</span></td>
                                <td><span class="badge badge-{{ $typeMeta['tone'] }}">{{ $typeMeta['label'] }}</span></td>
                                <td><span class="badge badge-{{ $meta['tone'] }}">{{ $meta['label'] }}</span></td>
                                <td>
                                    @if ($ot->status === 'pending' && $pendingApproval)
                                        <div class="text-sm strong">Bước {{ $ot->current_step }}/{{ $ot->total_steps }}</div>
                                        <div class="cell-sub">Chờ {{ $pendingApproval->approver?->name ?? 'người duyệt' }}</div>
                                    @elseif ($ot->status === 'approved')
                                        <span class="text-sm muted"><svg class="icon icon-sm" style="color: var(--green-600)"><use href="#i-check"></use></svg> Hoàn tất</span>
                                    @else
                                        <span class="text-sm muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <a href="{{ route('overtime.show', $ot) }}" class="btn btn-outline btn-sm">
                                            <svg class="icon"><use href="#i-eye"></use></svg>
                                            Xem
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $scope === 'all' ? 9 : 8 }}">
                                    <div class="empty">
                                        <div class="empty-icon"><svg class="icon"><use href="#i-clock"></use></svg></div>
                                        <strong>Không có đơn OT nào</strong>
                                        <p>Thử thay đổi bộ lọc hoặc đăng ký làm thêm giờ mới.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($query->hasPages())
            {{ $query->links() }}
        @endif
    </div>
@endsection
