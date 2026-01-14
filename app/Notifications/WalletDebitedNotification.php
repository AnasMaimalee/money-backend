<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WalletDebitedNotification extends Notification
{
    use Queueable;

    public float $amount;
    public string $purpose;
    public string $referenceId;

    public function __construct(float $amount, string $purpose, string $referenceId)
    {
        $this->amount = $amount;
        $this->purpose = $purpose;
        $this->referenceId = $referenceId;
    }

    public function via($notifiable)
    {
        return ['database', 'mail']; // store in DB & optionally email
    }

    public function toDatabase($notifiable)
    {
        return [
            'amount' => $this->amount,
            'purpose' => $this->purpose,
            'reference_id' => $this->referenceId,
            'message' => "Your wallet has been debited ₦{$this->amount} for {$this->purpose}.",
        ];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("Wallet Debited")
            ->line("Your wallet has been debited ₦{$this->amount} for {$this->purpose}.")
            ->action('View Exam', url("/user/cbt/exams/{$this->referenceId}"))
            ->line('Thank you for using our service!');
    }
}
