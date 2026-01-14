<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ExamStartedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $examId
    ) {}

    /**
     * Channels
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Database payload
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'CBT Exam Started',
            'message' => 'Your CBT exam has started. Ensure you complete it before time runs out.',
            'exam_id' => $this->examId,
        ];
    }
}
