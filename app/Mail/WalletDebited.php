<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WalletDebited extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public $user,
        public $amount,
        public $balance,
        public ?string $reason = null // ✅ OPTIONAL
    ) {}
    public function build()
    {
        return $this->subject('Wallet Debited')
            ->view('emails.wallet.debited');
    }

}
