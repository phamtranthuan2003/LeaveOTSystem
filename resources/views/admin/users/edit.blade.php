@extends('layouts.app')

@section('title', 'Sửa tài khoản · ' . $user->name)
@section('subtitle', 'Cập nhật thông tin nhân sự, vai trò và cấp bậc quản lý')

@section('actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Danh sách nhân sự
    </a>
@endsection

@section('content')
    <div class="grid grid-1-2">
        <div class="">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-user"></use></svg> Hồ sơ nhân sự</div>
                </div>
                <div class="card-body">
                    <div class="profile-side" style="text-align: center">
                        <span class="avatar avatar-xl {{ $user->status === 'active' ? 'blue' : 'red' }}" style="overflow: hidden">
                            @if ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                            @else
                                {{ initials($user->name) }}
                            @endif
                        </span>
                        <div style="font-size: 16px; font-weight: 800; color: var(--navy-900)">{{ $user->name }}</div>
                        <div class="text-sm muted">{{ $user->position ?: 'Chưa cập nhật chức danh' }}</div>
                        <div class="mt-12 flex items-center justify-center gap-8 flex-wrap">
                            @if ($user->role)
                                <span class="badge badge-{{ role_tone($user->role->slug) }} no-dot">{{ $user->role->name }}</span>
                            @endif
                            <span class="badge badge-{{ $user->status === 'active' ? 'success' : 'danger' }} no-dot">
                                {{ $user->status === 'active' ? 'Hoạt động' : 'Đã khoá' }}
                            </span>
                        </div>
                    </div>

                    <div class="kv-grid mt-20">
                        <div class="kv-item"><div class="k">Mã nhân viên</div><div class="v">{{ $user->employee_code ?: '—' }}</div></div>
                        <div class="kv-item"><div class="k">Phòng ban</div><div class="v">{{ $user->department?->name ?? '—' }}</div></div>
                        <div class="kv-item"><div class="k">Quản lý trực tiếp</div><div class="v">{{ $user->manager?->name ?? '—' }}</div></div>
                        <div class="kv-item"><div class="k">Ngày vào làm</div><div class="v">{{ $user->join_date ? vn_date($user->join_date) : '—' }}</div></div>
                        <div class="kv-item"><div class="k">Email</div><div class="v">{{ $user->email }}</div></div>
                        <div class="kv-item"><div class="k">Điện thoại</div><div class="v">{{ $user->phone ?: '—' }}</div></div>
                    </div>
                </div>
            </div>

            <div class="card mt-20">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-shield"></use></svg> Trạng thái & quyền</div>
                </div>
                <div class="card-body">
                    <div class="note-box">
                        Thay đổi <b>Quản lý trực tiếp</b> sẽ ảnh hưởng tới chuỗi duyệt thác nước của các đơn mới. Các đơn đang xử lý vẫn giữ chuỗi duyệt ban đầu.
                    </div>
                    @can('user.toggle')
                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="mt-16"
                              data-confirm="{{ $user->status === 'active' ? 'Khoá tài khoản ' . $user->name . '?' : 'Mở khoá tài khoản ' . $user->name . '?' }}">
                            @csrf
                            <button type="submit" class="btn {{ $user->status === 'active' ? 'btn-warning-soft' : 'btn-success' }} w-full">
                                <svg class="icon"><use href="#i-lock"></use></svg>
                                {{ $user->status === 'active' ? 'Khoá tài khoản này' : 'Mở khoá tài khoản này' }}
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        <div class="">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-edit"></use></svg> Thông tin tài khoản</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')
                        <div class="form-grid">
                            <div class="field">
                                <label for="name">Họ và tên <span class="req">*</span></label>
                                <input id="name" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                                @error('name')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="email">Email công ty <span class="req">*</span></label>
                                <input id="email" name="email" type="email" class="input @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                                @error('email')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="employee_code">Mã nhân viên</label>
                                <input id="employee_code" name="employee_code" type="text" class="input @error('employee_code') is-invalid @enderror" value="{{ old('employee_code', $user->employee_code) }}">
                                @error('employee_code')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="position">Chức danh</label>
                                <input id="position" name="position" type="text" class="input" value="{{ old('position', $user->position) }}">
                            </div>
                            <div class="field">
                                <label for="role_id">Vai trò <span class="req">*</span></label>
                                <select id="role_id" name="role_id" class="select @error('role_id') is-invalid @enderror" required>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('role_id')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="department_id">Phòng ban</label>
                                <select id="department_id" name="department_id" class="select">
                                    <option value="">— Chưa xếp phòng ban —</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" @selected((string) old('department_id', $user->department_id) === (string) $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @error('department_id')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="manager_id">Quản lý trực tiếp</label>
                                <select id="manager_id" name="manager_id" class="select @error('manager_id') is-invalid @enderror" data-searchable data-search-placeholder="Tìm quản lý theo tên…">
                                    <option value="">— Không có —</option>
                                    @foreach ($managers as $manager)
                                        <option value="{{ $manager->id }}" @selected((string) old('manager_id', $user->manager_id) === (string) $manager->id)>
                                            {{ $manager->name }}{{ $manager->position ? ' · ' . $manager->position : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('manager_id')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="phone">Số điện thoại</label>
                                <input id="phone" name="phone" type="text" class="input" value="{{ old('phone', $user->phone) }}">
                            </div>
                            <div class="field">
                                <label for="join_date">Ngày vào làm</label>
                                <input id="join_date" name="join_date" type="date" class="input" value="{{ old('join_date', $user->join_date?->format('Y-m-d')) }}">
                            </div>
                            <div class="field">
                                <label for="status">Trạng thái <span class="req">*</span></label>
                                <select id="status" name="status" class="select @error('status') is-invalid @enderror" required>
                                    <option value="active" @selected(old('status', $user->status) === 'active')>Đang hoạt động</option>
                                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Tạm khoá</option>
                                </select>
                                @error('status')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="password">Mật khẩu mới (tuỳ chọn)</label>
                                <div class="input-group">
                                    <input id="password" name="password" type="password" class="input @error('password') is-invalid @enderror" autocomplete="new-password" placeholder="Để trống nếu không đổi">
                                    <button type="button" class="btn-reveal" data-toggle-password="#password" tabindex="-1" aria-label="Hiện mật khẩu">
                                        <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                    </button>
                                </div>
                                @error('password')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="password_confirmation">Xác nhận mật khẩu mới</label>
                                <div class="input-group">
                                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password">
                                    <button type="button" class="btn-reveal" data-toggle-password="#password_confirmation" tabindex="-1" aria-label="Hiện mật khẩu">
                                        <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Huỷ</a>
                            <button type="submit" class="btn btn-primary">
                                <svg class="icon"><use href="#i-check"></use></svg>
                                Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @can('user.reset_password')
            <div class="card mt-20">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-key"></use></svg> Đặt lại mật khẩu</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                        @csrf
                        <div class="form-grid">
                            <div class="field">
                                <label for="new_password">Mật khẩu mới <span class="req">*</span></label>
                                <div class="input-group">
                                    <input id="new_password" name="new_password" type="password" class="input @error('new_password') is-invalid @enderror" required autocomplete="new-password">
                                    <button type="button" class="btn-reveal" data-toggle-password="#new_password" tabindex="-1" aria-label="Hiện mật khẩu">
                                        <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                    </button>
                                </div>
                                @error('new_password')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="new_password_confirmation">Xác nhận mật khẩu <span class="req">*</span></label>
                                <div class="input-group">
                                    <input id="new_password_confirmation" name="new_password_confirmation" type="password" class="input" required autocomplete="new-password">
                                    <button type="button" class="btn-reveal" data-toggle-password="#new_password_confirmation" tabindex="-1" aria-label="Hiện mật khẩu">
                                        <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-navy" data-confirm="Đặt lại mật khẩu cho {{ $user->name }}?">
                                <svg class="icon"><use href="#i-key"></use></svg>
                                Đặt lại mật khẩu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endcan
        </div>
    </div>
@endsection
