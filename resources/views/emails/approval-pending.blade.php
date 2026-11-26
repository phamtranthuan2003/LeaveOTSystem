<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cần duyệt {{ $typeLabel }}</title>
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
                                @if ($approvedAllLevels)
                                    <b>{{ $approvedBy?->name }}</b> đã duyệt toàn bộ các cấp của đơn <b>{{ $requestable->code }}</b>
                                    và chuyển đến bạn để duyệt chốt.
                                @elseif ($approvedBy)
                                    <b>{{ $approvedBy->name }}</b> đã duyệt đơn <b>{{ $requestable->code }}</b>
                                    và chuyển đến bạn duyệt tiếp.
                                @else
                                    <b>{{ $requestable->user?->name }}</b> vừa gửi đơn <b>{{ $requestable->code }}</b>
                                    và đang chờ bạn phê duyệt.
                                @endif
                            </p>

                            @include('emails.partials.request-details', ['requestable' => $requestable, 'isLeave' => $isLeave])

                            <p style="margin: 0 0 6px; color: #6b7280; font-size: 13px;">
                                Bước duyệt hiện tại: {{ $requestable->current_step }}/{{ $requestable->total_steps }}
                            </p>

                            <p style="margin: 0 0 20px;">
                                <a href="{{ $actionUrl }}" style="display: inline-block; background: #1d4ed8; color: #ffffff; text-decoration: none; padding: 11px 20px; border-radius: 8px; font-weight: bold; font-size: 14px;">
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
