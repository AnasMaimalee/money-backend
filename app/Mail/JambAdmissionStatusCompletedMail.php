<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\JambAdmissionLetterRequest;

class JambAdmissionStatusCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public JambAdmissionLetterRequest $job
    ) {}

    public function build()
    {
        return $this
            ->subject('Your JAMB Admission Status is Ready')
            ->view('emails.services.jamb-admission-Status-completed');
    }
}
