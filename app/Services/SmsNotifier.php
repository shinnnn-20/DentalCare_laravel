<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\SmsLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SmsNotifier
{
    public function send(Patient $patient, string $type, string $message, ?Appointment $appointment = null): SmsLog
    {
        $log = SmsLog::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'phone' => $patient->user->phone ?? '',
            'message' => $message,
            'type' => $type,
            'status' => 'queued',
        ]);

        $endpoint = config('services.clinic_sms.endpoint');
        $token = config('services.clinic_sms.token');

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            $log->update([
                'status' => 'failed',
                'error' => 'SMS gateway is not configured.',
            ]);

            return $log;
        }

        if ($log->phone === '') {
            $log->update([
                'status' => 'failed',
                'error' => 'Patient contact number is missing.',
            ]);

            return $log;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->post($endpoint, ['to' => $log->phone, 'message' => $message]);
        } catch (ConnectionException $exception) {
            $log->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            return $log;
        }

        if (! $response->successful()) {
            $log->update([
                'status' => 'failed',
                'error' => 'SMS gateway returned HTTP '.$response->status().'.',
            ]);

            return $log;
        }

        $log->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error' => null,
        ]);

        return $log;
    }
}
