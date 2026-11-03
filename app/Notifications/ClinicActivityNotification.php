<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClinicActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $notificationType,
        public string $title,
        public string $message,
        public string $url,
        public string $relatedType,
        public int $relatedId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, int|string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => $this->notificationType,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'related_type' => $this->relatedType,
            'related_id' => $this->relatedId,
        ];
    }
}
