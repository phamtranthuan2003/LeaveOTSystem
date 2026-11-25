<?php

return [
    'groups' => [
        'Tổng quan' => [
            'dashboard.view'        => 'Xem bảng điều khiển',
        ],
        'Nghỉ phép' => [
            'leave.create'          => 'Tạo đơn nghỉ phép',
            'leave.view.own'        => 'Xem đơn nghỉ của mình',
            'leave.cancel.own'      => 'Huỷ đơn nghỉ của mình',
            'leave.approve'         => 'Duyệt đơn nghỉ phép',
            'leave.view.all'        => 'Xem toàn bộ đơn nghỉ phép',
        ],
        'Làm thêm giờ (OT)' => [
            'ot.create'             => 'Đăng ký làm thêm giờ',
            'ot.view.own'           => 'Xem đơn OT của mình',
            'ot.cancel.own'         => 'Huỷ đơn OT của mình',
            'ot.approve'            => 'Duyệt đăng ký OT',
            'ot.view.all'           => 'Xem toàn bộ đơn OT',
        ],
        'Đội nhóm & lịch' => [
            'team.view'             => 'Xem đội nhóm & quỹ phép thành viên',
            'calendar.view'         => 'Xem lịch nghỉ / OT',
        ],
        'Quỹ phép' => [
            'balance.view.own'      => 'Xem quỹ phép của mình',
            'balance.view.all'      => 'Xem quỹ phép toàn công ty',
            'balance.generate'      => 'Khởi tạo quỹ phép năm',
            'balance.update'        => 'Sửa trực tiếp ngày quỹ phép',
            'balance.adjust'        => 'Cộng / trừ ngày quỹ phép',
        ],
        'Tổ chức & nhân sự' => [
            'user.view'             => 'Xem danh sách nhân sự',
            'user.create'           => 'Thêm tài khoản nhân sự',
            'user.update'           => 'Sửa thông tin nhân sự',
            'user.toggle'           => 'Khoá / mở khoá tài khoản',
            'user.reset_password'   => 'Đặt lại mật khẩu nhân sự',
            'user.delete'           => 'Xoá tài khoản nhân sự',
            'department.view'       => 'Xem sơ đồ phòng ban',
            'department.create'     => 'Thêm phòng ban',
            'department.update'     => 'Sửa phòng ban',
            'department.toggle'     => 'Ngưng / kích hoạt phòng ban',
            'department.delete'     => 'Xoá phòng ban',
        ],
        'Danh mục' => [
            'leavetype.view'        => 'Xem loại nghỉ phép',
            'leavetype.create'      => 'Thêm loại nghỉ phép',
            'leavetype.update'      => 'Sửa loại nghỉ phép',
            'leavetype.toggle'      => 'Bật / ngưng loại nghỉ phép',
            'leavetype.delete'      => 'Xoá loại nghỉ phép',
            'holiday.view'          => 'Xem ngày lễ',
            'holiday.create'        => 'Thêm ngày lễ',
            'holiday.update'        => 'Sửa ngày lễ',
            'holiday.delete'        => 'Xoá ngày lễ',
        ],
        'Báo cáo' => [
            'report.view'           => 'Xem báo cáo tổng hợp',
            'report.export'         => 'Xuất báo cáo Excel',
        ],
        'Hệ thống' => [
            'role.view'             => 'Xem vai trò & ma trận quyền',
            'role.create'           => 'Thêm vai trò',
            'role.update'           => 'Sửa quyền của vai trò',
            'role.delete'           => 'Xoá vai trò',
            'setting.view'          => 'Xem cấu hình hệ thống',
            'setting.update'        => 'Sửa cấu hình hệ thống',
            'audit.view'            => 'Xem nhật ký hoạt động',
        ],
    ],

    'roles' => [
        'admin' => [
            'name'        => 'Quản trị hệ thống',
            'description' => 'Toàn quyền cấu hình hệ thống, tài khoản và phân quyền.',
        ],
        'director' => [
            'name'        => 'Ban Giám đốc',
            'description' => 'Tổng Giám đốc / Giám đốc khối, duyệt cấp cao và xem báo cáo toàn công ty.',
        ],
        'hr' => [
            'name'        => 'Nhân sự (HR)',
            'description' => 'Quản lý nhân sự, quỹ phép, danh mục và bước duyệt cuối của đơn nghỉ.',
        ],
        'manager' => [
            'name'        => 'Trưởng phòng / Giám đốc khối',
            'description' => 'Duyệt cấp phòng ban, quản lý đội ngũ trực thuộc.',
        ],
        'leader' => [
            'name'        => 'Trưởng nhóm',
            'description' => 'Duyệt cấp nhóm, quản lý thành viên trực tiếp.',
        ],
        'employee' => [
            'name'        => 'Nhân viên',
            'description' => 'Tạo đơn nghỉ phép, đăng ký OT và theo dõi quỹ phép cá nhân.',
        ],
    ],

    // Quyền mặc định (admin luôn có toàn quyền, không phụ thuộc ma trận)
    'defaults' => [
        'employee' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own',
            'ot.create', 'ot.view.own', 'ot.cancel.own', 'calendar.view',
            'balance.view.own', 'team.view',
        ],
        'leader' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'ot.create', 'ot.view.own', 'ot.cancel.own', 'ot.approve', 'calendar.view',
            'balance.view.own', 'team.view',
        ],
        'manager' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'ot.create', 'ot.view.own', 'ot.cancel.own', 'ot.approve', 'calendar.view',
            'balance.view.own', 'team.view', 'report.view',
        ],
        'director' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'leave.view.all', 'ot.create', 'ot.view.own', 'ot.cancel.own', 'ot.approve', 'ot.view.all',
            'calendar.view', 'balance.view.own', 'team.view', 'report.view', 'report.export',
            'user.view', 'audit.view',
        ],
        'hr' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'leave.view.all', 'ot.create', 'ot.view.own', 'ot.cancel.own', 'ot.approve', 'ot.view.all',
            'calendar.view', 'balance.view.own', 'balance.view.all', 'balance.generate',
            'balance.update', 'balance.adjust', 'team.view',
            'user.view', 'user.create', 'user.update', 'user.toggle', 'user.reset_password', 'user.delete',
            'department.view', 'department.create', 'department.update', 'department.toggle', 'department.delete',
            'leavetype.view', 'leavetype.create', 'leavetype.update', 'leavetype.toggle', 'leavetype.delete',
            'holiday.view', 'holiday.create', 'holiday.update', 'holiday.delete',
            'report.view', 'report.export', 'audit.view',
        ],
    ],
];
