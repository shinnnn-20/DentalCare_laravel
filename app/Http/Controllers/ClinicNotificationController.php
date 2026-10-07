<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class ClinicNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->data['notification_type'] ?? 'system',
                'title' => $notification->data['title'] ?? 'Clinic notification',
                'message' => $notification->data['message'] ?? '',
                'created_at' => $notification->created_at->diffForHumans(),
                'is_read' => $notification->read_at !== null,
                'open_url' => route('clinic.notifications.open', $notification->id, false),
            ]);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $userNotification = $request->user()->notifications()
            ->whereKey($notification)
            ->firstOrFail();
        $userNotification->markAsRead();
        $url = $userNotification->data['url'] ?? route('appointments.index', [], false);

        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            $url = route('appointments.index', [], false);
        }

        if ($request->expectsJson()) {
            return response()->json(['redirect_url' => $url]);
        }

        return redirect()->to($url);
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['unread_count' => 0]);
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}
