<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Services\AuditTrail;
use App\Services\SmsNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,approved,rejected,checked_in,waiting,in_consultation,billed,completed,cancelled,no_show'],
        ]);
        $appointments = Appointment::query()
            ->with(['patient.user', 'service', 'queueEntry', 'dentalRecord', 'bill'])
            ->when($request->user()->role === 'patient', fn ($query) => $query->whereBelongsTo($request->user()->patient()->firstOrFail()))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();

        $services = Service::query()->where('is_active', true)->orderBy('name')->get();
        $patients = $request->user()->role === 'patient' ? collect() : Patient::with('user')->orderBy('patient_number')->get();
        $appointmentTimes = $this->appointmentTimeOptions();

        return view('appointments.index', compact('appointments', 'services', 'patients', 'appointmentTimes'));
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $isPatient = $request->user()->role === 'patient';
        $this->mergeAppointmentStart($request);

        $data = $request->validate([
            'patient_id' => [$isPatient ? 'nullable' : 'required', 'integer', 'exists:patients,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => [$isPatient ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i', ...($isPatient ? ['after:now'] : [])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = Service::query()->whereKey($data['service_id'])->where('is_active', true)->first();

        if ($service === null) {
            throw ValidationException::withMessages(['service_id' => 'Choose an available service.']);
        }

        $patient = $isPatient
            ? $request->user()->patient()->firstOrFail()
            : Patient::query()->findOrFail($data['patient_id']);
        $startsAt = $data['starts_at'] ?? now()->ceilMinutes(10)->second(0);

        if (isset($data['starts_at'])) {
            $this->ensureAppointmentAvailability($startsAt);
        }

        if ($isPatient && Appointment::query()
            ->where('starts_at', $startsAt)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->exists()) {
            throw ValidationException::withMessages(['starts_at' => 'That time is no longer available. Choose another time.']);
        }

        $appointment = DB::transaction(function () use ($isPatient, $patient, $service, $startsAt, $data, $request): Appointment {
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'created_by' => $request->user()->id,
                'type' => $isPatient ? 'online' : 'walk_in',
                'starts_at' => $startsAt,
                'status' => $isPatient ? 'pending' : 'checked_in',
                'notes' => $data['notes'] ?? null,
            ]);

            if (! $isPatient) {
                $this->addToQueue($appointment);
            }

            return $appointment;
        });

        $auditTrail->record($request->user(), $isPatient ? 'appointment_booked' : 'walk_in_registered', 'appointments', $appointment);

        return redirect()->route('appointments.index')->with('status', $isPatient
            ? 'Appointment request submitted for clinic approval.'
            : 'Walk-in appointment added to the queue.');
    }

    public function transition(
        Request $request,
        Appointment $appointment,
        string $action,
        AuditTrail $auditTrail,
        SmsNotifier $smsNotifier,
    ): RedirectResponse {
        $user = $request->user();

        if ($user->role === 'patient' && ! $appointment->patient()->where('user_id', $user->id)->exists()) {
            abort(404);
        }

        $transitions = [
            'approve' => ['pending', 'approved'],
            'reject' => ['pending', 'rejected'],
            'cancel' => [['pending', 'approved', 'checked_in', 'waiting'], 'cancelled'],
            'check-in' => ['approved', 'checked_in'],
            'call' => [['checked_in', 'waiting'], 'waiting'],
            'consult' => ['waiting', 'in_consultation'],
            'complete' => ['billed', 'completed'],
            'no-show' => [['approved', 'checked_in', 'waiting'], 'no_show'],
        ];

        abort_unless(isset($transitions[$action]), 404);
        [$from, $to] = $transitions[$action];

        if ($action === 'cancel' && $user->role !== 'patient' && ! $user->hasRole('admin', 'staff')) {
            abort(403);
        }

        if ($action !== 'cancel' && $user->role === 'patient') {
            abort(403);
        }

        if ($action === 'complete' && ! $user->hasRole('admin', 'staff')) {
            abort(403);
        }

        $allowedFrom = is_array($from) ? $from : [$from];

        if (! in_array($appointment->status, $allowedFrom, true)) {
            throw ValidationException::withMessages(['status' => 'This appointment cannot be changed from its current status.']);
        }

        if ($action === 'complete') {
            $bill = $appointment->bill()->with(['items', 'payments'])->first();
            $paidAmount = (int) round((float) $bill?->payments->sum('applied_amount') * 100);
            $billTotal = (int) round((float) $bill?->total * 100);

            if (
                $bill === null
                || $bill->items->isEmpty()
                || $bill->payment_status !== 'paid'
                || $paidAmount < $billTotal
            ) {
                throw ValidationException::withMessages([
                    'status' => 'This appointment cannot be marked as Completed until the bill has been finalized and fully paid.',
                ]);
            }
        }

        DB::transaction(function () use ($appointment, $action, $to): void {
            $appointment->update(['status' => $to]);

            if ($action === 'check-in') {
                $this->addToQueue($appointment);
            }

            if ($appointment->queueEntry !== null) {
                $queueStatus = match ($action) {
                    'call' => 'called',
                    'consult' => 'in_consultation',
                    'complete' => 'completed',
                    'cancel', 'no-show' => 'cancelled',
                    default => $appointment->queueEntry->status,
                };

                $appointment->queueEntry->update(['status' => $queueStatus]);
            }
        });

        $auditTrail->record($user, 'appointment_'.$action, 'appointments', $appointment);

        if (in_array($action, ['approve', 'reject', 'cancel'], true)) {
            $message = match ($action) {
                'approve' => 'Your dental appointment is confirmed for '.$appointment->starts_at->format('F j, Y \a\t g:i A').'.',
                'reject' => 'Your dental appointment request was not approved. Please contact the clinic to reschedule.',
                default => 'Your dental appointment on '.$appointment->starts_at->format('F j, Y \a\t g:i A').' has been cancelled.',
            };
            $smsNotifier->send($appointment->patient, 'appointment_'.$action, $message, $appointment);
        }

        return back()->with('status', 'Appointment updated to '.str_replace('_', ' ', $appointment->status).'.');
    }

    public function reschedule(Request $request, Appointment $appointment, AuditTrail $auditTrail, SmsNotifier $smsNotifier): RedirectResponse
    {
        abort_unless(in_array($appointment->status, ['pending', 'approved'], true), 422);

        $this->mergeAppointmentStart($request);
        $data = $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'date', 'after:now'],
        ]);
        $startsAt = Carbon::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], config('app.timezone'));

        $this->ensureAppointmentAvailability($startsAt);

        $slotTaken = Appointment::query()
            ->where('starts_at', $startsAt->format('Y-m-d H:i:s'))
            ->whereKeyNot($appointment->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'no_show'])
            ->exists();

        if ($slotTaken) {
            throw ValidationException::withMessages(['starts_at' => 'That time is no longer available. Choose another time.']);
        }

        $previousStart = $appointment->starts_at;
        $appointment->update(['starts_at' => $startsAt]);
        $auditTrail->record(
            $request->user(),
            'appointment_rescheduled',
            'appointments',
            $appointment,
            'Changed from '.$previousStart->format('Y-m-d H:i').' to '.$appointment->starts_at->format('Y-m-d H:i').'.',
        );

        if ($appointment->status === 'approved') {
            $smsNotifier->send(
                $appointment->patient,
                'appointment_rescheduled',
                'Your dental appointment has been rescheduled to '.$appointment->starts_at->format('F j, Y \a\t g:i A').'.',
                $appointment,
            );
        }

        return back()->with('status', 'Appointment rescheduled to '.$appointment->starts_at->format('F j, Y \a\t g:i A').'.');
    }

    private function addToQueue(Appointment $appointment): QueueEntry
    {
        if ($appointment->queueEntry !== null) {
            return $appointment->queueEntry;
        }

        $queueEntry = QueueEntry::create([
            'appointment_id' => $appointment->id,
            'queue_date' => today(),
            'queue_number' => 'Q-PENDING-'.Str::uuid(),
            'status' => 'waiting',
        ]);
        $queueEntry->update(['queue_number' => sprintf('Q-%03d', $queueEntry->id)]);

        return $queueEntry;
    }

    private function mergeAppointmentStart(Request $request): void
    {
        if (! $request->filled('appointment_date') && ! $request->filled('appointment_time')) {
            return;
        }

        $dateData = $request->validate([
            'appointment_date' => ['required', 'date_format:Y-m-d'],
        ]);

        if (Carbon::createFromFormat('!Y-m-d', $dateData['appointment_date'])->dayOfWeekIso === 7) {
            throw ValidationException::withMessages([
                'appointment_date' => 'The Dental Clinic is closed on Sundays. Please select another date.',
            ]);
        }

        $timeData = $request->validate([
            'appointment_time' => ['required', 'date_format:H:i'],
        ]);

        if (! in_array($timeData['appointment_time'], $this->appointmentTimeOptions(), true)) {
            throw ValidationException::withMessages([
                'appointment_time' => 'Appointments are available Monday to Saturday from 10:00 AM to 5:00 PM.',
            ]);
        }

        $request->merge([
            'starts_at' => $dateData['appointment_date'].'T'.$timeData['appointment_time'],
        ]);
    }

    private function appointmentTimeOptions(): array
    {
        return array_map(
            static fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
            range(10 * 60, 17 * 60, 10),
        );
    }

    private function ensureAppointmentAvailability(mixed $startsAt): void
    {
        $start = Carbon::parse($startsAt);

        if ($start->dayOfWeekIso === 7) {
            throw ValidationException::withMessages([
                'starts_at' => 'The Dental Clinic is closed on Sundays. Please select another date.',
            ]);
        }

        $minutes = ($start->hour * 60) + $start->minute;

        if ($minutes < 10 * 60 || $minutes > 17 * 60 || $start->minute % 10 !== 0) {
            throw ValidationException::withMessages([
                'starts_at' => 'Appointments are available Monday to Saturday from 10:00 AM to 5:00 PM, in 10-minute increments.',
            ]);
        }
    }
}
