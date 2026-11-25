<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['Tết Dương lịch 2026', '2026-01-01', 'Nghỉ Tết Dương lịch'],
            ['Tết Nguyên Đán Bính Ngọ - ngày 1', '2026-02-16', 'Nghỉ Tết Âm lịch'],
            ['Tết Nguyên Đán Bính Ngọ - ngày 2', '2026-02-17', 'Mùng 1 Tết'],
            ['Tết Nguyên Đán Bính Ngọ - ngày 3', '2026-02-18', 'Mùng 2 Tết'],
            ['Tết Nguyên Đán Bính Ngọ - ngày 4', '2026-02-19', 'Mùng 3 Tết'],
            ['Tết Nguyên Đán Bính Ngọ - ngày 5', '2026-02-20', 'Nghỉ bù Tết'],
            ['Giỗ Tổ Hùng Vương 2026', '2026-04-26', '10/3 Âm lịch'],
            ['Ngày Giải phóng miền Nam', '2026-04-30', 'Nghỉ lễ 30/4'],
            ['Quốc tế Lao động', '2026-05-01', 'Nghỉ lễ 1/5'],
            ['Quốc khánh 2026', '2026-09-02', 'Nghỉ Quốc khánh'],
            ['Tết Dương lịch 2027', '2027-01-01', 'Nghỉ Tết Dương lịch'],
            ['Tết Nguyên Đán Đinh Mùi - ngày 1', '2027-02-05', 'Nghỉ Tết Âm lịch'],
            ['Tết Nguyên Đán Đinh Mùi - ngày 2', '2027-02-06', 'Mùng 1 Tết'],
            ['Tết Nguyên Đán Đinh Mùi - ngày 3', '2027-02-07', 'Mùng 2 Tết'],
            ['Tết Nguyên Đán Đinh Mùi - ngày 4', '2027-02-08', 'Mùng 3 Tết'],
            ['Tết Nguyên Đán Đinh Mùi - ngày 5', '2027-02-09', 'Nghỉ bù Tết'],
            ['Giỗ Tổ Hùng Vương 2027', '2027-04-16', '10/3 Âm lịch'],
            ['Ngày Giải phóng miền Nam 2027', '2027-04-30', 'Nghỉ lễ 30/4'],
            ['Quốc tế Lao động 2027', '2027-05-01', 'Nghỉ lễ 1/5'],
            ['Quốc khánh 2027', '2027-09-02', 'Nghỉ Quốc khánh'],
        ];

        foreach ($holidays as [$name, $date, $description]) {
            Holiday::updateOrCreate(
                ['holiday_date' => $date],
                ['name' => $name, 'description' => $description]
            );
        }
    }
}
