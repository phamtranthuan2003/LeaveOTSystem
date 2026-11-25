@extends('layouts.app')

@section('title', 'Sơ đồ phòng ban')
@section('subtitle', 'Cơ cấu tổ chức theo cấp: Tập đoàn → Khối/Ban → Phòng → Nhóm')

@section('actions')
    @can('department.create')
        <a href="{{ route('admin.departments.create') }}" class="btn btn-primary btn-sm">
            <svg class="icon"><use href="#i-plus"></use></svg>
            Thêm phòng ban
        </a>
    @endcan
@endsection

@section('content')
    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-branch"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['total'] }}</div>
                <div class="it-label">Tổng đơn vị</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon info"><svg class="icon"><use href="#i-layers"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['level2'] }}</div>
                <div class="it-label">Khối / Ban</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon purple"><svg class="icon"><use href="#i-grid"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['level3'] }}</div>
                <div class="it-label">Phòng</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon {{ $unassigned > 0 ? 'amber' : 'green' }}"><svg class="icon"><use href="#i-users"></use></svg></span>
            <div>
                <div class="it-value">{{ $unassigned }}</div>
                <div class="it-label">Nhân sự chưa xếp phòng</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-branch"></use></svg> Cây tổ chức</div>
                <div class="card-sub">Hiển thị theo thứ tự cấp bậc và thứ tự sắp xếp</div>
            </div>
            <span class="badge badge-{{ $stats['active'] === $stats['total'] ? 'success' : 'warning' }} no-dot">
                {{ $stats['active'] }}/{{ $stats['total'] }} đang hoạt động
            </span>
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Đơn vị</th>
                            <th>Mã</th>
                            <th>Cấp</th>
                            <th>Quản lý</th>
                            <th>Nhân sự</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tree as $node)
                            @php $department = $node['department']; @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-8" style="padding-left: {{ $node['depth'] * 22 }}px">
                                        @if ($node['depth'] > 0)
                                            <svg class="icon icon-sm" style="color: var(--muted); transform: rotate(90deg)"><use href="#i-branch"></use></svg>
                                        @else
                                            <svg class="icon icon-sm" style="color: var(--blue-600)"><use href="#i-branch"></use></svg>
                                        @endif
                                        <div>
                                            <div class="{{ $node['depth'] === 0 ? 'cell-main' : '' }}" style="font-weight: {{ $node['depth'] === 0 ? 800 : 650 }}">{{ $department->name }}</div>
                                            @if ($department->description)
                                                <div class="text-xs muted">{{ \Illuminate\Support\Str::limit($department->description, 80) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td><span class="chip">{{ $department->code }}</span></td>
                                <td class="text-sm muted">{{ $department->levelLabel() }}</td>
                                <td class="text-sm">{{ $department->manager?->name ?? '—' }}</td>
                                <td><span class="count-pill">{{ $department->users_count }}</span></td>
                                <td>
                                    <span class="badge badge-{{ $department->active ? 'success' : 'muted' }}">
                                        {{ $department->active ? 'Hoạt động' : 'Ngưng' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        @can('department.update')
                                            <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-outline btn-sm">Sửa</a>
                                        @endcan
                                        @can('department.toggle')
                                            <form method="POST" action="{{ route('admin.departments.toggle', $department) }}"
                                                  data-confirm="{{ $department->active ? 'Ngưng hoạt động ' . $department->name . '?' : 'Kích hoạt lại ' . $department->name . '?' }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $department->active ? 'btn-warning-soft' : 'btn-success' }}">
                                                    {{ $department->active ? 'Ngưng' : 'Kích hoạt' }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('department.delete')
                                            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}"
                                                  data-confirm="Xoá phòng ban {{ $department->name }}? Chỉ xoá được khi phòng ban không còn nhân sự và phòng ban trực thuộc.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger-soft btn-sm">Xoá</button>
                                            </form>
                                        @endcan
                                        @if (!auth()->user()->hasPermission('department.update') && !auth()->user()->hasPermission('department.toggle') && !auth()->user()->hasPermission('department.delete'))
                                            <span class="text-xs muted">Chỉ xem</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty" style="padding: 30px">
                                        <strong>Chưa có phòng ban nào</strong>
                                        <p>Bắt đầu bằng việc tạo đơn vị cấp Tập đoàn.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
