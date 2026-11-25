<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['code' => 'EVG', 'name' => 'Everland Group', 'parent' => null, 'level' => 1, 'sort' => 1,
                'description' => 'Tập đoàn Everland - Ban điều hành cấp cao.'],
            ['code' => 'KVP', 'name' => 'Khối Văn phòng & Nhân sự', 'parent' => 'EVG', 'level' => 2, 'sort' => 1,
                'description' => 'Khối hỗ trợ vận hành toàn tập đoàn.'],
            ['code' => 'HCNS', 'name' => 'Phòng Hành chính - Nhân sự', 'parent' => 'KVP', 'level' => 3, 'sort' => 1,
                'description' => 'Quản trị nhân sự, tuyển dụng và chế độ phúc lợi.'],
            ['code' => 'KT', 'name' => 'Phòng Kế toán', 'parent' => 'KVP', 'level' => 3, 'sort' => 2,
                'description' => 'Kế toán, tài chính và kiểm soát chi phí.'],
            ['code' => 'KKD', 'name' => 'Khối Kinh doanh', 'parent' => 'EVG', 'level' => 2, 'sort' => 2,
                'description' => 'Khối kinh doanh và phát triển thị trường.'],
            ['code' => 'PKD', 'name' => 'Phòng Kinh doanh', 'parent' => 'KKD', 'level' => 3, 'sort' => 1,
                'description' => 'Bán hàng và chăm sóc khách hàng dự án.'],
            ['code' => 'NK1', 'name' => 'Nhóm Kinh doanh 1', 'parent' => 'PKD', 'level' => 4, 'sort' => 1,
                'description' => 'Nhóm kinh doanh khu vực phía Bắc.'],
            ['code' => 'NK2', 'name' => 'Nhóm Kinh doanh 2', 'parent' => 'PKD', 'level' => 4, 'sort' => 2,
                'description' => 'Nhóm kinh doanh khu vực phía Nam.'],
            ['code' => 'PMK', 'name' => 'Phòng Marketing', 'parent' => 'KKD', 'level' => 3, 'sort' => 2,
                'description' => 'Thương hiệu, truyền thông và chiến dịch bán hàng.'],
            ['code' => 'KDA', 'name' => 'Khối Dự án', 'parent' => 'EVG', 'level' => 2, 'sort' => 3,
                'description' => 'Khối quản lý và triển khai dự án bất động sản.'],
            ['code' => 'PTK', 'name' => 'Phòng Thiết kế', 'parent' => 'KDA', 'level' => 3, 'sort' => 1,
                'description' => 'Thiết kế kiến trúc và cảnh quan.'],
            ['code' => 'NTK', 'name' => 'Nhóm Thiết kế', 'parent' => 'PTK', 'level' => 4, 'sort' => 1,
                'description' => 'Nhóm thiết kế triển khai.'],
            ['code' => 'PXD', 'name' => 'Phòng Xây dựng', 'parent' => 'KDA', 'level' => 3, 'sort' => 2,
                'description' => 'Thi công, giám sát và nghiệm thu công trình.'],
        ];

        foreach ($tree as $item) {
            $parent = $item['parent'] ? Department::where('code', $item['parent'])->first() : null;

            Department::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name'        => $item['name'],
                    'parent_id'   => $parent?->id,
                    'level'       => $item['level'],
                    'sort_order'  => $item['sort'],
                    'description' => $item['description'],
                    'active'      => true,
                ]
            );
        }
    }
}
