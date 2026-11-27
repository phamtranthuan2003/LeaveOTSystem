<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(home_route_name());
        }

        return view('auth.login', ['locked' => $request->boolean('locked')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Vui lòng nhập email.',
            'email.email'       => 'Email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $remember = $request->boolean('remember');

        if (!Auth::attempt([...$credentials, 'status' => 'active'], $remember)) {
            $user = User::where('email', $credentials['email'])->first();

            if ($user && Hash::check($credentials['password'], $user->password) && $user->status !== 'active') {
                throw ValidationException::withMessages([
                    'email' => 'Tài khoản đã bị khoá.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        $request->session()->regenerate();

        AuditLogger::log('login', 'auth', 'Đăng nhập hệ thống');

        return redirect()->intended(route(home_route_name(Auth::user())));
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLogger::log('logout', 'auth', 'Đăng xuất khỏi hệ thống');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất.');
    }
}
