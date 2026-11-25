@extends('layouts.app')

@section('title', 'Vai trò & ma trận quyền')
@section('subtitle', 'Phân quyền chi tiết theo vai trò, áp dụng ngay cho toàn hệ thống')

@section('content')
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
                                        <span>Cấp {{ $role->level }} · {{ $role->permissions->count() }} quyền</span>
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
                                    <button type="submit" form="role-form-{{ $role->id }}" class="btn btn-primary btn-sm">
                                        <svg class="icon"><use href="#i-check"></use></svg>
                                        Lưu
                                    </button>
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
                        <div class="flex items-center justify-between">
                            <span class="badge badge-{{ role_tone($role->slug) }} no-dot">{{ $role->name }}</span>
                            <span class="level-tag l{{ min(4, max(1, (int) ceil($role->level / 25))) }}">Cấp {{ $role->level }}</span>
                        </div>
                        <p class="text-sm muted mt-12">{{ $role->description ?: 'Chưa có mô tả cho vai trò này.' }}</p>
                        <span class="perm-count">
                            <svg class="icon icon-sm"><use href="#i-check"></use></svg>
                            {{ $role->slug === 'admin' ? 'Toàn bộ quyền' : $role->permissions->count() . ' quyền' }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
