<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Services\LeaveCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private LeaveCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(LeaveCalculator::class);
    }

    public function test_full_week_counts_monday_to_friday_plus_half_saturday(): void
    {
        $days = $this->calculator->workingDays(Carbon::parse('2026-11-23'), Carbon::parse('2026-11-29'));

        $this->assertSame(5.5, $days);
    }

    public function test_saturday_counts_as_half_day(): void
    {
        $days = $this->calculator->workingDays(Carbon::parse('2026-11-28'), Carbon::parse('2026-11-28'));

        $this->assertSame(0.5, $days);
    }

    public function test_saturday_morning_is_half_day_and_afternoon_is_not_working_time(): void
    {
        $saturday = Carbon::parse('2026-11-28');

        $this->assertSame(0.5, $this->calculator->workingDays($saturday, $saturday, 'morning'));
        $this->assertSame(0.0, $this->calculator->workingDays($saturday, $saturday, 'afternoon'));
    }

    public function test_sunday_and_holidays_are_not_working_days(): void
    {
        $sunday = Carbon::parse('2026-11-29');
        $this->assertSame(0.0, $this->calculator->workingDays($sunday, $sunday));

        Holiday::create([
            'name' => 'Ngày lễ thử nghiệm',
            'holiday_date' => '2026-11-25',
        ]);

        $days = $this->calculator->workingDays(Carbon::parse('2026-11-23'), Carbon::parse('2026-11-29'));

        $this->assertSame(4.5, $days);
    }

    public function test_working_days_in_month_include_half_saturdays(): void
    {
        // Tháng 11/2026 có 21 ngày T2-T6 và 4 ngày Thứ 7.
        $days = $this->calculator->workingDaysInMonth(Carbon::parse('2026-11-01'));

        $this->assertSame(23.0, $days);
    }
}
