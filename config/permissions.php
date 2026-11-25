<?php

return [
    'groups' => [
        'Tổng quan' => [
            'dashboard.view' => 'Xem bảng điều khiển',
        ],
        'Nghỉ phép' => [
            'leave.create'       => 'Tạo đơn nghỉ phép',
            'leave.view.own'     => 'Xem đơn nghỉ của mình',
            'leave.cancel.own'   => 'Huỷ đơn nghỉ của mình',
            'leave.approve'      => 'Duyệt đơn nghỉ phép',
            'leave.view.all'     => 'Xem toàn bộ đơn nghỉ phép',
        ],
        'Làm thêm giờ (OT)' => [
            'ot.create'          => 'Đăng ký làm thêm giờ',
            'ot.approve'         => 'Duyệt đăng ký OT',
            'ot.view.all'        => 'Xem toàn bộ đơn OT',
        ],
        'Đội nhóm & lịch' => [
            'team.view'          => 'Xem đội nhóm & quỹ phép thành viên',
            'calendar.view'      => 'Xem lịch nghỉ / OT',
        ],
        'Quỹ phép' => [
            'balance.view.own'   => 'Xem quỹ phép của mình',
            'balance.manage'     => 'Quản lý quỹ phép toàn công ty',
        ],
        'Tổ chức & nhân sự' => [
            'user.view'          => 'Xem danh sách nhân viên',
            'user.manage'        => 'Thêm / sửa / khoá tài khoản nhân viên',
            'department.manage'  => 'Quản lý sơ đồ phòng ban',
        ],
        'Danh mục' => [
            'leavetype.manage'   => 'Quản lý loại nghỉ phép',
            'holiday.manage'     => 'Quản lý ngày lễ',
        ],
        'Báo cáo' => [
            'report.view'        => 'Xem báo cáo tổng hợp',
            'report.export'      => 'Xuất báo cáo CSV',
        ],
        'Hệ thống' => [
            'role.manage'        => 'Quản lý vai trò & ma trận phân quyền',
            'setting.manage'     => 'Cấu hình hệ thống',
            'audit.view'         => 'Xem nhật ký hoạt động',
        ],
    ],

    'roles' => [
        'admin' => [
            'name'        => 'Quản trị hệ thống',
            'description' => 'Toàn quyền cấu hình hệ thống, tài khoản và phân quyền.',
            'level'       => 100,
        ],
        'director' => [
            'name'        => 'Ban Giám đốc',
            'description' => 'Tổng Giám đốc / Giám đốc khối, duyệt cấp cao và xem báo cáo toàn công ty.',
            'level'       => 80,
        ],
        'hr' => [
            'name'        => 'Nhân sự (HR)',
            'description' => 'Quản lý nhân sự, quỹ phép, danh mục và bước duyệt cuối của đơn nghỉ.',
            'level'       => 60,
        ],
        'manager' => [
            'name'        => 'Trưởng phòng / Giám đốc khối',
            'description' => 'Duyệt cấp phòng ban, quản lý đội ngũ trực thuộc.',
            'level'       => 50,
        ],
        'leader' => [
            'name'        => 'Trưởng nhóm',
            'description' => 'Duyệt cấp nhóm, quản lý thành viên trực tiếp.',
            'level'       => 40,
        ],
        'employee' => [
            'name'        => 'Nhân viên',
            'description' => 'Tạo đơn nghỉ phép, đăng ký OT và theo dõi quỹ phép cá nhân.',
            'level'       => 10,
        ],
    ],

    // Quyền mặc định (admin luôn có toàn quyền, không phụ thuộc ma trận)
    'defaults' => [
        'employee' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own',
            'ot.create', 'calendar.view', 'balance.view.own', 'team.view',
        ],
        'leader' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'ot.create', 'ot.approve', 'calendar.view', 'balance.view.own', 'team.view',
        ],
        'manager' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'ot.create', 'ot.approve', 'calendar.view', 'balance.view.own', 'team.view',
            'report.view',
        ],
        'director' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'leave.view.all', 'ot.create', 'ot.approve', 'ot.view.all', 'calendar.view',
            'balance.view.own', 'team.view', 'report.view', 'report.export', 'user.view', 'audit.view',
        ],
        'hr' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'leave.view.all', 'ot.create', 'ot.approve', 'ot.view.all', 'calendar.view',
            'balance.view.own', 'balance.manage', 'team.view', 'user.view', 'user.manage',
            'department.manage', 'leavetype.manage', 'holiday.manage', 'report.view', 'report.export',
            'audit.view',
        ],
        'admin' => [
            'dashboard.view', 'leave.create', 'leave.view.own', 'leave.cancel.own', 'leave.approve',
            'leave.view.all', 'ot.create', 'ot.approve', 'ot.view.all', 'calendar.view',
            'balance.view.own', 'balance.manage', 'team.view', 'user.view', 'user.manage',
            'department.manage', 'leavetype.manage', 'holiday.manage', 'report.view', 'report.export',
            'role.manage', 'setting.manage', 'audit.view',
        ],
    ],
];
