<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockedSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_stays_signed_in(): void
    {
        $user = $this->makeUser('duc.vm@everland.vn', 'active');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_locked_mid_session_is_logged_out(): void
    {
        $user = $this->makeUser('duc.vm@everland.vn', 'active');

        $this->actingAs($user);
        $user->forceFill(['status' => 'inactive'])->save();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login', ['locked' => 1]));

        $this->assertGuest();
    }

    public function test_locked_user_is_logged_out_on_any_page(): void
    {
        $user = $this->makeUser('hr@everland.vn', 'active');

        $this->actingAs($user);
        $user->forceFill(['status' => 'inactive'])->save();

        $this->get(route('notifications.index'))
            ->assertRedirect(route('login', ['locked' => 1]));

        $this->assertGuest();
    }

    public function test_login_page_shows_locked_message(): void
    {
        $this->get(route('login', ['locked' => 1]))
            ->assertOk()
            ->assertSee('Tài khoản đã bị khoá.');
    }

    public function test_locking_through_admin_toggle_kicks_the_user_out(): void
    {
        $admin = $this->makeUser('thuan.pt@everland.vn', 'active');
        $admin->forceFill(['is_protected' => true])->save();

        $target = $this->makeUser('nhan.vien@everland.vn', 'active', 'staff');

        $this->actingAs($admin)
            ->post(route('admin.users.toggle', $target))
            ->assertRedirect();

        $this->assertSame('inactive', $target->fresh()->status);

        $this->actingAs($target->fresh())
            ->get(route('notifications.index'))
            ->assertRedirect(route('login', ['locked' => 1]));

        $this->assertGuest();
    }

    private function makeUser(string $email, string $status, string $roleSlug = 'admin'): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], [
            'name' => $roleSlug === 'admin' ? 'Quản trị hệ thống' : 'Chuyên viên',
        ]);

        return User::create([
            'name'     => 'Người dùng ' . $email,
            'email'    => $email,
            'password' => 'matkhau123',
            'role_id'  => $role->id,
            'status'   => $status,
        ]);
    }
}
