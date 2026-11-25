<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'AL', 'name' => 'Phép năm', 'color' => '#2f6bff', 'default_days' => 12, 'deduct_balance' => true, 'requires_attachment' => false, 'is_paid' => true, 'description' => 'Nghỉ phép hưởng lương theo hợp đồng lao động.'],
            ['code' => 'SL', 'name' => 'Nghỉ ốm', 'color' => '#e5484d', 'default_days' => 10, 'deduct_balance' => true, 'requires_attachment' => true, 'is_paid' => true, 'description' => 'Nghỉ ốm có giấy xác nhận của cơ sở y tế.'],
            ['code' => 'UL', 'name' => 'Nghỉ không lương', 'color' => '#8b5cf6', 'default_days' => 0, 'deduct_balance' => false, 'requires_attachment' => false, 'is_paid' => false, 'description' => 'Nghỉ việc riêng không hưởng lương, cần quản lý phê duyệt.'],
            ['code' => 'ML', 'name' => 'Nghỉ thai sản', 'color' => '#ec4899', 'default_days' => 0, 'deduct_balance' => false, 'requires_attachment' => true, 'is_paid' => true, 'description' => 'Nghỉ thai sản theo quy định, đính kèm hồ sơ y tế.'],
            ['code' => 'CL', 'name' => 'Nghỉ việc riêng có lương', 'color' => '#f59e0b', 'default_days' => 3, 'deduct_balance' => true, 'requires_attachment' => false, 'is_paid' => true, 'description' => 'Kết hôn, tang chế, khám bệnh... theo quy chế công ty.'],
            ['code' => 'CB', 'name' => 'Nghỉ bù OT', 'color' => '#10b981', 'default_days' => 0, 'deduct_balance' => true, 'requires_attachment' => false, 'is_paid' => true, 'description' => 'Nghỉ bù cho thời gian đã làm thêm giờ.'],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(
                ['code' => $type['code']],
                $type + ['active' => true]
            );
        }
    }
}
