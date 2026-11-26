@extends('layouts.app')

@section('title', 'Quản lý nhân sự')
@section('subtitle', 'Danh sách tài khoản, vai trò và cấp bậc quản lý trong tổ chức')

@section('actions')
    @can('user.create')
        <button type="button" class="btn btn-outline btn-sm" data-modal-open="#user-import">
            <svg class="icon"><use href="#i-file"></use></svg>
            Import nhân sự
        </button>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
            <svg class="icon"><use href="#i-plus"></use></svg>
            Thêm nhân sự
        </a>
    @endcan
@endsection

@section('content')
    @php
        $avatarTones = ['blue', 'orange', 'green', 'purple', 'red'];
    @endphp

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['total'] }}</div>
                <div class="it-label">Tổng tài khoản</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon green"><svg class="icon"><use href="#i-check"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['active'] }}</div>
                <div class="it-label">Đang hoạt động</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon red"><svg class="icon"><use href="#i-lock"></use></svg></span>
            <div>
                <div class="it-value">{{ $stats['inactive'] }}</div>
                <div class="it-label">Đã khoá</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon info"><svg class="icon"><use href="#i-shield"></use></svg></span>
            <div>
                <div class="it-value">{{ $roles->count() }}</div>
                <div class="it-label">Vai trò có thể gán</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="filter-bar">
        <div class="field search">
            <label for="q">Tìm kiếm</label>
            <div class="input-group">
                <svg class="icon"><use href="#i-search"></use></svg>
                <input id="q" name="q" type="text" class="input" value="{{ request('q') }}" placeholder="Tên, email, mã NV, số điện thoại...">
            </div>
        </div>
        <div class="field">
            <label for="department_id">Phòng ban</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">Tất cả</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="role_id">Vai trò</label>
            <select id="role_id" name="role_id" class="select">
                <option value="">Tất cả</option>
                @foreach ($allRoles as $role)
                    <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Trạng thái</label>
            <select id="status" name="status" class="select">
                <option value="">Tất cả</option>
                <option value="active" @selected(request('status') === 'active')>Đang hoạt động</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Đã khoá</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <svg class="icon"><use href="#i-filter"></use></svg>
                Lọc
            </button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm">Xoá lọc</a>
        </div>
    </form>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-users"></use></svg> Danh sách nhân sự</div>
                <div class="card-sub">{{ $users->total() }} tài khoản</div>
            </div>
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nhân sự</th>
                            <th>Mã NV</th>
                            <th>Phòng ban</th>
                            <th>Vai trò</th>
                            <th>Quản lý trực tiếp</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $employee)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-12">
                                        <span class="avatar avatar-sm {{ $avatarTones[$employee->id % count($avatarTones)] }}" style="border-radius: 9px; overflow: hidden">
                                            @if ($employee->avatarUrl())
                                                <img src="{{ $employee->avatarUrl() }}" alt="{{ $employee->name }}">
                                            @else
                                                {{ initials($employee->name) }}
                                            @endif
                                        </span>
                                        <div>
                                            <div class="cell-main">{{ $employee->name }}</div>
                                            <div class="text-xs muted">{{ $employee->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="chip">{{ $employee->employee_code ?: '—' }}</span></td>
                                <td class="text-sm">{{ $employee->department?->name ?? '—' }}</td>
                                <td>
                                    @if ($employee->role)
                                        <span class="badge badge-{{ role_tone($employee->role->slug) }} no-dot">{{ $employee->role->name }}</span>
                                    @else
                                        <span class="badge badge-muted no-dot">Chưa gán</span>
                                    @endif
                                </td>
                                <td class="text-sm muted">{{ $employee->manager?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge badge-{{ $employee->status === 'active' ? 'success' : 'danger' }}">
                                        {{ $employee->status === 'active' ? 'Hoạt động' : 'Đã khoá' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        @if ($employee->is_protected)
                                            <span class="badge badge-warning no-dot" title="Tài khoản hệ thống được bảo vệ: không thể sửa, khoá hoặc xoá">Được bảo vệ</span>
                                        @else
                                            @can('user.update')
                                                <a href="{{ route('admin.users.edit', $employee) }}" class="btn btn-outline btn-sm">Sửa</a>
                                            @endcan
                                            @can('user.toggle')
                                                <form method="POST" action="{{ route('admin.users.toggle', $employee) }}"
                                                      data-confirm="{{ $employee->status === 'active' ? 'Khoá tài khoản ' . $employee->name . '?' : 'Mở khoá tài khoản ' . $employee->name . '?' }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm {{ $employee->status === 'active' ? 'btn-warning-soft' : 'btn-success' }}">
                                                        {{ $employee->status === 'active' ? 'Khoá' : 'Mở' }}
                                                    </button>
                                                </form>
                                            @endcan
                                            @can('user.delete')
                                                @if ($employee->id !== $actor->id)
                                                    <form method="POST" action="{{ route('admin.users.destroy', $employee) }}"
                                                          data-confirm="Xoá tài khoản {{ $employee->name }}? Hành động không thể hoàn tác; chỉ xoá được khi tài khoản chưa phát sinh đơn nghỉ/OT.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger-soft btn-sm">Xoá</button>
                                                    </form>
                                                @endif
                                            @endcan
                                            @if (!$actor->hasPermission('user.update') && !$actor->hasPermission('user.toggle') && !$actor->hasPermission('user.delete'))
                                                <span class="text-xs muted">Chỉ xem</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty" style="padding: 30px">
                                        <strong>Không tìm thấy nhân sự phù hợp</strong>
                                        <p>Thử thay đổi từ khoá hoặc bộ lọc.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            {{ $users->links() }}
        @endif
    </div>

    @can('user.create')
    <div class="modal" id="user-import">
        <div class="modal-card">
            <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-head">
                    <div>
                        <h3>Import nhân sự từ Excel</h3>
                        <p>Tạo hàng loạt tài khoản từ file .xlsx, .xls hoặc .csv</p>
                    </div>
                    <button type="button" class="modal-close" data-modal-close>
                        <svg class="icon"><use href="#i-x"></use></svg>
                    </button>
                </div>
                <div class="modal-body">
                    @if (session('import_errors'))
                        <div class="alert alert-error" style="margin-bottom: 14px">
                            <svg class="icon"><use href="#i-alert"></use></svg>
                            <div>
                                <strong>Các dòng chưa import được:</strong>
                                <ul style="margin: 6px 0 0 18px; max-height: 180px; overflow-y: auto">
                                    @foreach (session('import_errors') as $importError)
                                        <li>{{ $importError }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="field">
                        <label for="import-file">Tệp danh sách nhân sự <span class="req">*</span></label>
                        <input id="import-file" name="file" type="file" class="input" accept=".xlsx,.xls,.csv" required>
                        @error('file')<span class="error-text">{{ $message }}</span>@enderror
                    </div>

                    <div class="note-box mt-12">
                        <a href="{{ route('admin.users.import.template') }}" class="strong">
                            <svg class="icon icon-sm"><use href="#i-download"></use></svg>
                            Tải file mẫu Excel
                        </a>
                        <p class="mt-8">Cột bắt buộc: <b>Họ và tên</b>, <b>Vai trò</b>. <b>Vai trò</b> và <b>Phòng ban</b> chọn từ danh sách trong file mẫu. Các cột khác: mã NV, chức danh, số điện thoại, ngày vào làm (dd/mm/yyyy).</p>
                        <p class="mt-8">Email công ty tự sinh theo họ tên (vd: Phạm Trần Thuận → <b>thuan.pt@everland.vn</b>, nếu trùng sẽ thêm số). Mật khẩu mặc định <b>123456789</b>. Dòng nào sai (vai trò/phòng ban không tồn tại, ngày sai định dạng, mã NV trùng...) sẽ được liệt kê để bạn sửa và import lại.</p>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                    <button type="submit" class="btn btn-primary">
                        <svg class="icon"><use href="#i-check"></use></svg>
                        Import
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
@endsection

@if (session('import_errors'))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('user-import');
            if (modal) {
                modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            }
        });
    </script>
    @endpush
@endif
