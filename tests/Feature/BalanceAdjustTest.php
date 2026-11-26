<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceAdjustTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', 'Quản trị viên');
        $this->employee = $this->makeUser('employee', 'Nhân viên A');

        $this->leaveType = LeaveType::create([
            'code' => 'AL',
            'name' => 'Phép năm',
            'color' => '#2f6bff',
            'default_days' => 12,
            'deduct_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);

        LeaveBalance::create([
            'user_id' => $this->employee->id,
            'year' => now()->year,
            'leave_type_id' => $this->leaveType->id,
            'entitled_days' => 12,
            'used_days' => 0,
            'carried_over' => 0,
        ]);
    }

    public function test_admin_can_add_and_subtract_days_for_any_employee(): void
    {
        $this->actingAs($this->admin)->post(route('admin.balances.adjust', $this->employee), [
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'days' => 2.5,
            'note' => 'Thưởng phép thâm niên',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(14.5, $this->entitledDays());

        $this->actingAs($this->admin)->post(route('admin.balances.adjust', $this->employee), [
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'days' => -3,
            'note' => 'Trừ phép vi phạm nội quy',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(11.5, $this->entitledDays());
        $this->assertSame(2, AuditLog::where('action', 'balance.adjust')->count());
        $this->assertDatabaseCount('app_notifications', 2);
    }

    public function test_admin_can_grant_days_to_another_leave_type_from_the_balances_screen(): void
    {
        $personal = LeaveType::create([
            'code' => 'CL',
            'name' => 'Nghỉ việc riêng có lương',
            'color' => '#f59e0b',
            'default_days' => 3,
            'deduct_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.balances.index', ['leave_type_id' => $this->leaveType->id]))
            ->assertOk()
            ->assertSee('data-grant-type', false)
            ->assertSee('Nghỉ việc riêng có lương');

        $this->actingAs($this->admin)->post(route('admin.balances.adjust', $this->employee), [
            'leave_type_id' => $personal->id,
            'year' => now()->year,
            'days' => 2,
            'note' => 'Cộng phép riêng có lương',
        ])->assertRedirect()->assertSessionHas('success');

        $balance = LeaveBalance::where([
            'user_id' => $this->employee->id,
            'leave_type_id' => $personal->id,
            'year' => now()->year,
        ])->first();

        $this->assertNotNull($balance);
        $this->assertSame(5.0, (float) $balance->entitled_days);
    }

    public function test_cannot_subtract_more_than_current_entitlement(): void
    {
        $this->actingAs($this->admin)->post(route('admin.balances.adjust', $this->employee), [
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'days' => -13,
            'note' => 'Trừ vượt quỹ',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(12.0, $this->entitledDays());
        $this->assertDatabaseCount('app_notifications', 0);
    }

    public function test_zero_adjustment_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.balances.adjust', $this->employee), [
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'days' => 0,
            'note' => 'Không thay đổi',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(12.0, $this->entitledDays());
    }

    public function test_regular_employee_cannot_adjust_balances(): void
    {
        $this->actingAs($this->employee)->post(route('admin.balances.adjust', $this->admin), [
            'leave_type_id' => $this->leaveType->id,
            'year' => now()->year,
            'days' => 5,
            'note' => 'Tự cộng phép',
        ])->assertForbidden();

        $this->assertSame(12.0, $this->entitledDays());
    }

    public function test_cl_type_is_used_as_annual_leave_when_al_is_missing(): void
    {
        LeaveBalance::where('leave_type_id', $this->leaveType->id)->delete();
        $this->leaveType->delete();

        $annual = LeaveType::create([
            'code' => 'CL',
            'name' => 'Nghỉ phép năm',
            'color' => '#2f6bff',
            'default_days' => 12,
            'deduct_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);

        $this->assertSame($annual->id, LeaveType::annual()?->id);

        $response = $this->actingAs($this->admin)->get(route('admin.balances.index'));

        $response->assertOk();
        $this->assertSame($annual->id, $response->viewData('selectedType')?->id);
    }

    private function entitledDays(): float
    {
        return (float) LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->leaveType->id)
            ->where('year', now()->year)
            ->value('entitled_days');
    }

    private function makeUser(string $roleSlug, string $name): User
    {
        $role = Role::create([
            'slug' => $roleSlug,
            'name' => ucfirst($roleSlug),
        ]);

        return User::create([
            'name' => $name,
            'email' => $roleSlug.'@test.vn',
            'password' => 'password123',
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
