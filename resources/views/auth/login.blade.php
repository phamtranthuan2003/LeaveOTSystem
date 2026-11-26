<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập · Everland Group HR</title>
    <link rel="icon" href="{{ asset('img/everland-1668155348.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
@include('partials.icons')

<div class="login-page">
    <section class="login-visual">
        <div class="login-brand">
            <span class="brand-logo"><img src="{{ asset('img/everland-1668155348.png') }}" alt="Everland Group"></span>
            <div>
                <strong>EVERLAND GROUP</strong>
                <span>Human Resources Suite</span>
            </div>
        </div>

        <div class="login-hero">
            <div class="hero-eyebrow" style="color: rgba(255,255,255,.7)">Nghỉ phép · Làm thêm giờ · Phê duyệt thác nước</div>
            <h1>Quản lý <span>nghỉ phép</span> và làm thêm giờ, liền mạch theo từng cấp.</h1>
            <p>Một nền tảng duy nhất cho nhân viên, trưởng nhóm, trưởng phòng, ban giám đốc và HR — minh bạch quỹ phép, kiểm soát OT và phê duyệt đúng thẩm quyền.</p>

            <div class="login-features">
                <div class="login-feature">
                    <span class="lf-icon"><svg class="icon"><use href="#i-layers"></use></svg></span>
                    Quy trình duyệt thác nước: Trưởng nhóm → Trưởng phòng → Ban Giám đốc → HR
                </div>
                <div class="login-feature">
                    <span class="lf-icon"><svg class="icon"><use href="#i-calendar"></use></svg></span>
                    Lịch nghỉ toàn công ty, ngày lễ và ca OT trong một màn hình
                </div>
                <div class="login-feature">
                    <span class="lf-icon"><svg class="icon"><use href="#i-shield"></use></svg></span>
                    Phân quyền RBAC chi tiết theo vai trò và phòng ban
                </div>
            </div>
        </div>

        <div class="login-stats">
            <div class="login-stat"><strong>13</strong><span>Phòng ban &amp; khối</span></div>
            <div class="login-stat"><strong>6</strong><span>Vai trò phân quyền</span></div>
            <div class="login-stat"><strong>4</strong><span>Cấp phê duyệt</span></div>
            <div class="login-stat"><strong>100%</strong><span>Số hoá quy trình</span></div>
        </div>
    </section>

    <section class="login-form-side">
        <div class="login-card">
            <div class="brand-mini">
                <span class="brand-logo"><img src="{{ asset('img/everland-1668155348.png') }}" alt="Everland Group"></span>
                <strong>EVERLAND GROUP · HR</strong>
            </div>

            <h2>Chào mừng trở lại</h2>
            <p class="sub">Đăng nhập bằng email công ty để tiếp tục quản lý đơn nghỉ phép và OT.</p>

            @if (session('success'))
                <div class="alert alert-success" data-auto-dismiss>
                    <svg class="icon"><use href="#i-check"></use></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <div>
                        <strong>Đăng nhập không thành công</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form class="login-form" method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="field">
                    <label for="email">Email công ty <span class="req">*</span></label>
                    <div class="input-group">
                        <svg class="icon"><use href="#i-mail"></use></svg>
                        <input id="email" name="email" type="email" class="input @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" placeholder="Nhập tài khoản" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="field">
                    <label for="password">Mật khẩu <span class="req">*</span></label>
                    <div class="input-group">
                        <svg class="icon"><use href="#i-lock"></use></svg>
                        <input id="password" name="password" type="password" class="input @error('password') is-invalid @enderror"
                               placeholder="Nhập mật khẩu" required autocomplete="current-password">
                        <button type="button" class="btn-reveal" data-toggle-password="#password" aria-label="Hiện mật khẩu">
                            <svg class="icon icon-sm"><use href="#i-eye"></use></svg>
                        </button>
                    </div>
                </div>

                <div class="login-options">
                    <label class="checkbox-row">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        Ghi nhớ đăng nhập
                    </label>
                    <span class="text-xs muted">Quên mật khẩu? Liên hệ HR</span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    <svg class="icon"><use href="#i-arrow-right"></use></svg>
                    Đăng nhập hệ thống
                </button>
            </form>

            <p class="login-note">© {{ date('Y') }} Everland Group. Hệ thống nội bộ — chỉ dành cho nhân sự được cấp quyền.</p>
        </div>
    </section>
</div>

<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
