@extends('layouts.app')

@section('title', 'Vai trò & ma trận quyền')
@section('subtitle', 'Phân quyền chi tiết theo vai trò, áp dụng ngay cho toàn hệ thống')

@section('actions')
    @can('role.create')
        <button type="button" class="btn btn-primary btn-sm" data-modal-open="#role-create">
            <svg class="icon"><use href="#i-plus"></use></svg>
            Thêm vai trò
        </button>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        @php($autoModalId = str_starts_with((string) old('form_context'), 'info:') ? 'role-edit-' . substr((string) old('form_context'), 5) : 'role-create')
        <div data-auto-modal="{{ $autoModalId }}" hidden></div>
    @endif

    <div class="info-strip mb-20">
        <div class="info-tile">
            <span class="stat-icon"><svg class="icon"><use href="#i-shield"></use></svg></span>
            <div>
                <div class="it-value">{{ $counts['roles'] }}</div>
                <div class="it-label">Vai trò</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon info"><svg class="icon"><use href="#i-layers"></use></svg></span>
            <div>
                <div class="it-value">{{ $counts['permissions'] }}</div>
                <div class="it-label">Quyền trong hệ thống</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon purple"><svg class="icon"><use href="#i-sliders"></use></svg></span>
            <div>
                <div class="it-value">{{ $counts['nonAdmin'] }}</div>
                <div class="it-label">Vai trò có thể tuỳ chỉnh</div>
            </div>
        </div>
        <div class="info-tile">
            <span class="stat-icon red"><svg class="icon"><use href="#i-lock"></use></svg></span>
            <div>
                <div class="it-value">Admin</div>
                <div class="it-label">Luôn toàn quyền</div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mb-20">
        <svg class="icon"><use href="#i-info"></use></svg>
        <div>
            Vai trò <b>Quản trị hệ thống</b> luôn có toàn quyền và không thể chỉnh sửa.
            Thay đổi quyền áp dụng ngay cho tất cả tài khoản thuộc vai trò tương ứng.
        </div>
    </div>

    @foreach ($editableRoles as $role)
        <form id="role-form-{{ $role->id }}" method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
        </form>
    @endforeach

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-shield"></use></svg> Ma trận phân quyền</div>
                <div class="card-sub">Tích chọn quyền cho từng vai trò, sau đó bấm Lưu ở dòng tương ứng</div>
            </div>
        </div>
        <div class="card-body flush">
            <div class="matrix-wrap">
                <table class="matrix">
                    <thead>
                        <tr>
                            <th>Quyền hạn</th>
                            @foreach ($editableRoles as $role)
                                <th>
                                    <div class="matrix-role-head">
                                        <b>{{ $role->name }}</b>
                                        <span>{{ $role->permissions->count() }} quyền</span>
                                    </div>
                                </th>
                            @endforeach
                            <th>
                                <div class="matrix-role-head">
                                    <b>Quản trị</b>
                                    <span>Toàn quyền</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group => $permissions)
                            <tr class="group-row">
                                <td colspan="{{ count($editableRoles) + 2 }}">{{ $group }}</td>
                            </tr>
                            @foreach ($permissions as $slug => $label)
                                <tr>
                                    <td>
                                        <div style="font-weight: 650">{{ $label }}</div>
                                        <div class="text-xs muted">{{ $slug }}</div>
                                    </td>
                                    @foreach ($editableRoles as $role)
                                        <td>
                                            <label class="checkbox-row" style="justify-content: center; gap: 0">
                                                <input type="checkbox" name="permissions[]" value="{{ $slug }}"
                                                       form="role-form-{{ $role->id }}"
                                                       @checked($role->permissions->contains('slug', $slug))
                                                       @cannot('role.update') disabled @endcannot
                                                       aria-label="{{ $role->name }} · {{ $label }}">
                                            </label>
                                        </td>
                                    @endforeach
                                    <td>
                                        <span class="badge badge-danger no-dot">Có</span>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>
                                <span class="text-xs muted">Cấu hình hiệu lực ngay sau khi lưu</span>
                            </td>
                            @foreach ($editableRoles as $role)
                                <td>
                                    @can('role.update')
                                        <button type="submit" form="role-form-{{ $role->id }}" class="btn btn-primary btn-sm">
                                            <svg class="icon"><use href="#i-check"></use></svg>
                                            Lưu
                                        </button>
                                    @else
                                        <span class="text-xs muted">Chỉ xem</span>
                                    @endcan
                                </td>
                            @endforeach
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-3 mt-20">
        @foreach ($roles as $role)
            <div class="card">
                <div class="card-body">
                    <div class="role-card">
                        <span class="badge badge-{{ role_tone($role->slug) }} no-dot">{{ $role->name }}</span>
                        <p class="text-sm muted mt-12">{{ $role->description ?: 'Chưa có mô tả cho vai trò này.' }}</p>
                        <span class="perm-count">
                            <svg class="icon icon-sm"><use href="#i-check"></use></svg>
                            {{ $role->slug === 'admin' ? 'Toàn bộ quyền' : $role->permissions->count() . ' quyền' }}
                        </span>
                        <div class="flex items-center gap-8 mt-12" style="justify-content: space-between">
                            <span class="text-xs muted">{{ $role->users_count }} tài khoản đang dùng</span>
                            @if ($role->slug !== 'admin')
                                <div class="flex items-center gap-8">
                                    @can('role.update')
                                        <button type="button" class="btn btn-outline btn-sm" data-modal-open="#role-edit-{{ $role->id }}">
                                            <svg class="icon"><use href="#i-edit"></use></svg>
                                            Sửa
                                        </button>
                                    @endcan
                                    @can('role.delete')
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                              data-confirm="Xoá vai trò {{ $role->name }}? Chỉ xoá được khi không còn tài khoản nào dùng vai trò này.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger-soft btn-sm">Xoá</button>
                                        </form>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @foreach ($editableRoles as $role)
        @php($editingThis = old('form_context') === 'info:'.$role->id)
        <div class="modal" id="role-edit-{{ $role->id }}">
            <div class="modal-card wide">
                <form method="POST" action="{{ route('admin.roles.update-info', $role) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_context" value="info:{{ $role->id }}">
                    <div class="modal-head">
                        <div>
                            <h3>Sửa vai trò</h3>
                            <p>Cập nhật tên và mô tả vai trò. Mã vai trò <b>{{ $role->slug }}</b> được giữ nguyên để không ảnh hưởng quy trình duyệt.</p>
                        </div>
                        <button type="button" class="modal-close" data-modal-close>
                            <svg class="icon"><use href="#i-x"></use></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-grid">
                            <div class="field span-2">
                                <label for="role-name-{{ $role->id }}">Tên vai trò <span class="req">*</span></label>
                                <input id="role-name-{{ $role->id }}" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ $editingThis ? old('name') : $role->name }}" maxlength="80" required>
                                @error('name')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field span-2">
                                <label for="role-description-{{ $role->id }}">Mô tả</label>
                                <textarea id="role-description-{{ $role->id }}" name="description" rows="2" class="textarea" maxlength="255" placeholder="Trách nhiệm chính của vai trò này">{{ $editingThis ? old('description') : $role->description }}</textarea>
                                @error('description')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <div class="modal" id="role-create">
        <div class="modal-card wide">
            <form method="POST" action="{{ route('admin.roles.store') }}">
                @csrf
                <div class="modal-head">
                    <div>
                        <h3>Thêm vai trò mới</h3>
                        <p>Vai trò mới xuất hiện ngay trong ma trận phân quyền và danh sách vai trò khi tạo nhân sự</p>
                    </div>
                    <button type="button" class="modal-close" data-modal-close>
                        <svg class="icon"><use href="#i-x"></use></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="field span-2">
                            <label for="role-name">Tên vai trò <span class="req">*</span></label>
                            <input id="role-name" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="80" required placeholder="VD: Trưởng ca">
                            @error('name')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field span-2">
                            <label for="role-description">Mô tả</label>
                            <textarea id="role-description" name="description" rows="2" class="textarea" maxlength="255" placeholder="Trách nhiệm chính của vai trò này">{{ old('description') }}</textarea>
                            @error('description')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-section-title mt-16">Quyền sử dụng chức năng</div>
                    <div class="perm-picker">
                        @foreach ($groups as $group => $permissions)
                            <div class="perm-picker-group">
                                <h4>{{ $group }}</h4>
                                @foreach ($permissions as $slug => $label)
                                    <label class="checkbox-row">
                                        <input type="checkbox" name="permissions[]" value="{{ $slug }}" @checked(in_array($slug, old('permissions', []), true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="help mt-8">Có thể bỏ trống và gán quyền sau trong ma trận; người dùng chỉ dùng được chức năng khi vai trò của họ có quyền tương ứng.</div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                    <button type="submit" class="btn btn-primary">
                        <svg class="icon"><use href="#i-check"></use></svg>
                        Tạo vai trò
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

