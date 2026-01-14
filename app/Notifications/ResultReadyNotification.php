<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResultReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $examId
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'CBT Result Ready',
            'message' => 'Your CBT result is now available. Click to view.',
            'exam_id' => $this->examId,
        ];
    }
}
