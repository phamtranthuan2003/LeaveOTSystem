<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bàn giao công việc từ đơn {{ $leave->code }}</title>
</head>
<body style="margin: 0; padding: 0; background: #f1f4f9; font-family: Arial, Helvetica, sans-serif; color: #1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f1f4f9; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td style="background: #1d4ed8; padding: 20px 24px; color: #ffffff;">
                            <div style="font-size: 18px; font-weight: bold;">Everland Group · Nghỉ phép &amp; OT</div>
                            <div style="font-size: 13px; margin-top: 4px; opacity: .85;">Thông báo bàn giao công việc</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px;">
                            <p style="margin: 0 0 12px;">Chào <b>{{ $handover->name }}</b>,</p>
                            <p style="margin: 0 0 16px; line-height: 1.6;">
                                <b>{{ $leave->user?->name }}</b> đã gửi đơn nghỉ phép <b>{{ $leave->code }}</b>
                                và chọn bạn là người nhận bàn giao công việc trong thời gian nghỉ.
                            </p>

                            @include('emails.partials.request-details', ['requestable' => $leave, 'isLeave' => true])

                            <p style="margin: 0 0 6px; color: #6b7280; font-size: 13px;">
                                Trạng thái đơn: chờ duyệt (bước {{ $leave->current_step }}/{{ $leave->total_steps }})
                            </p>

                            <p style="margin: 0 0 20px; color: #6b7280; font-size: 13px;">
                                Bạn chỉ nhận thông báo bàn giao này. Vui lòng chủ động trao đổi với
                                <b>{{ $leave->user?->name }}</b> để nắm kế hoạch công việc được bàn giao.
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