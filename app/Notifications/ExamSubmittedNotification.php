<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExamSubmittedNotification extends Notification
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
            'title' => 'CBT Exam Submitted',
            'message' => 'Your CBT exam has been submitted successfully.',
            'exam_id' => $this->examId,
        ];
    }
}
