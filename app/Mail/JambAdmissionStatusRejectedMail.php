<?php

namespace App\Mail;

use App\Models\JambAdmissionStatusRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JambAdmissionStatusRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public JambAdmissionStatusRequest $job
    ) {}

    public function build()
    {
        return $this
            ->subject('JAMB Admission Status Request Rejected')
            ->view('emails.services.jamb-admission-status-rejected');
    }
}
