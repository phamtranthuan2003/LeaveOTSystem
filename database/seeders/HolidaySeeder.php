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
            // Lịch nghỉ 2027 theo thông báo Chính phủ ngày 26/11/2026, gồm 27/7 và 24/11 theo luật sửa đổi Bộ luật Lao động.
            ['Tết Dương lịch 2027', '2027-01-01', 'Nghỉ Tết Dương lịch'],
            ['Tết Dương lịch 2027 - Thứ Bảy', '2027-01-02', 'Nghỉ cuối tuần trong kỳ nghỉ'],
            ['Tết Dương lịch 2027 - Chủ nhật', '2027-01-03', 'Nghỉ cuối tuần trong kỳ nghỉ'],
            ['Tết Nguyên đán Đinh Mùi - 28 tháng Chạp', '2027-02-04', 'Bắt đầu kỳ nghỉ Tết Âm lịch'],
            ['Tết Nguyên đán Đinh Mùi - 29 tháng Chạp', '2027-02-05', 'Nghỉ Tết Âm lịch'],
            ['Tết Nguyên đán Đinh Mùi - Mùng 1 Tết', '2027-02-06', 'Mùng 1 Tết'],
            ['Tết Nguyên đán Đinh Mùi - Mùng 2 Tết', '2027-02-07', 'Mùng 2 Tết'],
            ['Tết Nguyên đán Đinh Mùi - Mùng 3 Tết', '2027-02-08', 'Mùng 3 Tết'],
            ['Tết Nguyên đán Đinh Mùi - Mùng 4 Tết (nghỉ bù)', '2027-02-09', 'Nghỉ bù do mùng 1, mùng 2 trùng cuối tuần'],
            ['Tết Nguyên đán Đinh Mùi - Mùng 5 Tết (nghỉ bù)', '2027-02-10', 'Nghỉ bù do mùng 1, mùng 2 trùng cuối tuần'],
            ['Giỗ Tổ Hùng Vương 2027', '2027-04-16', '10/3 Âm lịch'],
            ['Giỗ Tổ Hùng Vương 2027 - Thứ Bảy', '2027-04-17', 'Nghỉ cuối tuần trong kỳ nghỉ'],
            ['Giỗ Tổ Hùng Vương 2027 - Chủ nhật', '2027-04-18', 'Nghỉ cuối tuần trong kỳ nghỉ'],
            ['Ngày Giải phóng miền Nam 2027', '2027-04-30', 'Nghỉ lễ 30/4'],
            ['Quốc tế Lao động 2027', '2027-05-01', 'Nghỉ lễ 1/5'],
            ['Nghỉ cuối tuần (30/4 - 1/5) 2027', '2027-05-02', 'Nghỉ cuối tuần trong kỳ nghỉ'],
            ['Nghỉ bù Quốc tế Lao động 2027', '2027-05-03', 'Nghỉ bù do 1/5 trùng Thứ Bảy'],
            ['Ngày Tổ quốc tri ân - Thương binh, liệt sĩ 2027', '2027-07-27', 'Ngày lễ hưởng nguyên lương'],
            ['Quốc khánh 2027', '2027-09-02', 'Nghỉ Quốc khánh'],
            ['Quốc khánh 2027 - ngày thứ 2', '2027-09-03', 'Ngày nghỉ liền kề'],
            ['Quốc khánh 2027 - Thứ Bảy', '2027-09-04', 'Nghỉ cuối tuần trong kỳ nghỉ'],
            ['Quốc khánh 2027 - Chủ nhật', '2027-09-05', 'Nghỉ cuối tuần trong kỳ nghỉ'],
            ['Ngày Văn hóa Việt Nam 2027', '2027-11-24', 'Ngày lễ hưởng nguyên lương'],
        ];

        foreach ($holidays as [$name, $date, $description]) {
            Holiday::updateOrCreate(
                ['holiday_date' => $date],
                ['name' => $name, 'description' => $description]
            );
        }
    }
}
