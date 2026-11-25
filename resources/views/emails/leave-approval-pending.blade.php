<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cần duyệt đơn nghỉ phép</title>
</head>
<body style="margin: 0; padding: 0; background: #f1f4f9; font-family: Arial, Helvetica, sans-serif; color: #1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f1f4f9; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td style="background: #1d4ed8; padding: 20px 24px; color: #ffffff;">
                            <div style="font-size: 18px; font-weight: bold;">Everland Group · Nghỉ phép &amp; OT</div>
                            <div style="font-size: 13px; margin-top: 4px; opacity: .85;">Thông báo đơn cần duyệt</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px;">
                            <p style="margin: 0 0 12px;">Chào <b>{{ $approver->name }}</b>,</p>
                            <p style="margin: 0 0 16px; line-height: 1.6;">
                                <b>{{ $leave->user?->name }}</b> vừa gửi đơn nghỉ phép <b>{{ $leave->code }}</b>
                                và đang chờ bạn phê duyệt.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e9f2; border-radius: 8px; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 10px 14px; background: #f7f9fd; width: 140px; color: #6b7280; font-size: 13px;">Nhân viên</td>
                                    <td style="padding: 10px 14px; font-size: 14px;">
                                        {{ $leave->user?->name }}
                                        @if ($leave->user?->employee_code)
                                            ({{ $leave->user->employee_code }})
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Phòng ban</td>
                                    <td style="padding: 10px 14px; font-size: 14px;">{{ $leave->user?->department?->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Loại nghỉ</td>
                                    <td style="padding: 10px 14px; font-size: 14px;">{{ $leave->leaveType?->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Thời gian</td>
                                    <td style="padding: 10px 14px; font-size: 14px;">
                                        {{ vn_date($leave->start_date) }}
                                        @if ($leave->start_date?->ne($leave->end_date))
                                            – {{ vn_date($leave->end_date) }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Số ngày</td>
                                    <td style="padding: 10px 14px; font-size: 14px;">{{ rtrim(rtrim(number_format((float) $leave->total_days, 1, ',', '.'), '0'), ',') }} ngày ({{ working_day_label($leave->day_type) }})</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px; vertical-align: top;">Lý do</td>
                                    <td style="padding: 10px 14px; font-size: 14px; line-height: 1.5;">{{ $leave->reason }}</td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 20px;">
                                <a href="{{ $approveUrl }}" style="display: inline-block; background: #1d4ed8; color: #ffffff; text-decoration: none; padding: 11px 20px; border-radius: 8px; font-weight: bold; font-size: 14px;">
                                    Xem và duyệt đơn
                                </a>
                            </p>

                            <p style="margin: 0; color: #6b7280; font-size: 12px; line-height: 1.6;">
                                Email này được gửi tự động từ Hệ thống quản lý nghỉ phép &amp; làm thêm giờ. Vui lòng không trả lời email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
