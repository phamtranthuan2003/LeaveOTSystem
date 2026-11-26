<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $requestable,
        public User $requester,
        public string $event,
        public ?User $approvedBy = null,
        public ?User $pendingWith = null,
        public ?string $reason = null,
        public bool $approvedAllLevels = false,
        public bool $byAdmin = false,
    ) {}

    public function envelope(): Envelope
    {
        $code = $this->requestable->code;

        $subject = match ($this->event) {
            'submitted' => 'Đã gửi đơn '.$code,
            'progressed' => 'Cập nhật duyệt đơn '.$code,
            'completed' => 'Đơn '.$code.' đã được duyệt hoàn tất',
            'rejected' => 'Đơn '.$code.' bị từ chối',
            default => 'Cập nhật đơn '.$code,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $isLeave = $this->requestable instanceof LeaveRequest;

        return new Content(
            view: 'emails.request-status',
            with: [
                'requestable' => $this->requestable,
                'requester' => $this->requester,
                'event' => $this->event,
                'approvedBy' => $this->approvedBy,
                'pendingWith' => $this->pendingWith,
                'reason' => $this->reason,
                'approvedAllLevels' => $this->approvedAllLevels,
                'byAdmin' => $this->byAdmin,
                'isLeave' => $isLeave,
                'typeLabel' => $isLeave ? 'đơn nghỉ phép' : 'đơn đăng ký OT',
                'actionUrl' => $isLeave
                    ? route('leave.show', $this->requestable)
                    : route('overtime.show', $this->requestable),
            ],
        );
    }
}
