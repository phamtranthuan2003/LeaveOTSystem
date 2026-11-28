@extends('layouts.app')

@section('title', 'Vai trò & phân quyền')
@section('subtitle', 'Quản lý vai trò và bộ quyền chi tiết của từng vai trò')

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
        @php
            $formContext = (string) old('form_context');
            if (str_starts_with($formContext, 'perms:')) {
                $autoModalId = 'role-perms-' . substr($formContext, 6);
            } elseif (str_starts_with($formContext, 'info:')) {
                $autoModalId = 'role-edit-' . substr($formContext, 5);
            } else {
                $autoModalId = 'role-create';
            }
        @endphp
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
            Bấm <b>Sửa quyền</b> để mở bảng chọn quyền cho từng vai trò.
            Thay đổi áp dụng ngay cho tất cả tài khoản thuộc vai trò tương ứng.
            Vai trò <b>Quản trị hệ thống</b> luôn có toàn quyền và không thể chỉnh sửa.
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title"><svg class="icon"><use href="#i-shield"></use></svg> Danh sách vai trò</div>
                <div class="card-sub">Mỗi vai trò có một bộ quyền riêng, chỉnh trong hộp thoại phân quyền</div>
            </div>
        </div>
        <div class="card-body flush">
            <div class="table-wrap">
                <table class="data-table roles-table">
                    <thead>
                        <tr>
                            <th>Vai trò</th>
                            <th>Mô tả</th>
                            <th>Quyền</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td>
                                    <span class="badge badge-{{ role_tone($role->slug) }} no-dot">{{ $role->name }}</span>
                                    <div class="cell-sub">{{ $role->users_count }} tài khoản đang dùng</div>
                                </td>
                                <td class="text-sm muted role-desc">{{ $role->description ?: '—' }}</td>
                                <td>
                                    @if ($role->slug === 'admin')
                                        <span class="badge badge-danger no-dot">Toàn quyền</span>
                                    @else
                                        <span class="perm-count"><b>{{ $role->permissions->count() }}</b><span class="text-xs muted">/{{ $counts['permissions'] }}</span></span>
                                    @endif
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        @if ($role->slug === 'admin')
                                            <span class="badge badge-muted no-dot">Không thể sửa</span>
                                        @else
                                            @can('role.update')
                                                <button type="button" class="btn btn-soft btn-sm" data-modal-open="#role-perms-{{ $role->id }}">
                                                    <svg class="icon"><use href="#i-key"></use></svg>
                                                    Sửa quyền
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-outline btn-sm" data-modal-open="#role-perms-{{ $role->id }}">
                                                    <svg class="icon"><use href="#i-eye"></use></svg>
                                                    Xem quyền
                                                </button>
                                            @endcan
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
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @foreach ($editableRoles as $role)
        @php($editingPerms = old('form_context') === 'perms:'.$role->id)
        <div class="modal" id="role-perms-{{ $role->id }}" data-perm-modal>
            <div class="modal-card wide perms-modal">
                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="perms-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_context" value="perms:{{ $role->id }}">
                    <div class="modal-head">
                        <div>
                            <h3>Phân quyền · {{ $role->name }}</h3>
                            <p>
                                <b data-perm-count>{{ $role->permissions->count() }}</b>/{{ $counts['permissions'] }} quyền đang bật
                                · áp dụng ngay cho {{ $role->users_count }} tài khoản
                            </p>
                        </div>
                        <button type="button" class="modal-close" data-modal-close aria-label="Đóng">
                            <svg class="icon"><use href="#i-x"></use></svg>
                        </button>
                    </div>
                    <div class="perms-toolbar">
                        <div class="input-group">
                            <svg class="icon"><use href="#i-search"></use></svg>
                            <input type="search" class="input" data-perm-search placeholder="Tìm quyền theo tên hoặc mã..." aria-label="Tìm quyền">
                        </div>
                        <label class="checkbox-row">
                            <input type="checkbox" data-all-toggle @cannot('role.update') disabled @endcannot>
                            Chọn tất cả
                        </label>
                    </div>
                    <div class="modal-body perms-body">
                        @foreach ($groups as $group => $permissions)
                            <section class="perms-group" data-perm-group>
                                <div class="perms-group-head">
                                    <label class="checkbox-row">
                                        <input type="checkbox" data-group-toggle @cannot('role.update') disabled @endcannot>
                                        <span>{{ $group }}</span>
                                    </label>
                                    <span class="text-xs muted">{{ count($permissions) }} quyền</span>
                                </div>
                                <div class="perms-list">
                                    @foreach ($permissions as $slug => $label)
                                        <label class="checkbox-row perms-item" data-perm-item data-label="{{ Str::lower(Str::ascii($label . ' ' . $slug)) }}">
                                            <input type="checkbox" name="permissions[]" value="{{ $slug }}"
                                                   @checked($editingPerms ? in_array($slug, old('permissions', []), true) : $role->permissions->contains('slug', $slug))
                                                   @cannot('role.update') disabled @endcannot>
                                            <span class="perms-desc">
                                                <span>{{ $label }}</span>
                                                <span class="perms-slug">{{ $slug }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                        <p class="perms-empty text-sm muted hidden" data-perm-empty>Không tìm thấy quyền nào khớp.</p>
                    </div>
                    <div class="modal-foot">
                        <span class="text-xs muted perms-foot-note">Bỏ tích hết để thu hồi toàn bộ quyền của vai trò</span>
                        <button type="button" class="btn btn-outline" data-modal-close>Đóng</button>
                        @can('role.update')
                            <button type="submit" class="btn btn-primary">
                                <svg class="icon"><use href="#i-check"></use></svg>
                                Lưu quyền
                            </button>
                        @endcan
                    </div>
                </form>
            </div>
        </div>
    @endforeach

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
                        <p>Vai trò mới xuất hiện ngay trong danh sách vai trò và khi tạo nhân sự</p>
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
                    <div class="help mt-8">Có thể bỏ trống và gán quyền sau bằng nút <b>Sửa quyền</b>; người dùng chỉ dùng được chức năng khi vai trò của họ có quyền tương ứng.</div>
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
