<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ExamStartedNotification extends Notification
{
    use Queueable;

    public string $examId;

    public function __construct(string $examId)
    {
        $this->examId = $examId;
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CBT Exam Started')
            ->greeting('Hello ' . $notifiable->name)
            ->line('Your CBT exam has started successfully.')
            ->line('Exam ID: ' . $this->examId)
            ->action('Continue Exam', url('/exams/' . $this->examId))
            ->line('Best of luck!');
    }

    public function toArray($notifiable): array
    {
        return [
            'exam_id' => $this->examId,
            'message' => 'Your CBT exam has started successfully!',
        ];
    }
}
