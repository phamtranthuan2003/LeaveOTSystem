<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => 'Everland Group', 'group' => 'Thông tin công ty', 'label' => 'Tên công ty'],
            ['key' => 'company_address', 'value' => 'Tòa nhà Everland, Khu đô thị sinh thái, Hà Nội', 'group' => 'Thông tin công ty', 'label' => 'Địa chỉ trụ sở'],
            ['key' => 'company_phone', 'value' => '1900 6789', 'group' => 'Thông tin công ty', 'label' => 'Hotline'],
            ['key' => 'company_email', 'value' => 'hr@everland.vn', 'group' => 'Thông tin công ty', 'label' => 'Email liên hệ'],
            ['key' => 'work_start', 'value' => '08:00', 'group' => 'Chính sách nghỉ phép', 'label' => 'Giờ bắt đầu làm việc'],
            ['key' => 'work_end', 'value' => '17:30', 'group' => 'Chính sách nghỉ phép', 'label' => 'Giờ kết thúc làm việc'],
            ['key' => 'annual_leave_default', 'value' => '1', 'group' => 'Chính sách nghỉ phép', 'label' => 'Phép năm mặc định (ngày)'],
            ['key' => 'max_carry_over', 'value' => '5', 'group' => 'Chính sách nghỉ phép', 'label' => 'Số ngày phép tối đa được chuyển năm'],
            ['key' => 'ot_rate_weekday', 'value' => '1.5', 'group' => 'Hệ số làm thêm giờ (OT)', 'label' => 'Hệ số OT ngày thường'],
            ['key' => 'ot_rate_weekend', 'value' => '2', 'group' => 'Hệ số làm thêm giờ (OT)', 'label' => 'Hệ số OT cuối tuần'],
            ['key' => 'ot_rate_holiday', 'value' => '3', 'group' => 'Hệ số làm thêm giờ (OT)', 'label' => 'Hệ số OT ngày lễ'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
