<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\JambAdmissionLetterRequest;

class JambAdmissionLetterRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public JambAdmissionLetterRequest $job
    ) {}

    public function build()
    {
        return $this
            ->subject('JAMB Admission Letter Request Rejected')
            ->view('emails.services.jamb-admission-letter-rejected');
    }
}
