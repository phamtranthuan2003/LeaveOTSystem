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

class ApprovalPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $requestable,
        public User $approver,
        public ?User $approvedBy = null,
        public bool $approvedAllLevels = false,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->requestable instanceof LeaveRequest ? 'đơn nghỉ phép' : 'đăng ký OT';

        return new Envelope(
            subject: 'Cần duyệt '.$label.' '.$this->requestable->code,
        );
    }

    public function content(): Content
    {
        $isLeave = $this->requestable instanceof LeaveRequest;

        return new Content(
            view: 'emails.approval-pending',
            with: [
                'requestable' => $this->requestable,
                'approver' => $this->approver,
                'approvedBy' => $this->approvedBy,
                'approvedAllLevels' => $this->approvedAllLevels,
                'isLeave' => $isLeave,
                'typeLabel' => $isLeave ? 'đơn nghỉ phép' : 'đơn đăng ký OT',
                'actionUrl' => $isLeave
                    ? route('leave.show', $this->requestable)
                    : route('overtime.show', $this->requestable),
            ],
        );
    }
}
