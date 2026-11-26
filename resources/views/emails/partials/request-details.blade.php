<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e9f2; border-radius: 8px; margin-bottom: 20px;">
    <tr>
        <td style="padding: 10px 14px; background: #f7f9fd; width: 140px; color: #6b7280; font-size: 13px;">Nhân viên</td>
        <td style="padding: 10px 14px; font-size: 14px;">
            {{ $requestable->user?->name }}
            @if ($requestable->user?->employee_code)
                ({{ $requestable->user->employee_code }})
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Phòng ban</td>
        <td style="padding: 10px 14px; font-size: 14px;">{{ $requestable->user?->department?->name ?? '—' }}</td>
    </tr>

    @if ($isLeave)
        <tr>
            <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Loại nghỉ</td>
            <td style="padding: 10px 14px; font-size: 14px;">{{ $requestable->leaveType?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Thời gian</td>
            <td style="padding: 10px 14px; font-size: 14px;">
                {{ vn_date($requestable->start_date) }}
                @if ($requestable->start_date?->ne($requestable->end_date))
                    – {{ vn_date($requestable->end_date) }}
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Số ngày</td>
            <td style="padding: 10px 14px; font-size: 14px;">
                {{ days_label($requestable->total_days) }} ({{ working_day_label($requestable->day_type) }})
            </td>
        </tr>
    @else
        <tr>
            <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Ngày OT</td>
            <td style="padding: 10px 14px; font-size: 14px;">{{ vn_date($requestable->ot_date) }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Khung giờ</td>
            <td style="padding: 10px 14px; font-size: 14px;">
                {{ substr((string) $requestable->start_time, 0, 5) }} – {{ substr((string) $requestable->end_time, 0, 5) }}
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Số giờ</td>
            <td style="padding: 10px 14px; font-size: 14px;">
                {{ hours_label($requestable->hours) }}
                · x{{ rtrim(rtrim(number_format((float) $requestable->multiplier, 1, ',', '.'), '0'), ',') }}
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Loại OT</td>
            <td style="padding: 10px 14px; font-size: 14px;">{{ ot_type_meta($requestable->ot_type)['label'] }}</td>
        </tr>
        @if ($requestable->project)
            <tr>
                <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px;">Dự án</td>
                <td style="padding: 10px 14px; font-size: 14px;">{{ $requestable->project }}</td>
            </tr>
        @endif
    @endif

    <tr>
        <td style="padding: 10px 14px; background: #f7f9fd; color: #6b7280; font-size: 13px; vertical-align: top;">Lý do</td>
        <td style="padding: 10px 14px; font-size: 14px; line-height: 1.5;">{{ $requestable->reason }}</td>
    </tr>
</table>
