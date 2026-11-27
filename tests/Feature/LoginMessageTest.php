<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_email_shows_credentials_error(): void
    {
        $this->post(route('login.attempt'), [
            'email'    => 'khong.ton.tai@everland.vn',
            'password' => 'matkhau123',
        ])->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);

        $this->assertGuest();
    }

    public function test_wrong_password_shows_credentials_error(): void
    {
        $this->makeUser('thuan.pt@everland.vn', 'active');

        $this->post(route('login.attempt'), [
            'email'    => 'thuan.pt@everland.vn',
            'password' => 'sai-mat-khau',
        ])->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);

        $this->assertGuest();
    }

    public function test_locked_account_shows_locked_error(): void
    {
        $this->makeUser('nhan.vien@everland.vn', 'inactive');

        $this->post(route('login.attempt'), [
            'email'    => 'nhan.vien@everland.vn',
            'password' => 'matkhau123',
        ])->assertSessionHasErrors(['email' => 'Tài khoản đã bị khoá.']);

        $this->assertGuest();
    }

    public function test_active_account_with_correct_password_logs_in(): void
    {
        $user = $this->makeUser('thuan.pt@everland.vn', 'active');

        $this->post(route('login.attempt'), [
            'email'    => 'thuan.pt@everland.vn',
            'password' => 'matkhau123',
        ])->assertRedirect(route(home_route_name($user)));

        $this->assertAuthenticatedAs($user);
    }

    private function makeUser(string $email, string $status): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Quản trị hệ thống']);

        return User::create([
            'name'     => 'Người dùng ' . $email,
            'email'    => $email,
            'password' => 'matkhau123',
            'role_id'  => $role->id,
            'status'   => $status,
        ]);
    }
}
