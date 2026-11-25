<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveApprovalPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeaveRequest $leave,
        public User $approver
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cần duyệt đơn nghỉ phép ' . $this->leave->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave-approval-pending',
            with: [
                'leave'      => $this->leave,
                'approver'   => $this->approver,
                'approveUrl' => route('leave.show', $this->leave),
            ],
        );
    }
}
