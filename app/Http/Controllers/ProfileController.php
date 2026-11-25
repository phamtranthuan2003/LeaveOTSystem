<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone'       => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address'     => ['nullable', 'string', 'max:255'],
            'avatar'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required'  => 'Vui lòng nhập họ tên.',
            'email.unique'   => 'Email này đã được sử dụng bởi tài khoản khác.',
            'avatar.image'   => 'Ảnh đại diện phải là tệp hình ảnh.',
            'avatar.max'     => 'Ảnh đại diện tối đa 2MB.',
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = now()->format('YmdHis') . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/avatars'), $filename);

            if ($user->avatar && str_starts_with($user->avatar, 'uploads/avatars/')) {
                $oldPath = public_path($user->avatar);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $data['avatar'] = 'uploads/avatars/' . $filename;
        }

        $user->update($data);

        AuditLogger::log('profile.update', 'profile', 'Cập nhật hồ sơ cá nhân', $user);

        return back()->with('success', 'Đã cập nhật hồ sơ cá nhân.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required'       => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.required'               => 'Vui lòng nhập mật khẩu mới.',
            'password.min'                    => 'Mật khẩu mới tối thiểu 8 ký tự.',
            'password.confirmed'              => 'Xác nhận mật khẩu không khớp.',
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        AuditLogger::log('password.change', 'profile', 'Đổi mật khẩu đăng nhập');

        return back()->with('success', 'Đã đổi mật khẩu thành công.');
    }
}
