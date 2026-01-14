<?php

namespace App\Services\CBT;

use App\Repositories\CBT\NotificationRepository;
use App\Models\Notification;

class NotificationService
{
    public function __construct(protected NotificationRepository $repository) {}

    public function send(string $userId, string $type, array $data = []): Notification
    {
        return $this->repository->create($userId, $type, $data);
    }

    public function getUserNotifications(string $userId)
    {
        return $this->repository->getUserNotifications($userId);
    }

    public function markAsRead(Notification $notification): void
    {
        $this->repository->markAsRead($notification);
    }
}
