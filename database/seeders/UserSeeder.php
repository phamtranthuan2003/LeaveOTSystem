<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            ['code' => 'EVG-0001', 'name' => 'Nguyễn Hoàng Anh', 'email' => 'admin@everland.vn', 'role' => 'admin', 'dept' => 'EVG', 'manager' => null, 'position' => 'Quản trị hệ thống', 'join' => '2019-03-01'],
            ['code' => 'EVG-0002', 'name' => 'Trần Minh Quân', 'email' => 'director@everland.vn', 'role' => 'director', 'dept' => 'EVG', 'manager' => null, 'position' => 'Tổng Giám đốc', 'join' => '2018-01-15'],
            ['code' => 'EVG-0003', 'name' => 'Phạm Thu Hà', 'email' => 'hr@everland.vn', 'role' => 'hr', 'dept' => 'HCNS', 'manager' => 'director@everland.vn', 'position' => 'Trưởng phòng Hành chính - Nhân sự', 'join' => '2019-06-10'],
            ['code' => 'EVG-0004', 'name' => 'Lê Văn Hùng', 'email' => 'manager@everland.vn', 'role' => 'manager', 'dept' => 'KKD', 'manager' => 'director@everland.vn', 'position' => 'Giám đốc Khối Kinh doanh', 'join' => '2018-04-02'],
            ['code' => 'EVG-0005', 'name' => 'Đỗ Thị Mai', 'email' => 'manager2@everland.vn', 'role' => 'manager', 'dept' => 'KDA', 'manager' => 'director@everland.vn', 'position' => 'Giám đốc Khối Dự án', 'join' => '2019-02-18'],
            ['code' => 'EVG-0006', 'name' => 'Võ Quốc Bảo', 'email' => 'ketoan@everland.vn', 'role' => 'manager', 'dept' => 'KT', 'manager' => 'director@everland.vn', 'position' => 'Trưởng phòng Kế toán', 'join' => '2020-07-01'],
            ['code' => 'EVG-0007', 'name' => 'Nguyễn Thị Lan', 'email' => 'leader@everland.vn', 'role' => 'leader', 'dept' => 'NK1', 'manager' => 'manager@everland.vn', 'position' => 'Trưởng nhóm Kinh doanh 1', 'join' => '2020-03-09'],
            ['code' => 'EVG-0008', 'name' => 'Bùi Anh Tuấn', 'email' => 'leader2@everland.vn', 'role' => 'leader', 'dept' => 'NK2', 'manager' => 'manager@everland.vn', 'position' => 'Trưởng nhóm Kinh doanh 2', 'join' => '2020-09-14'],
            ['code' => 'EVG-0009', 'name' => 'Hoàng Ngọc Diệp', 'email' => 'leader3@everland.vn', 'role' => 'leader', 'dept' => 'NTK', 'manager' => 'manager2@everland.vn', 'position' => 'Trưởng nhóm Thiết kế', 'join' => '2021-01-11'],
            ['code' => 'EVG-0010', 'name' => 'Trần Văn Nam', 'email' => 'employee@everland.vn', 'role' => 'employee', 'dept' => 'NK1', 'manager' => 'leader@everland.vn', 'position' => 'Nhân viên Kinh doanh', 'join' => '2022-02-14'],
            ['code' => 'EVG-0011', 'name' => 'Lý Thị Hương', 'email' => 'huong.ly@everland.vn', 'role' => 'employee', 'dept' => 'NK1', 'manager' => 'leader@everland.vn', 'position' => 'Nhân viên Kinh doanh', 'join' => '2022-05-23'],
            ['code' => 'EVG-0012', 'name' => 'Phan Đức Thắng', 'email' => 'thang.phan@everland.vn', 'role' => 'employee', 'dept' => 'NK1', 'manager' => 'leader@everland.vn', 'position' => 'Nhân viên Kinh doanh', 'join' => '2023-03-06'],
            ['code' => 'EVG-0013', 'name' => 'Đặng Minh Khôi', 'email' => 'khoi.dang@everland.vn', 'role' => 'employee', 'dept' => 'NK2', 'manager' => 'leader2@everland.vn', 'position' => 'Nhân viên Kinh doanh', 'join' => '2022-08-01'],
            ['code' => 'EVG-0014', 'name' => 'Nguyễn Thu Trang', 'email' => 'trang.nguyen@everland.vn', 'role' => 'employee', 'dept' => 'NK2', 'manager' => 'leader2@everland.vn', 'position' => 'Nhân viên Kinh doanh', 'join' => '2023-01-09'],
            ['code' => 'EVG-0015', 'name' => 'Lê Quốc Cường', 'email' => 'cuong.le@everland.vn', 'role' => 'employee', 'dept' => 'PKD', 'manager' => 'manager@everland.vn', 'position' => 'Chuyên viên Kinh doanh', 'join' => '2021-11-22'],
            ['code' => 'EVG-0016', 'name' => 'Trịnh Mỹ Linh', 'email' => 'linh.trinh@everland.vn', 'role' => 'employee', 'dept' => 'HCNS', 'manager' => 'hr@everland.vn', 'position' => 'Chuyên viên Nhân sự', 'join' => '2023-04-17'],
            ['code' => 'EVG-0017', 'name' => 'Mai Văn Phúc', 'email' => 'phuc.mai@everland.vn', 'role' => 'employee', 'dept' => 'KT', 'manager' => 'ketoan@everland.vn', 'position' => 'Kế toán viên', 'join' => '2022-10-03'],
            ['code' => 'EVG-0018', 'name' => 'Nguyễn Hải Yến', 'email' => 'yen.nguyen@everland.vn', 'role' => 'employee', 'dept' => 'PMK', 'manager' => 'manager@everland.vn', 'position' => 'Chuyên viên Marketing', 'join' => '2022-06-13'],
            ['code' => 'EVG-0019', 'name' => 'Vũ Đức Long', 'email' => 'long.vu@everland.vn', 'role' => 'employee', 'dept' => 'NTK', 'manager' => 'leader3@everland.vn', 'position' => 'Kiến trúc sư', 'join' => '2021-07-05'],
            ['code' => 'EVG-0020', 'name' => 'Bùi Thanh Tâm', 'email' => 'tam.bui@everland.vn', 'role' => 'employee', 'dept' => 'NTK', 'manager' => 'leader3@everland.vn', 'position' => 'Hoạ viên 3D', 'join' => '2023-02-20'],
            ['code' => 'EVG-0021', 'name' => 'Ngô Thị Hồng', 'email' => 'hong.ngo@everland.vn', 'role' => 'employee', 'dept' => 'PXD', 'manager' => 'manager2@everland.vn', 'position' => 'Kỹ sư Xây dựng', 'join' => '2020-12-07'],
            ['code' => 'EVG-0022', 'name' => 'Đoàn Minh Tuấn', 'email' => 'tuan.doan@everland.vn', 'role' => 'employee', 'dept' => 'PXD', 'manager' => 'manager2@everland.vn', 'position' => 'Giám sát công trình', 'join' => '2021-09-27'],
        ];

        $roles = Role::all()->keyBy('slug');
        $departments = Department::all()->keyBy('code');

        foreach ($people as $index => $person) {
            $manager = $person['manager'] ? User::where('email', $person['manager'])->first() : null;

            User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'employee_code' => $person['code'],
                    'name'          => $person['name'],
                    'password'      => 'Everland@123',
                    'role_id'       => $roles[$person['role']]->id,
                    'department_id' => $departments[$person['dept']]->id,
                    'manager_id'    => $manager?->id,
                    'position'      => $person['position'],
                    'phone'         => '09' . str_pad((string) (10000000 + $index * 137), 8, '0', STR_PAD_LEFT),
                    'join_date'     => $person['join'],
                    'status'        => 'active',
                ]
            );
        }

        $departmentManagers = [
            'EVG'  => 'director@everland.vn',
            'KVP'  => 'hr@everland.vn',
            'HCNS' => 'hr@everland.vn',
            'KT'   => 'ketoan@everland.vn',
            'KKD'  => 'manager@everland.vn',
            'PKD'  => 'manager@everland.vn',
            'NK1'  => 'leader@everland.vn',
            'NK2'  => 'leader2@everland.vn',
            'PMK'  => 'manager@everland.vn',
            'KDA'  => 'manager2@everland.vn',
            'PTK'  => 'manager2@everland.vn',
            'NTK'  => 'leader3@everland.vn',
            'PXD'  => 'manager2@everland.vn',
        ];

        foreach ($departmentManagers as $code => $email) {
            $manager = User::where('email', $email)->first();
            if ($manager && isset($departments[$code])) {
                $departments[$code]->update(['manager_id' => $manager->id]);
            }
        }
    }
}
