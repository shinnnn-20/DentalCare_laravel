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
                $this->recordStatusChange($appointment, 'appointment_completion_reopened');

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
            $this->recordStatusChange($appointment, 'appointment_completed');

            return true;
        });
    }

    private function recordStatusChange(Appointment $appointment, string $action): void
    {
        AuditLog::query()->create([
            'user_id' => null,
            'role' => 'system',
            'action' => $action,
            'module' => 'appointments',
            'record_type' => class_basename($appointment),
            'record_id' => (string) $appointment->id,
            'description' => $action === 'appointment_completed'
                ? 'Appointment was automatically completed after treatment and full payment.'
                : 'Appointment returned to billed status after an add-on service created an outstanding balance.',
        ]);
    }
}
