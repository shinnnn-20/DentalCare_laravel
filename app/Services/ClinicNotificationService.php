<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ClinicActivityNotification;
use Illuminate\Support\Facades\Notification;

class ClinicNotificationService
{
    public function notifyClinicStaff(
        User $actor,
        string $type,
        string $title,
        string $message,
        string $url,
        string $relatedType,
        int $relatedId,
    ): void {
        $recipients = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->where('is_active', true)
            ->whereKeyNot($actor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ClinicActivityNotification(
            $type,
            $title,
            $message,
            $url,
            $relatedType,
            $relatedId,
        ));
    }
}
