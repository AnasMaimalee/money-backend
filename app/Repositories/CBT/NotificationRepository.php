<?php

namespace App\Repositories\CBT;

use App\Models\Notification;
use Illuminate\Support\Str;

class NotificationRepository
{
    /**
     * Create a notification for a user
     */
    public function createNotification(string $userId, string $type, array $data = []): Notification
    {
        return Notification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $type,
            'data' => $data,
            'is_read' => false,
        ]);
    }

    /**
     * Fetch latest notifications for a user
     */
    public function getUserNotifications(string $userId, int $limit = 20)
    {
        return Notification::where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(string $notificationId): void
    {
        Notification::where('id', $notificationId)->update(['is_read' => true]);
    }
}
