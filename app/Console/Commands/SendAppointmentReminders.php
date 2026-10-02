<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\SmsLog;
use App\Services\SmsNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clinic:send-appointment-reminders')]
#[Description('Send one SMS reminder for approved appointments approximately 24 hours away')]
class SendAppointmentReminders extends Command
{
    public function handle(SmsNotifier $smsNotifier): int
    {
        $windowStart = now()->addHours(24);
        $appointments = Appointment::query()
            ->with(['patient.user'])
            ->where('status', 'approved')
            ->whereBetween('starts_at', [$windowStart, $windowStart->copy()->addMinutes(15)])
            ->get();
        $sent = 0;

        foreach ($appointments as $appointment) {
            if (SmsLog::query()
                ->where('appointment_id', $appointment->id)
                ->where('type', 'appointment_reminder')
                ->exists()) {
                continue;
            }

            $smsNotifier->send(
                $appointment->patient,
                'appointment_reminder',
                'Reminder: Your dental appointment is scheduled for '.$appointment->starts_at->format('F j, Y \a\t g:i A').'.',
                $appointment,
            );
            $sent++;
        }

        $this->components->info("Processed {$sent} appointment reminder(s).");

        return self::SUCCESS;
    }
}
