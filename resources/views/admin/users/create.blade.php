@extends('layouts.app')

@section('title', 'Thêm nhân sự')
@section('subtitle', 'Tạo tài khoản mới và thiết lập cấp bậc quản lý trong chuỗi duyệt')

@section('actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm">
        <svg class="icon"><use href="#i-arrow-left"></use></svg>
        Quay lại danh sách
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="grid grid-1-2">
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-info"></use></svg> Hướng dẫn thiết lập</div>
                </div>
                <div class="card-body">
                    <div class="note-box">
                        <b>Chuỗi duyệt thác nước</b> được xác định theo <b>Quản lý trực tiếp</b>. Hệ thống sẽ tự động lần theo cấp trên
                        (Trưởng nhóm → Trưởng phòng → Giám đốc) để tạo các bước duyệt cho đơn nghỉ phép và OT.
                    </div>
                    <div class="note-box mt-12">
                        Đơn nghỉ phép có thêm bước duyệt cuối của <b>HR</b>. Đơn OT chỉ duyệt qua chuỗi quản lý.
                    </div>
                    <div class="kv-grid mt-16">
                        <div class="kv-item"><div class="k">Cấp bậc vai trò</div><div class="v">Theo ma trận quyền</div></div>
                        <div class="kv-item"><div class="k">Quỹ phép</div><div class="v">Tự khởi tạo năm hiện tại</div></div>
                        <div class="kv-item"><div class="k">Mật khẩu</div><div class="v">Tối thiểu 8 ký tự</div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><svg class="icon"><use href="#i-users"></use></svg> Thông tin tài khoản</div>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Họ và tên <span class="req">*</span></label>
                            <input id="name" name="name" type="text" class="input @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
                            @error('name')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="email">Email công ty <span class="req">*</span></label>
                            <input id="email" name="email" type="email" class="input @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="employee_code">Mã nhân viên</label>
                            <input id="employee_code" name="employee_code" type="text" class="input @error('employee_code') is-invalid @enderror" value="{{ old('employee_code', $nextCode) }}" placeholder="{{ $nextCode }}">
                            @error('employee_code')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="position">Chức danh</label>
                            <input id="position" name="position" type="text" class="input" value="{{ old('position') }}" placeholder="VD: Chuyên viên nhân sự">
                        </div>
                        <div class="field">
                            <label for="password">Mật khẩu <span class="req">*</span></label>
                            <div class="input-group">
                                <input id="password" name="password" type="password" class="input @error('password') is-invalid @enderror" required autocomplete="new-password">
                                <button type="button" class="btn-reveal" data-toggle-password="#password" tabindex="-1" aria-label="Hiện mật khẩu">
                                    <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                </button>
                            </div>
                            @error('password')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Xác nhận mật khẩu <span class="req">*</span></label>
                            <div class="input-group">
                                <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
                                <button type="button" class="btn-reveal" data-toggle-password="#password_confirmation" tabindex="-1" aria-label="Hiện mật khẩu">
                                    <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                </button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="role_id">Vai trò <span class="req">*</span></label>
                            <select id="role_id" name="role_id" class="select @error('role_id') is-invalid @enderror" required>
                                <option value="">— Chọn vai trò —</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected((string) old('role_id', $defaultRoleId) === (string) $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                            @error('role_id')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="department_id">Phòng ban</label>
                            <select id="department_id" name="department_id" class="select">
                                <option value="">— Chưa xếp phòng ban —</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="manager_id">Quản lý trực tiếp</label>
                            <select id="manager_id" name="manager_id" class="select" data-searchable data-search-placeholder="Tìm quản lý theo tên…">
                                <option value="">— Không có —</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}" @selected((string) old('manager_id') === (string) $manager->id)>
                                        {{ $manager->name }}{{ $manager->position ? ' · ' . $manager->position : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('manager_id')<span class="error-text">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="phone">Số điện thoại</label>
                            <input id="phone" name="phone" type="text" class="input" value="{{ old('phone') }}">
                        </div>
                        <div class="field">
                            <label for="join_date">Ngày vào làm</label>
                            <input id="join_date" name="join_date" type="date" class="input" value="{{ old('join_date') }}">
                        </div>
                        <div class="field">
                            <label for="status">Trạng thái <span class="req">*</span></label>
                            <select id="status" name="status" class="select" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Đang hoạt động</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Tạm khoá</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Huỷ</a>
                        <button type="submit" class="btn btn-primary">
                            <svg class="icon"><use href="#i-check"></use></svg>
                            Tạo tài khoản
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
