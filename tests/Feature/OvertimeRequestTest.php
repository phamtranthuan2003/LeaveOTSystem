<?php

namespace Tests\Feature;

use App\Models\Holiday;
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
            'reason'     => 'Ca đêm chạy máy liên tục',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => now()->addDays(2)->toDateString(),
            'start_time' => '01:00',
            'end_time'   => '03:00',
            'reason'     => 'Tiếp tục ca đêm sang hôm sau',
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('overtime_requests', 1);
    }

    public function test_ot_type_and_multiplier_are_detected_from_the_date(): void
    {
        $monday = now()->startOfWeek()->addWeek();
        $saturday = $monday->copy()->addDays(5);
        $holiday = $monday->copy()->addDays(1);

        Holiday::create(['name' => 'Ngày lễ kiểm thử', 'holiday_date' => $holiday->toDateString()]);

        $post = fn (string $date) => $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => $date,
            'start_time' => '18:00',
            'end_time'   => '20:00',
            'reason'     => 'Kiểm tra loại ngày OT tự động',
        ]);

        $post($monday->toDateString())->assertSessionHasNoErrors();
        $post($saturday->toDateString())->assertSessionHasNoErrors();
        $post($holiday->toDateString())->assertSessionHasNoErrors();

        $byDate = OvertimeRequest::all()->keyBy(fn ($ot) => $ot->ot_date->toDateString());

        $this->assertSame('weekday', $byDate[$monday->toDateString()]->ot_type);
        $this->assertSame(1.5, $byDate[$monday->toDateString()]->multiplier);
        $this->assertSame('weekend', $byDate[$saturday->toDateString()]->ot_type);
        $this->assertSame(2.0, $byDate[$saturday->toDateString()]->multiplier);
        $this->assertSame('holiday', $byDate[$holiday->toDateString()]->ot_type);
        $this->assertSame(3.0, $byDate[$holiday->toDateString()]->multiplier);
    }

    public function test_client_supplied_ot_type_is_ignored(): void
    {
        $monday = now()->startOfWeek()->addWeek();

        $this->actingAs($this->employee)->post(route('overtime.store'), [
            'ot_date'    => $monday->toDateString(),
            'start_time' => '18:00',
            'end_time'   => '20:00',
            'ot_type'    => 'holiday',
            'reason'     => 'Thử gửi loại ngày sai lên server',
        ])->assertSessionHasNoErrors();

        $ot = OvertimeRequest::first();
        $this->assertSame('weekday', $ot->ot_type);
        $this->assertSame(1.5, $ot->multiplier);
    }
}
