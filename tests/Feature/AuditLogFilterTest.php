<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;
    private User $actorA;
    private User $actorB;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['slug' => 'hr', 'name' => 'Nhân sự']);
        $role->permissions()->attach(
            Permission::firstOrCreate(['slug' => 'audit.view'], ['name' => 'Xem nhật ký hoạt động'])->id
        );

        $this->viewer = $this->makeUser('viewer@test.vn', $role, 'Người xem nhật ký');
        $this->actorA = $this->makeUser('actor.a@test.vn', $role, 'Trần Văn A');
        $this->actorB = $this->makeUser('actor.b@test.vn', $role, 'Lê Thị B');

        AuditLog::create([
            'user_id' => $this->actorA->id,
            'action' => 'create',
            'module' => 'Nghỉ phép',
            'description' => 'Tạo đơn nghỉ phép NP-001',
        ]);
        AuditLog::create([
            'user_id' => $this->actorB->id,
            'action' => 'update',
            'module' => 'Làm thêm giờ',
            'description' => 'Cập nhật đơn OT-002',
        ]);
    }

    public function test_audit_log_page_can_be_filtered_by_actor(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('admin.audit.index', ['actor' => $this->actorA->id]));

        $response->assertOk()
            ->assertSee('Tạo đơn nghỉ phép NP-001')
            ->assertDontSee('Cập nhật đơn OT-002');
    }

    public function test_actor_filter_combines_with_module_filter(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('admin.audit.index', ['actor' => $this->actorA->id, 'module' => 'Làm thêm giờ']));

        $response->assertOk()
            ->assertSee('Không có bản ghi phù hợp')
            ->assertDontSee('Tạo đơn nghỉ phép NP-001');
    }

    private function makeUser(string $email, Role $role, string $name): User
    {
        return User::create([
            'role_id' => $role->id,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('Password@123'),
            'position' => 'Nhân viên',
            'active' => true,
            'status' => 'active',
        ]);
    }
}