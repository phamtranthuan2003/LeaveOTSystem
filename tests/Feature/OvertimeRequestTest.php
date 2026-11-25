<?php

namespace Tests\Feature;

use App\Models\OvertimeRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['slug' => 'admin', 'name' => 'Quản trị hệ thống']);

        $employeeRole = Role::create(['slug' => 'employee', 'name' => 'Nhân viên']);
        $employeeRole->permissions()->attach(
            Permission::firstOrCreate(['slug' => 'ot.create'], ['name' => 'Tạo đơn OT', 'group' => 'Làm thêm giờ (OT)'])->id
        );

        $directorRole = Role::create(['slug' => 'director', 'name' => 'Ban Giám đốc']);

        User::create([
            'name'     => 'Giám đốc',
            'email'    => 'director@test.vn',
            'password' => 'password123',
            'role_id'  => $directorRole->id,
            'status'   => 'active',
        ]);

        $this->employee = User::create([
            'name'     => 'Nhân viên OT',
            'email'    => 'ot@test.vn',
            'password' => 'password123',
            'role_id'  => $employeeRole->id,
            'status'   => 'active',
        ]);
    }

    public function test_overnight_overtime_is_accepted_and_hours_are_calculated(): void
    {
        $date = now()->addDay()->toDateString();

        $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => $date,
            'start_time' => '17:30',
            'end_time'   => '01:00',
            'ot_type'    => 'weekday',
            'reason'     => 'Chạy tiến độ công trình buổi tối',
        ])->assertSessionHasNoErrors();

        $ot = OvertimeRequest::first();
        $this->assertNotNull($ot);
        $this->assertSame(7.5, $ot->hours);
        $this->assertSame('01:00:00', $ot->end_time);
    }

    public function test_overtime_over_sixteen_hours_is_rejected(): void
    {
        $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => now()->addDay()->toDateString(),
            'start_time' => '17:30',
            'end_time'   => '10:00',
            'ot_type'    => 'weekday',
            'reason'     => 'Ca làm việc quá dài để kiểm tra giới hạn',
        ])->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('overtime_requests', 0);
    }

    public function test_equal_start_and_end_time_is_rejected(): void
    {
        $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'end_time'   => '18:00',
            'ot_type'    => 'weekday',
            'reason'     => 'Khung giờ không hợp lệ để kiểm tra',
        ])->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('overtime_requests', 0);
    }

    public function test_overnight_overtime_overlaps_early_morning_on_next_day(): void
    {
        $date = now()->addDay()->toDateString();

        $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => $date,
            'start_time' => '22:00',
            'end_time'   => '02:00',
            'ot_type'    => 'weekday',
            'reason'     => 'Ca đêm chạy máy liên tục',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => now()->addDays(2)->toDateString(),
            'start_time' => '01:00',
            'end_time'   => '03:00',
            'ot_type'    => 'weekday',
            'reason'     => 'Tiếp tục ca đêm sang hôm sau',
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('overtime_requests', 1);
    }
}
