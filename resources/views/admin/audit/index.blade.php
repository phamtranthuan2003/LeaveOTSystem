@extends('layouts.app')

@section('title', 'Nhật ký hoạt động')
@section('subtitle', 'Lịch sử thao tác của người dùng trên hệ thống, phục vụ tra soát')

@section('content')
    <form method="GET" action="{{ route('admin.audit.index') }}" class="filter-bar">
        <div class="field search">
            <label for="q">Tìm kiếm</label>
            <div class="input-group">
                <svg class="icon"><use href="#i-search"></use></svg>
                <input id="q" name="q" type="text" class="input" value="{{ request('q') }}" placeholder="Mô tả hoặc tên người thao tác...">
            </div>
        </div>
        <div class="field">
            <label for="module">Phân hệ</label>
            <select id="module" name="module" class="select">
                <option value="">Tất cả</option>
                @foreach ($modules as $module)
                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="actor">Người thao tác</label>
            <select id="actor" name="actor" class="select">
                <option value="">Tất cả</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}" @selected((string) request('actor') === (string) $actor->id)>{{ $actor->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="from">Từ ngày</label>
            <input id="from" name="from" type="date" class="input" value="{{ request('from') }}">
        </div>
        <div class="field">
            <label for="to">Đến ngày</label>
            <input id="to" name="to" type="date" class="input" value="{{ request('to') }}">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <svg class="icon"><use href="#i-filter"></use></svg>
                Lọc
            </button>
            <a href="{{ route('admin.audit.index') }}" class="btn btn-outline btn-sm">Xoá lọc</a>
        </div>
    </form>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-book"></use></svg> Nhật ký hoạt động</div>
                <div class="card-sub">{{ $logs->total() }} bản ghi</div>
            </div>
            @can('records.purge')
                <form method="POST" action="{{ route('admin.audit.clear') }}" data-confirm="Xoá toàn bộ nhật ký hoạt động? Thao tác này không thể hoàn tác.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger-soft btn-sm">
                        <svg class="icon"><use href="#i-trash"></use></svg>
                        Xoá toàn bộ
                    </button>
                </form>
            @endcan
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Người thao tác</th>
                            <th>Phân hệ</th>
                            <th>Hành động</th>
                            <th>Mô tả</th>
                            <th>IP</th>
                            @can('records.purge')
                                <th></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $log->created_at?->format('d/m/Y') }}</div>
                                    <div class="text-xs muted">{{ $log->created_at?->format('H:i:s') }}</div>
                                </td>
                                <td class="text-sm">{{ $log->user?->name ?? 'Hệ thống' }}</td>
                                <td><span class="badge badge-info no-dot">{{ $log->module }}</span></td>
                                <td><span class="chip">{{ $log->action }}</span></td>
                                <td class="text-sm" style="max-width: 380px">{{ $log->description }}</td>
                                <td class="text-xs muted">{{ $log->ip ?: '—' }}</td>
                                @can('records.purge')
                                    <td>
                                        <form method="POST" action="{{ route('admin.audit.destroy', $log) }}" data-confirm="Xoá bản ghi nhật ký này?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" aria-label="Xoá bản ghi">
                                                <svg class="icon"><use href="#i-trash"></use></svg>
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()?->can('records.purge') ? 7 : 6 }}">
                                    <div class="empty" style="padding: 30px">
                                        <strong>Không có bản ghi phù hợp</strong>
                                        <p>Thử thay đổi khoảng thời gian hoặc bộ lọc.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($logs->hasPages())
            {{ $logs->links() }}
        @endif
    </div>
@endsection
