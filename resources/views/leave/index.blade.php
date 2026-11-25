@extends('layouts.app')

@section('title', 'Đơn nghỉ phép')
@section('subtitle', $scope === 'all' ? 'Toàn bộ đơn nghỉ phép trong công ty' : 'Danh sách đơn nghỉ phép của bạn')

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
        $totalStat = $stats->sum();
        $mineUrl = request()->fullUrlWithQuery(['scope' => 'mine', 'page' => null]);
        $allUrl = request()->fullUrlWithQuery(['scope' => 'all', 'page' => null]);
        $years = [now()->year + 1, now()->year, now()->year - 1];
    @endphp

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-file"></use></svg></span>
            <div>
                <div class="it-value">{{ $totalStat }}</div>
                <div class="it-label">Tổng số đơn</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon amber"><svg class="icon"><use href="#i-clock"></use></svg></span>
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
            <span class="stat-icon red"><svg class="icon"><use href="#i-x"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['rejected'] ?? 0 }}</div>
                <div class="it-label">Từ chối</div>
            </div>
        </div>
    </div>

    <div class="tab-link-row">
        <div class="tabs">
            <a href="{{ $mineUrl }}" class="tab {{ $scope === 'mine' ? 'active' : '' }}">
                <svg class="icon"><use href="#i-user"></use></svg>
                Đơn của tôi
            </a>
            @can('leave.view.all')
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

    <form method="GET" action="{{ route('leave.index') }}" class="filter-bar">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <div class="field search">
            <label for="q">Tìm kiếm</label>
            <div class="input-group">
                <svg class="icon"><use href="#i-search"></use></svg>
                <input id="q" name="q" type="text" class="input" value="{{ request('q') }}" placeholder="Mã đơn, lý do, tên nhân sự...">
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
            <label for="leave_type_id">Loại phép</label>
            <select id="leave_type_id" name="leave_type_id" class="select">
                <option value="">Tất cả</option>
                @foreach ($leaveTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) request('leave_type_id') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
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
            <a href="{{ route('leave.index', ['scope' => $scope]) }}" class="btn btn-outline btn-sm">Xoá lọc</a>
        </div>
    </form>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-sun"></use></svg> Danh sách đơn nghỉ phép</div>
                <div class="card-sub">Nhấn vào đơn để xem chi tiết quy trình duyệt</div>
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
                            <th data-sort>Loại phép</th>
                            <th data-sort>Thời gian nghỉ</th>
                            <th data-sort>Số ngày</th>
                            <th data-sort>Trạng thái</th>
                            <th>Bước duyệt</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($query as $leave)
                            @php
                                $meta = status_meta($leave->status);
                                $pendingApproval = $leave->approvals->firstWhere('status', 'pending');
                            @endphp
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $leave->code }}</div>
                                    <div class="cell-sub">{{ vn_date($leave->created_at, 'd/m/Y H:i') }}</div>
                                </td>
                                @if ($scope === 'all')
                                    <td>
                                        <div class="cell-user">
                                            <span class="avatar avatar-sm">
                                                @if ($leave->user?->avatarUrl())
                                                    <img src="{{ $leave->user->avatarUrl() }}" alt="">
                                                @else
                                                    {{ initials($leave->user?->name) }}
                                                @endif
                                            </span>
                                            <span>
                                                <span class="cell-main" style="display: block">{{ $leave->user?->name }}</span>
                                                <span class="cell-sub">{{ $leave->user?->department?->name }}</span>
                                            </span>
                                        </div>
                                    </td>
                                @endif
                                <td>
                                    <span class="chip">
                                        <span class="legend-dot" style="background: {{ $leave->leaveType?->color ?? '#2f6bff' }}"></span>
                                        {{ $leave->leaveType?->name ?? 'Nghỉ phép' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="cell-main" style="font-size: 13px">{{ vn_date($leave->start_date, 'd/m') }} – {{ vn_date($leave->end_date, 'd/m/Y') }}</div>
                                    <div class="cell-sub">{{ working_day_label($leave->day_type) }}</div>
                                </td>
                                <td><span class="num">{{ rtrim(rtrim(number_format($leave->total_days, 1, ',', '.'), '0'), ',') }}</span> <span class="text-xs muted">ngày</span></td>
                                <td><span class="badge badge-{{ $meta['tone'] }}">{{ $meta['label'] }}</span></td>
                                <td>
                                    @if ($leave->status === 'pending' && $pendingApproval)
                                        <div class="text-sm strong">Bước {{ $leave->current_step }}/{{ $leave->total_steps }}</div>
                                        <div class="cell-sub">Chờ {{ $pendingApproval->approver?->name ?? 'người duyệt' }}</div>
                                    @elseif ($leave->status === 'approved')
                                        <span class="text-sm muted"><svg class="icon icon-sm" style="color: var(--green-600)"><use href="#i-check"></use></svg> Hoàn tất {{ $leave->total_steps }} bước</span>
                                    @else
                                        <span class="text-sm muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <a href="{{ route('leave.show', $leave) }}" class="btn btn-outline btn-sm">
                                            <svg class="icon"><use href="#i-eye"></use></svg>
                                            Xem
                                        </a>
                                        @can('records.purge')
                                            <form method="POST" action="{{ route('leave.destroy', $leave) }}" data-confirm="Xoá vĩnh viễn đơn {{ $leave->code }}? Quỹ phép đã trừ (nếu có) sẽ được hoàn lại.">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="scope" value="{{ $scope }}">
                                                <button type="submit" class="btn btn-danger-soft btn-sm" aria-label="Xoá vĩnh viễn đơn {{ $leave->code }}">
                                                    <svg class="icon"><use href="#i-trash"></use></svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $scope === 'all' ? 8 : 7 }}">
                                    <div class="empty">
                                        <div class="empty-icon"><svg class="icon"><use href="#i-sun"></use></svg></div>
                                        <strong>Không có đơn nghỉ phép nào</strong>
                                        <p>Thử thay đổi bộ lọc hoặc tạo đơn nghỉ phép mới.</p>
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
