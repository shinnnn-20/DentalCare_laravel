<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\DentalRecord;
use App\Models\Patient;
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
        $url = $this->relatedRecordUrl(
            $userNotification->data['related_type'] ?? null,
            $userNotification->data['related_id'] ?? null,
        );

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

    private function relatedRecordUrl(mixed $relatedType, mixed $relatedId): string
    {
        $id = is_int($relatedId) || (is_string($relatedId) && ctype_digit($relatedId))
            ? filter_var($relatedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        return match ($relatedType) {
            'appointment' => $id !== false && Appointment::query()->whereKey($id)->exists()
                ? route('clinic.appointment-records', ['appointment_id' => $id], false).'#appointment-'.$id
                : route('clinic.appointment-records', [], false),
            'bill' => $id !== false && Bill::query()->whereKey($id)->exists()
                ? route('clinic.billing.index', ['bill' => $id], false).'#bill-'.$id
                : route('clinic.billing.index', [], false),
            'patient' => $id !== false && Patient::query()->whereKey($id)->exists()
                ? route('clinic.patients.show', $id, false)
                : route('clinic.patients.index', [], false),
            'dental_record' => $this->dentalRecordUrl($id),
            default => route('clinic.appointment-records', [], false),
        };
    }

    private function dentalRecordUrl(int|false $id): string
    {
        if ($id === false) {
            return route('clinic.appointment-records', [], false);
        }

        $record = DentalRecord::query()->find($id);
        if ($record === null) {
            return route('clinic.appointment-records', [], false);
        }

        return route('clinic.patients.show', [
            'patient' => $record->patient_id,
            'record' => $record->id,
        ], false).'#dental-record-'.$record->id;
    }
}
