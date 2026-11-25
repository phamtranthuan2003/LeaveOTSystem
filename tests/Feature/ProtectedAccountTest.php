<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $protected;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['slug' => 'admin', 'name' => 'Quản trị hệ thống']);

        $this->admin = $this->makeUser('admin@test.vn', $adminRole);

        $this->protected = $this->makeUser('thuan.pt@everland.vn', $adminRole);
        $this->protected->forceFill(['is_protected' => true])->save();
    }

    public function test_protected_account_cannot_be_edited(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->protected))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->protected), [
                'name'   => 'Tên bị đổi',
                'status' => 'inactive',
            ])
            ->assertSessionHas('error');

        $this->assertSame('thuan.pt@everland.vn', $this->protected->fresh()->email);
        $this->assertSame('active', $this->protected->fresh()->status);
    }

    public function test_protected_account_cannot_be_locked_or_unlocked(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.toggle', $this->protected))
            ->assertSessionHas('error');

        $this->assertSame('active', $this->protected->fresh()->status);
    }

    public function test_protected_account_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->protected))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->protected->id]);
    }

    public function test_protected_account_password_cannot_be_reset(): void
    {
        $originalHash = $this->protected->fresh()->password;

        $this->actingAs($this->admin)
            ->post(route('admin.users.reset-password', $this->protected), [
                'new_password'              => 'matkhaumoi123',
                'new_password_confirmation' => 'matkhaumoi123',
            ])
            ->assertSessionHas('error');

        $this->assertSame($originalHash, $this->protected->fresh()->password);
    }

    public function test_protected_account_shows_badge_without_action_buttons(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Được bảo vệ');
    }

    private function makeUser(string $email, Role $role): User
    {
        return User::create([
            'name'     => 'Người dùng ' . $email,
            'email'    => $email,
            'password' => 'password123',
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }
}
