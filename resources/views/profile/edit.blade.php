@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân')
@section('subtitle', 'Cập nhật thông tin liên hệ và bảo mật tài khoản')

@section('content')
    <div class="grid grid-1-2" style="align-items: start">
        <div class="grid" style="align-content: start">
            <div class="card">
                <div class="card-body profile-side">
                    <span class="avatar avatar-xl {{ $user->role ? role_tone($user->role->slug) : '' }}" id="avatar-preview-wrap">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" id="avatar-preview">
                        @else
                            <span id="avatar-initials">{{ initials($user->name) }}</span>
                            <img src="" alt="" id="avatar-preview" style="display: none">
                        @endif
                    </span>
                    <h2>{{ $user->name }}</h2>
                    <div class="role-line">{{ $user->position ?: 'Nhân viên' }}</div>
                    <div class="mt-8">
                        <span class="badge badge-{{ role_tone($user->role?->slug) }} no-dot">{{ $user->role?->name }}</span>
                    </div>

                    <div class="profile-meta">
                        <div class="pm-row">
                            <svg class="icon"><use href="#i-briefcase"></use></svg>
                            {{ $user->employee_code }} · {{ $user->department?->name ?? 'Chưa xếp phòng' }}
                        </div>
                        <div class="pm-row">
                            <svg class="icon"><use href="#i-mail"></use></svg>
                            {{ $user->email }}
                        </div>
                        <div class="pm-row">
                            <svg class="icon"><use href="#i-phone"></use></svg>
                            {{ $user->phone ?: 'Chưa cập nhật số điện thoại' }}
                        </div>
                        <div class="pm-row">
                            <svg class="icon"><use href="#i-user"></use></svg>
                            Cấp trên: {{ $user->manager?->name ?? 'Không có' }}
                        </div>
                        <div class="pm-row">
                            <svg class="icon"><use href="#i-calendar"></use></svg>
                            Vào làm {{ vn_date($user->join_date, 'd/m/Y') }}
                        </div>
                        <div class="pm-row">
                            <svg class="icon"><use href="#i-pin"></use></svg>
                            {{ $user->address ?: 'Chưa cập nhật địa chỉ' }}
                        </div>
                    </div>

                    <div class="divider"></div>
                    <div class="flex gap-8" style="justify-content: center; flex-wrap: wrap">
                        <a href="{{ route('balances.index') }}" class="btn btn-soft btn-sm">
                            <svg class="icon"><use href="#i-wallet"></use></svg>
                            Quỹ phép
                        </a>
                        <a href="{{ route('notifications.index') }}" class="btn btn-outline btn-sm">
                            <svg class="icon"><use href="#i-bell"></use></svg>
                            Thông báo
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-shield"></use></svg> Trạng thái tài khoản</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="flex justify-between mb-12 text-sm">
                        <span class="muted">Trạng thái</span>
                        <span class="badge badge-{{ $user->status === 'active' ? 'success' : 'muted' }} no-dot">
                            {{ $user->status === 'active' ? 'Đang hoạt động' : 'Tạm khoá' }}
                        </span>
                    </div>
                    <div class="flex justify-between mb-12 text-sm">
                        <span class="muted">Vai trò</span>
                        <span class="strong">{{ $user->role?->name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="muted">Ngày tham gia</span>
                        <span class="strong">{{ vn_date($user->created_at, 'd/m/Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid" style="align-content: start">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-user"></use></svg> Thông tin cá nhân</div>
                        <div class="card-sub">Thông tin này hiển thị trong đơn nghỉ phép và báo cáo</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-body form-card-body">
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
                                <label for="phone">Số điện thoại</label>
                                <input id="phone" name="phone" type="text" class="input @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" maxlength="30" placeholder="09xx xxx xxx">
                                @error('phone')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="date_of_birth">Ngày sinh</label>
                                <input id="date_of_birth" name="date_of_birth" type="date" class="input @error('date_of_birth') is-invalid @enderror"
                                       value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}" max="{{ now()->subDay()->toDateString() }}">
                                @error('date_of_birth')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field span-2">
                                <label for="address">Địa chỉ</label>
                                <input id="address" name="address" type="text" class="input @error('address') is-invalid @enderror" value="{{ old('address', $user->address) }}" maxlength="255" placeholder="Số nhà, đường, quận/huyện, tỉnh/thành">
                                @error('address')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field span-2">
                                <label for="avatar">Ảnh đại diện</label>
                                <div class="upload-zone">
                                    <svg class="icon icon-lg"><use href="#i-user"></use></svg>
                                    <div style="flex: 1; min-width: 0">
                                        <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="w-full">
                                        <div class="help" style="margin-top: 4px">JPG, PNG hoặc WEBP · tối đa 2MB. Ảnh cũ sẽ được thay thế.</div>
                                    </div>
                                </div>
                                @error('avatar')<span class="error-text">{{ $message }}</span>@enderror
                            </div>

                            <div class="form-section-title span-2">Thông tin công việc (không thể chỉnh sửa)</div>
                            <div class="field">
                                <label>Mã nhân sự</label>
                                <input type="text" class="input" value="{{ $user->employee_code }}" disabled>
                            </div>
                            <div class="field">
                                <label>Phòng ban</label>
                                <input type="text" class="input" value="{{ $user->department?->name ?? '—' }}" disabled>
                            </div>
                            <div class="field">
                                <label>Chức danh</label>
                                <input type="text" class="input" value="{{ $user->position ?: '—' }}" disabled>
                            </div>
                            <div class="field">
                                <label>Vai trò hệ thống</label>
                                <input type="text" class="input" value="{{ $user->role?->name ?? '—' }}" disabled>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="reset" class="btn btn-outline">Hoàn tác</button>
                            <button type="submit" class="btn btn-primary">
                                <svg class="icon"><use href="#i-check"></use></svg>
                                Lưu thay đổi
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title"><svg class="icon"><use href="#i-lock"></use></svg> Đổi mật khẩu</div>
                        <div class="card-sub">Nên đổi mật khẩu định kỳ 3–6 tháng</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="card-body form-card-body">
                        <div class="form-grid">
                            <div class="field span-2">
                                <label for="current_password">Mật khẩu hiện tại <span class="req">*</span></label>
                                <div class="input-group">
                                    <svg class="icon"><use href="#i-lock"></use></svg>
                                    <input id="current_password" name="current_password" type="password" class="input @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                                    <button type="button" class="btn-reveal" data-toggle-password="#current_password" aria-label="Hiện mật khẩu">
                                        <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                    </button>
                                </div>
                                @error('current_password')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="password">Mật khẩu mới <span class="req">*</span></label>
                                <div class="input-group">
                                    <svg class="icon"><use href="#i-key"></use></svg>
                                    <input id="password" name="password" type="password" class="input @error('password') is-invalid @enderror" required minlength="8" autocomplete="new-password">
                                    <button type="button" class="btn-reveal" data-toggle-password="#password" tabindex="-1" aria-label="Hiện mật khẩu">
                                        <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                    </button>
                                </div>
                                @error('password')<span class="error-text">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="password_confirmation">Xác nhận mật khẩu <span class="req">*</span></label>
                                <div class="input-group">
                                    <svg class="icon"><use href="#i-key"></use></svg>
                                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" required minlength="8" autocomplete="new-password">
                                    <button type="button" class="btn-reveal" data-toggle-password="#password_confirmation" tabindex="-1" aria-label="Hiện mật khẩu">
                                        <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="help mt-8">Mật khẩu tối thiểu 8 ký tự, nên bao gồm chữ hoa, chữ thường và số.</div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-navy">
                                <svg class="icon"><use href="#i-shield"></use></svg>
                                Cập nhật mật khẩu
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        function initAvatarPreview() {
        var input = document.getElementById('avatar');
        var preview = document.getElementById('avatar-preview');
        var initials = document.getElementById('avatar-initials');
        if (!input || !preview) return;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (event) {
                preview.src = event.target.result;
                preview.style.display = 'block';
                if (initials) initials.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });
        }
        document.addEventListener('DOMContentLoaded', initAvatarPreview);
        document.addEventListener('app:page-updated', initAvatarPreview);
    })();
</script>
@endpush
