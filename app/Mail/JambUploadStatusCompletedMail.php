<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\JambResultRequest;

class JambUploadStatusCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public JambResultRequest $job;

    /**
     * Create a new message instance.
     */
    public function __construct(JambResultRequest $job)
    {
        $this->job = $job;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your JAMB Result is Ready')
            ->view('emails.services.jamb-uplaod-status-completed')
            ->with([
                'job' => $this->job, // <-- make $job available in the blade
            ]);
    }
}
