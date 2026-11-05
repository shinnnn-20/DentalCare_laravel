<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;

class AppointmentCompletionService
{
    public function sync(Bill $bill): bool
    {
        return DB::transaction(function () use ($bill): bool {
            $lockedBill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $appointment = Appointment::query()
                ->whereKey($lockedBill->appointment_id)
                ->lockForUpdate()
                ->firstOrFail();
            $totalCents = (int) round((float) $lockedBill->total * 100);
            $paidCents = (int) round((float) $lockedBill->payments()->sum('applied_amount') * 100);
            $isFullyPaid = $paidCents >= $totalCents;

            if ($appointment->status === 'completed') {
                if ($isFullyPaid) {
                    return false;
                }

                $appointment->update(['status' => 'billed']);
                $this->recordStatusChange($appointment, 'appointment_completion_reopened', $lockedBill, $paidCents, $totalCents);

                return true;
            }

            if (
                $appointment->status !== 'billed'
                || ! $appointment->dentalRecord()->exists()
                || $lockedBill->items()->doesntExist()
                || ! $isFullyPaid
            ) {
                return false;
            }

            $appointment->update(['status' => 'completed']);
            $appointment->queueEntry()->update(['status' => 'completed']);
            DB::table('appointment_slots')->where('appointment_id', $appointment->id)->delete();
            $this->recordStatusChange($appointment, 'appointment_completed', $lockedBill, $paidCents, $totalCents);

            return true;
        });
    }

    private function recordStatusChange(Appointment $appointment, string $action, Bill $bill, int $paidCents, int $totalCents): void
    {
        $appointment->loadMissing(['patient.user', 'branch']);
        $description = $action === 'appointment_completed'
            ? sprintf(
                'System completed appointment #%d for patient %s on %s at %s after treatment and full payment of PHP %s on bill %s.',
                $appointment->id,
                $appointment->patient->user->name,
                $appointment->starts_at->format('M j, Y g:i A'),
                $appointment->branch?->name ?? 'an unassigned branch',
                number_format($totalCents / 100, 2),
                $bill->bill_number,
            )
            : sprintf(
                'System changed appointment #%d for patient %s from completed back to billed because bill %s has an outstanding balance: PHP %s paid of PHP %s.',
                $appointment->id,
                $appointment->patient->user->name,
                $bill->bill_number,
                number_format($paidCents / 100, 2),
                number_format($totalCents / 100, 2),
            );

        AuditLog::query()->create([
            'user_id' => null,
            'role' => 'system',
            'action' => $action,
            'module' => 'appointments',
            'record_type' => class_basename($appointment),
            'record_id' => (string) $appointment->id,
            'description' => $description,
        ]);
    }
}
