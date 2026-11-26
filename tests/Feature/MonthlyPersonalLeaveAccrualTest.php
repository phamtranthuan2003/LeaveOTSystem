<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyPersonalLeaveAccrualTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_adds_one_paid_personal_leave_day_on_the_first_of_the_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 1, 0, 5));

        [$personal, $annual] = $this->seedTypes();
        $employee = $this->makeUser('a@test.vn');
        $inactive = $this->makeUser('b@test.vn', 'inactive');

        $this->balance($employee, $personal, 2026, 3);
        $this->balance($employee, $annual, 2026, 12);

        $this->artisan('leave:accrue-personal')->assertSuccessful();

        $personalBalance = $this->balanceOf($employee, $personal, 2026);
        $this->assertSame(4.0, (float) $personalBalance->entitled_days);
        $this->assertSame('2026-12', $personalBalance->last_accrual_month);

        $annualBalance = $this->balanceOf($employee, $annual, 2026);
        $this->assertSame(12.0, (float) $annualBalance->entitled_days);
        $this->assertNull($annualBalance->last_accrual_month);

        $this->assertDatabaseMissing('leave_balances', ['user_id' => $inactive->id]);
    }

    public function test_it_does_not_accrue_twice_in_the_same_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 1, 0, 5));

        [$personal] = $this->seedTypes();
        $employee = $this->makeUser('a@test.vn');
        $this->balance($employee, $personal, 2026, 3);

        $this->artisan('leave:accrue-personal')->assertSuccessful();

        Carbon::setTestNow(Carbon::create(2026, 12, 20, 9, 0));
        $this->artisan('leave:accrue-personal')->assertSuccessful();

        $this->assertSame(4.0, (float) $this->balanceOf($employee, $personal, 2026)->entitled_days);
    }

    public function test_it_carries_remaining_days_into_the_new_year_and_still_adds_one_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 1, 0, 5));

        [$personal] = $this->seedTypes();
        $employee = $this->makeUser('a@test.vn');
        $this->balance($employee, $personal, 2026, 3, used: 1);

        Carbon::setTestNow(Carbon::create(2027, 1, 1, 0, 5));
        $this->artisan('leave:accrue-personal')->assertSuccessful();

        $balance = $this->balanceOf($employee, $personal, 2027);
        $this->assertSame(4.0, (float) $balance->entitled_days);
        $this->assertSame(2.0, (float) $balance->carried_over);
        $this->assertSame('2027-01', $balance->last_accrual_month);
    }

    public function test_it_fails_when_the_paid_personal_leave_type_is_missing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 1, 0, 5));

        $this->artisan('leave:accrue-personal')->assertFailed();
    }

    public function test_annual_leave_accrues_monthly_from_march_and_resets_in_march(): void
    {
        $annual = LeaveType::create([
            'code' => 'CL',
            'name' => 'Nghỉ phép năm',
            'color' => '#2f6bff',
            'default_days' => 1,
            'deduct_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);
        $employee = $this->makeUser('annual@test.vn');

        Carbon::setTestNow(Carbon::create(2026, 3, 1, 0, 5));
        $this->artisan('leave:accrue-personal')->assertSuccessful();

        $march = $this->balanceOf($employee, $annual, 2026);
        $this->assertSame(1.0, (float) $march->entitled_days);
        $this->assertSame('2026-03', $march->last_accrual_month);

        Carbon::setTestNow(Carbon::create(2026, 12, 1, 0, 5));
        $this->artisan('leave:accrue-personal')->assertSuccessful();
        $this->assertSame(10.0, (float) $this->balanceOf($employee, $annual, 2026)->entitled_days);

        Carbon::setTestNow(Carbon::create(2027, 1, 1, 0, 5));
        $this->artisan('leave:accrue-personal')->assertSuccessful();
        $this->assertSame(11.0, (float) $this->balanceOf($employee, $annual, 2026)->entitled_days);
        $this->assertDatabaseMissing('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'year' => 2027,
        ]);

        Carbon::setTestNow(Carbon::create(2027, 2, 1, 0, 5));
        $this->artisan('leave:accrue-personal')->assertSuccessful();

        $cycle = $this->balanceOf($employee, $annual, 2026);
        $this->assertSame(12.0, (float) $cycle->entitled_days);
        $cycle->used_days = 5;
        $cycle->save();

        Carbon::setTestNow(Carbon::create(2027, 3, 1, 0, 5));
        $this->artisan('leave:accrue-personal')->assertSuccessful();

        $reset = $this->balanceOf($employee, $annual, 2027);
        $this->assertSame(1.0, (float) $reset->entitled_days);
        $this->assertSame(0.0, (float) $reset->carried_over);
        $this->assertSame(0.0, (float) $reset->used_days);
        $this->assertSame('2027-03', $reset->last_accrual_month);
    }

    /**
     * @return array{0: LeaveType, 1: LeaveType}
     */
    private function seedTypes(): array
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

        $annual = LeaveType::create([
            'code' => 'AL',
            'name' => 'Phép năm',
            'color' => '#2f6bff',
            'default_days' => 12,
            'deduct_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'active' => true,
        ]);

        return [$personal, $annual];
    }

    private function makeUser(string $email, string $status = 'active'): User
    {
        $role = Role::firstOrCreate(['slug' => 'employee'], ['name' => 'Nhân viên']);

        return User::create([
            'name' => 'Nhân viên '.$email,
            'email' => $email,
            'password' => 'password123',
            'role_id' => $role->id,
            'status' => $status,
        ]);
    }

    private function balance(User $user, LeaveType $type, int $year, float $entitled, float $used = 0): LeaveBalance
    {
        return LeaveBalance::create([
            'user_id' => $user->id,
            'year' => $year,
            'leave_type_id' => $type->id,
            'entitled_days' => $entitled,
            'used_days' => $used,
            'carried_over' => 0,
        ]);
    }

    private function balanceOf(User $user, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::where([
            'user_id' => $user->id,
            'year' => $year,
            'leave_type_id' => $type->id,
        ])->firstOrFail();
    }
}
