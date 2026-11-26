<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HandoverAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeaveRequest $leave,
        public User $handover,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bạn được bàn giao công việc từ đơn '.$this->leave->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.handover-assigned',
            with: [
                'leave' => $this->leave,
                'handover' => $this->handover,
            ],
        );
    }
}
