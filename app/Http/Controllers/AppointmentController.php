<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentAvailabilityBlock;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use App\Services\PhilippineHolidayCalendar;
use App\Services\SmsNotifier;
use Carbon\CarbonInterface;
use Carbon\Constants\UnitValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AppointmentController extends Controller
{
    private const RELEASED_APPOINTMENT_STATUSES = ['cancelled', 'rejected', 'completed', 'no_show'];

    private const ONGOING_APPOINTMENT_STATUSES = ['checked_in', 'waiting', 'in_consultation', 'billed'];

    private const CAPACITY_COUNTED_STATUSES = ['approved', 'checked_in', 'waiting', 'in_consultation', 'billed', 'completed', 'no_show'];

    public function __construct(private PhilippineHolidayCalendar $holidayCalendar) {}

    public function index(Request $request): View
    {
        $isAppointmentRecordsPage = $request->routeIs('clinic.appointment-records');
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $hasAssignedBranch = $user->hasRole('staff', 'doctor') && $user->branch_id !== null;
        $branches = Branch::query()
            ->when($hasAssignedBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('id')
            ->get();
        $bookingBranches = $user->hasRole('doctor')
            ? Branch::query()->orderBy('id')->get()
            : $branches;
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,approved,rejected,checked_in,waiting,in_consultation,billed,completed,cancelled,no_show'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'branch_id' => ['nullable', 'string', Rule::in([
                ...($hasAssignedBranch ? [] : ['unassigned']),
                ...$branches->pluck('id')->map(fn (int $id): string => (string) $id)->all(),
            ])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        if (isset($filters['appointment_id'])) {
            abort_unless($request->user()->hasRole('admin', 'staff', 'doctor'), 404);
        }

        $appointments = Appointment::query()
            ->with(['patient.user', 'service', 'services', 'branch', 'queueEntry', 'dentalRecord', 'bill.items', 'paymentProofReviewer'])
            ->when($filters['appointment_id'] ?? null, fn ($query, $appointmentId) => $query->whereKey($appointmentId))
            ->when($request->user()->role === 'patient', fn ($query) => $query->whereBelongsTo($request->user()->patient()->firstOrFail()))
            ->when($hasAssignedBranch, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when($request->user()->role === 'doctor' && ! $isAppointmentRecordsPage, fn ($query) => $query
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('starts_at', '>=', today(config('clinic.timezone'))->toDateString()))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['branch_id'] ?? null) === 'unassigned', fn ($query) => $query->whereNull('branch_id'))
            ->when(
                isset($filters['branch_id']) && $filters['branch_id'] !== 'unassigned',
                fn ($query) => $query->where('branch_id', (int) $filters['branch_id']),
            )
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    if (ctype_digit($search)) {
                        $query->whereKey((int) $search)->orWhereHas('patient', fn ($patientQuery) => $patientQuery
                            ->where('patient_number', 'like', '%'.$search.'%'));
                    } else {
                        $query->whereHas('patient.user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', '%'.$search.'%'));
                    }

                    $query->orWhereHas('services', fn ($serviceQuery) => $serviceQuery
                        ->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery
                            ->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(
                $isAppointmentRecordsPage,
                fn ($query) => $query->orderByRaw("CASE WHEN status IN ('checked_in', 'waiting', 'in_consultation', 'billed') THEN 0 WHEN status = 'pending' THEN 1 WHEN status = 'approved' THEN 2 ELSE 3 END"),
            )
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();
        $appointmentCollection = $appointments->getCollection();
        if ($isAppointmentRecordsPage) {
            $followUpAppointments = $appointmentCollection->filter(
                fn (Appointment $appointment): bool => $appointment->dentalRecord?->follow_up_date !== null,
            );
            $followUpAppointmentIds = $followUpAppointments->modelKeys();
            $appointmentSections = [
                'Ongoing Appointments' => $appointmentCollection
                    ->whereIn('status', self::ONGOING_APPOINTMENT_STATUSES)
                    ->whereNotIn('id', $followUpAppointmentIds),
                'Pending Appointments' => $appointmentCollection
                    ->where('status', 'pending')
                    ->whereNotIn('id', $followUpAppointmentIds),
                'Approved Appointments' => $appointmentCollection
                    ->where('status', 'approved')
                    ->whereNotIn('id', $followUpAppointmentIds),
                'Follow-up appointments' => $followUpAppointments,
                'Other Appointments' => $appointmentCollection->reject(fn (Appointment $appointment): bool => in_array(
                    $appointment->status,
                    [...self::ONGOING_APPOINTMENT_STATUSES, 'pending', 'approved'],
                    true,
                ) || $followUpAppointments->contains('id', $appointment->id)),
            ];
        } else {
            $appointmentSections = ['Appointments' => $appointmentCollection];
        }
        $clinicNow = now(config('clinic.timezone'));
        $canRescheduleOnAppointmentDate = $request->user()->hasRole('admin', 'staff')
            || ($request->user()->hasRole('doctor') && $request->user()->branch_id !== null);
        $reschedulableAppointmentIds = $appointments->getCollection()
            ->filter(function (Appointment $appointment) use ($clinicNow, $canRescheduleOnAppointmentDate): bool {
                $appointmentStart = $this->clinicDateTime($appointment->starts_at);

                return $canRescheduleOnAppointmentDate
                    && $appointmentStart->toDateString() >= $clinicNow->toDateString();
            })
            ->pluck('id')
            ->all();

        $services = collect();
        $serviceBranchIds = [];
        foreach ($bookingBranches as $bookingBranch) {
            $branchServices = Service::query()
                ->forBranch((int) $bookingBranch->id)
                ->where('is_active', true)
                ->when(Schema::hasColumn('services', 'category'), fn ($query) => $query->orderBy('category'))
                ->orderBy('name')
                ->get();

            foreach ($branchServices as $service) {
                $services->put($service->id, $service);
                $serviceBranchIds[$service->id][] = (string) $bookingBranch->id;
            }
        }
        $services = $services->values();
        $patients = $request->user()->hasRole('admin', 'staff', 'doctor')
            ? Patient::query()
                ->with('user')
                ->orderBy('patient_number')
                ->get()
            : collect();
        $hasUnassignedAppointments = ! $hasAssignedBranch
            && Appointment::query()->whereNull('branch_id')->exists();
        $highlightedAppointmentId = isset($filters['appointment_id']) ? (int) $filters['appointment_id'] : null;
        $appointmentListFilters = array_intersect_key($filters, array_flip(['branch_id', 'search']));

        $capacityManagementBranchId = null;
        if ($isAppointmentRecordsPage && $user->hasRole('admin', 'staff')) {
            if (isset($filters['branch_id']) && ctype_digit((string) $filters['branch_id'])) {
                $capacityManagementBranchId = (int) $filters['branch_id'];
            } elseif (! array_key_exists('branch_id', $filters) && $hasAssignedBranch) {
                $capacityManagementBranchId = (int) $user->branch_id;
            }
        }
        $capacityManagementDate = $filters['date'] ?? today(config('clinic.timezone'))->toDateString();
        $capacityDailyLimit = null;
        $capacityUsed = 0;
        $capacityRemaining = null;
        $capacityPending = 0;
        if ($capacityManagementBranchId !== null) {
            $capacityDailyLimit = DB::table('appointment_day_capacities')
                ->where('branch_id', $capacityManagementBranchId)
                ->where('appointment_date', $capacityManagementDate)
                ->value('daily_limit');
            $capacityUsed = $this->countAppointmentsAgainstCapacity($capacityManagementBranchId, $capacityManagementDate);
            $capacityRemaining = $capacityDailyLimit === null
                ? null
                : max(0, (int) $capacityDailyLimit - $capacityUsed);
            $capacityPending = Appointment::query()
                ->where('branch_id', $capacityManagementBranchId)
                ->whereDate('starts_at', $capacityManagementDate)
                ->whereIn('type', ['online', 'appointment'])
                ->where('status', 'pending')
                ->count();
        }

        return view('appointments.index', compact(
            'appointments',
            'services',
            'patients',
            'branches',
            'bookingBranches',
            'serviceBranchIds',
            'hasUnassignedAppointments',
            'reschedulableAppointmentIds',
            'highlightedAppointmentId',
            'appointmentListFilters',
            'appointmentSections',
            'hasAssignedBranch',
            'isAppointmentRecordsPage',
            'capacityManagementBranchId',
            'capacityManagementDate',
            'capacityDailyLimit',
            'capacityUsed',
            'capacityRemaining',
            'capacityPending',
        ));
    }

    public function storeDailyCapacity(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'appointment_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.today(config('clinic.timezone'))->toDateString(),
            ],
            'daily_limit' => ['required', 'integer', 'min:0', 'max:10'],
        ]);
        $user = $request->user();
        $branchId = (int) $data['branch_id'];

        if ($user->hasRole('staff') && $user->branch_id !== null && $branchId !== (int) $user->branch_id) {
            abort(404);
        }

        $branch = Branch::query()->findOrFail($branchId);
        $date = $data['appointment_date'];
        $dailyLimit = (int) $data['daily_limit'];

        DB::transaction(function () use ($branchId, $date, $dailyLimit, $user): void {
            Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
            $appointmentsAlreadyCounted = $this->countAppointmentsAgainstCapacity($branchId, $date);

            if ($dailyLimit < $appointmentsAlreadyCounted) {
                throw ValidationException::withMessages([
                    'daily_limit' => "The daily limit cannot be lower than the {$appointmentsAlreadyCounted} appointment(s) already counted for this date.",
                ]);
            }

            $capacity = DB::table('appointment_day_capacities')
                ->where('branch_id', $branchId)
                ->where('appointment_date', $date)
                ->lockForUpdate()
                ->first();

            if ($capacity === null) {
                DB::table('appointment_day_capacities')->insert([
                    'branch_id' => $branchId,
                    'appointment_date' => $date,
                    'daily_limit' => $dailyLimit,
                    'updated_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('appointment_day_capacities')
                    ->where('id', $capacity->id)
                    ->update([
                        'daily_limit' => $dailyLimit,
                        'updated_by' => $user->id,
                        'updated_at' => now(),
                    ]);
            }
        });

        $auditTrail->record(
            $user,
            'appointment_capacity_updated',
            'appointments',
            $branch,
            "Set the daily appointment approval limit for {$branch->name} on {$date} to {$dailyLimit}.",
        );

        return redirect()->route('clinic.appointment-records', [
            'branch_id' => $branchId,
            'date' => $date,
        ])->with('status', "Daily appointment limit set to {$dailyLimit} for {$branch->name} on ".Carbon::parse($date)->format('F j, Y').'.');
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'exclude_appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ]);
        $excludeAppointmentId = null;
        if (isset($data['exclude_appointment_id'])) {
            abort_unless($request->user()->hasRole('admin', 'staff', 'doctor'), 404);
            $excludedAppointment = Appointment::query()
                ->whereKey((int) $data['exclude_appointment_id'])
                ->where('branch_id', (int) $data['branch_id'])
                ->whereIn('type', ['online', 'appointment'])
                ->firstOrFail();
            $excludeAppointmentId = (int) $excludedAppointment->id;
        }
        if (
            $request->user()->hasRole('staff')
            && $request->user()->branch_id !== null
            && (int) $data['branch_id'] !== (int) $request->user()->branch_id
        ) {
            abort(404);
        }

        $timezone = config('clinic.timezone');
        $month = Carbon::createFromFormat('!Y-m', $data['month'], $timezone);
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $today = today($timezone);
        $holidays = array_filter(
            $this->holidayCalendar->forYear((int) $month->year),
            fn (string $date): bool => str_starts_with($date, $month->format('Y-m-')),
            ARRAY_FILTER_USE_KEY,
        );
        $dailyLimits = DB::table('appointment_day_capacities')
            ->where('branch_id', (int) $data['branch_id'])
            ->whereBetween('appointment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->pluck('daily_limit', 'appointment_date');
        $appointmentsByDate = Appointment::query()
            ->where('branch_id', (int) $data['branch_id'])
            ->whereBetween('starts_at', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->whereIn('type', ['online', 'appointment'])
            ->whereIn('status', self::CAPACITY_COUNTED_STATUSES)
            ->when($excludeAppointmentId !== null, fn ($query) => $query->where('id', '!=', $excludeAppointmentId))
            ->selectRaw('DATE(starts_at) as appointment_date, COUNT(*) as appointment_count')
            ->groupByRaw('DATE(starts_at)')
            ->get()
            ->keyBy('appointment_date');
        $days = [];
        $fullDates = [];

        for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
            $dateKey = $date->toDateString();

            $days[$dateKey] = $date->gte($today)
                && $date->dayOfWeekIso !== 7
                && ! isset($holidays[$dateKey]);

            if (
                $days[$dateKey]
                && isset($dailyLimits[$dateKey])
                && (int) ($appointmentsByDate->get($dateKey)?->appointment_count ?? 0) >= (int) $dailyLimits[$dateKey]
            ) {
                $days[$dateKey] = false;
                $fullDates[$dateKey] = true;
            }
        }

        return response()->json([
            'month' => $month->format('Y-m'),
            'days' => $days,
            'holidays' => $holidays,
            'full_dates' => $fullDates,
        ]);
    }

    public function store(
        Request $request,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $user = $request->user();
        $isPatient = $user->role === 'patient';
        $isDoctor = $user->hasRole('doctor');
        abort_unless($isPatient || $user->hasRole('admin', 'staff', 'doctor'), 403);
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        if ($isPatient && ! $request->filled('payment_method')) {
            $request->merge(['payment_method' => 'walk_in']);
        }
        $isGcashRequest = $isPatient && $request->input('payment_method') === 'gcash';
        if ($isDoctor && ! $request->exists('booking_mode')) {
            $request->merge(['booking_mode' => 'walk_in']);
        }

        $serviceIds = $request->input('service_ids');
        if (! is_array($serviceIds) || empty($serviceIds)) {
            $singleServiceId = $request->input('service_id');
            $serviceIds = $singleServiceId ? [(int) $singleServiceId] : [];
        } else {
            $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));
        }

        if ($isPatient && count($serviceIds) > 2) {
            throw ValidationException::withMessages([
                'service_ids' => 'You can select up to 2 treatments per appointment. Book additional treatments in a separate appointment.',
            ]);
        }

        $appointmentBookingMode = $isDoctor ? $request->input('booking_mode', 'walk_in') : null;
        $isScheduledAppointment = $isDoctor && $appointmentBookingMode === 'appointment';
        $isScheduledRequest = $isPatient || $isScheduledAppointment;
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'patient_id' => [$isPatient ? 'nullable' : 'required', 'integer', 'exists:patients,id'],
            'booking_mode' => [$isDoctor ? 'sometimes' : 'prohibited', Rule::in(['walk_in', 'appointment'])],
            'payment_method' => [$isPatient ? 'required' : 'prohibited', Rule::in(['walk_in', 'gcash'])],
            'payment_proof' => $isGcashRequest
                ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']
                : ['prohibited'],
            'service_id' => [empty($serviceIds) ? 'required' : 'nullable', 'integer', 'exists:services,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'appointment_date' => [$isScheduledRequest ? 'required' : 'prohibited', 'date_format:Y-m-d'],
            'appointment_time' => ['prohibited'],
            'starts_at' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($user->hasRole('staff')
            && $user->branch_id !== null
            && (int) $data['branch_id'] !== (int) $user->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'Appointments must be scheduled at your assigned branch.',
            ]);
        }

        if (empty($serviceIds)) {
            throw ValidationException::withMessages(['service_id' => 'Choose at least one available treatment.']);
        }

        $branchId = (int) $data['branch_id'];
        $services = Service::query()
            ->forBranch($branchId)
            ->whereIn('id', $serviceIds)
            ->where('is_active', true)
            ->get();

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages(['service_id' => 'Choose an available service for the selected branch.']);
        }

        $patient = $isPatient
            ? $request->user()->patient()->firstOrFail()
            : Patient::query()->findOrFail($data['patient_id']);
        $durationMinutes = (int) $services->sum('duration_minutes');
        $durationMinutes = max(30, $durationMinutes);
        $startsAt = $isScheduledRequest
            ? $this->appointmentDate($data['appointment_date'])
            : $this->nextAppointmentStart(now(config('clinic.timezone')), $durationMinutes);

        $requiresBranchApproval = $isScheduledAppointment
            && (int) $user->branch_id !== $branchId;
        $proofPath = null;
        try {
            $appointment = DB::transaction(function () use ($isPatient, $isGcashRequest, $isScheduledRequest, $isScheduledAppointment, $patient, $services, $startsAt, $durationMinutes, $data, $request, $branchId, &$proofPath): Appointment {
                Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
                if ($isScheduledRequest) {
                    $this->ensureAppointmentDateAvailable($startsAt, $branchId);
                }
                $slotKeys = [];
                if (! $isScheduledRequest) {
                    $this->ensureAppointmentAvailability($startsAt, $durationMinutes, $branchId);
                    $slotKeys = $this->appointmentSlotKeys($startsAt, $durationMinutes);
                    $this->claimAppointmentSlots($slotKeys, $branchId);
                }
                $appointment = Appointment::create([
                    'patient_id' => $patient->id,
                    'branch_id' => $branchId,
                    'service_id' => $services->first()->id,
                    'created_by' => $request->user()->id,
                    'payment_method' => $isPatient ? $data['payment_method'] : null,
                    'payment_proof_status' => $isGcashRequest ? 'pending_review' : null,
                    'type' => $isPatient ? 'online' : ($isScheduledAppointment ? 'appointment' : 'walk_in'),
                    'starts_at' => $startsAt,
                    'duration_minutes' => $durationMinutes,
                    'status' => $isScheduledRequest ? 'pending' : 'checked_in',
                    'notes' => $data['notes'] ?? null,
                ]);

                $appointment->services()->sync($services->pluck('id'));

                if ($isGcashRequest) {
                    $proofPath = $request->file('payment_proof')->store('appointment-payment-proofs', 'local');
                    if (! is_string($proofPath)) {
                        throw new RuntimeException('The GCash receipt image could not be stored.');
                    }

                    $appointment->update(['payment_proof_path' => $proofPath]);
                }

                if (! $isScheduledRequest) {
                    $this->attachAppointmentSlots($slotKeys, $branchId, $appointment->id);
                }

                if (! $isPatient && ! $isScheduledAppointment) {
                    $this->addToQueue($appointment);
                }

                return $appointment;
            });
        } catch (Throwable $exception) {
            if (is_string($proofPath)) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        $scheduleLabel = $this->formatAppointmentSchedule($appointment, 'M j, Y g:i A', 'M j, Y');
        $auditTrail->record(
            $request->user(),
            $isPatient ? 'appointment_booked' : ($isScheduledAppointment ? 'appointment_scheduled' : 'walk_in_registered'),
            'appointments',
            $appointment,
            sprintf(
                '%s %s for patient %s on %s at %s; treatments: %s; current status: %s.',
                $isPatient ? 'Patient' : $request->user()->name,
                $isPatient ? 'requested an appointment' : ($isScheduledAppointment ? 'scheduled an appointment' : 'registered a walk-in'),
                $patient->user->name,
                $scheduleLabel,
                Branch::query()->whereKey($branchId)->value('name') ?? 'unknown branch',
                $services->pluck('name')->join(', '),
                str_replace('_', ' ', $appointment->status),
            ).($requiresBranchApproval ? ' Destination branch approval is required.' : ''),
        );
        $patientName = $patient->user->name;
        $appointmentSchedule = $this->formatAppointmentSchedule($appointment, 'F j, Y \a\t g:i A', 'F j, Y');
        $notifications->notifyClinicStaff(
            $request->user(),
            $isPatient || $isScheduledAppointment ? 'appointment.booked' : 'appointment.created',
            $requiresBranchApproval
                ? 'Appointment Awaiting Branch Approval'
                : ($isPatient || $isScheduledAppointment ? 'New Appointment Booked' : 'Appointment Created'),
            $isPatient
                ? "{$patientName} requested an appointment for {$appointmentSchedule}."
                : ($requiresBranchApproval
                    ? "{$request->user()->name} requested an appointment for {$patientName} on {$appointmentSchedule}; the destination branch must approve it."
                : ($isScheduledAppointment
                    ? "{$request->user()->name} requested an appointment for {$patientName} on {$appointmentSchedule}; front-desk approval is required."
                    : "{$request->user()->name} added a walk-in for {$patientName} on {$appointmentSchedule}.")),
            route('clinic.appointment-records', ['date' => $appointment->starts_at->toDateString()], false).'#appointment-'.$appointment->id,
            'appointment',
            $appointment->id,
        );

        return redirect()->route($isPatient ? 'appointments.index' : 'clinic.appointment-records')->with('status', match (true) {
            $isPatient => 'Appointment request submitted for clinic approval.',
            $requiresBranchApproval => 'Appointment request submitted to the selected branch for approval.',
            $isScheduledAppointment => 'Appointment request submitted for front-desk approval.',
            default => 'Walk-in appointment added to the queue.',
        });
    }

    public function checkInFromQueue(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('admin', 'staff', 'doctor'), 403);
        abort_if($request->user()->hasRole('doctor') && $request->user()->branch_id === null, 403);

        DB::transaction(function () use ($appointment, $request, $auditTrail): void {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (
                $request->user()->hasRole('staff', 'doctor')
                && $request->user()->branch_id !== null
                && (int) $lockedAppointment->branch_id !== (int) $request->user()->branch_id
            ) {
                abort(404);
            }

            if ($lockedAppointment->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Only approved appointments can be checked in, and each appointment can be checked in once.',
                ]);
            }

            if ($lockedAppointment->starts_at->toDateString() < today(config('clinic.timezone'))->toDateString()) {
                throw ValidationException::withMessages([
                    'status' => 'Past appointments cannot be checked in.',
                ]);
            }

            if ($lockedAppointment->queueEntry()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'This appointment already has a queue entry.',
                ]);
            }

            $lockedAppointment->loadMissing(['patient.user', 'branch']);
            $lockedAppointment->update(['status' => 'checked_in']);
            $queueEntry = $this->addToQueue($lockedAppointment);
            $auditTrail->record(
                $request->user(),
                'appointment_check_in',
                'appointments',
                $lockedAppointment,
                "Checked in {$lockedAppointment->patient->user->name} for appointment #{$lockedAppointment->id} at "
                    .$this->formatAppointmentSchedule($lockedAppointment, 'M j, Y g:i A', 'M j, Y')
                    .' in '.($lockedAppointment->branch?->name ?? 'an unassigned branch')
                    ."; queue number {$queueEntry->queue_number}; status changed from approved to checked in.",
            );
        });

        $appointment->refresh()->load('patient.user');
        $notifications->notifyClinicStaff(
            $request->user(),
            'appointment.checked_in',
            'Patient Checked In',
            "{$request->user()->name} checked in {$appointment->patient->user->name}.",
            route('clinic.queue.index', [], false),
            'appointment',
            $appointment->id,
        );

        return redirect()->route('clinic.queue.index')->with('status', 'Appointment checked in and added to the queue.');
    }

    private function addToQueue(Appointment $appointment): QueueEntry
    {
        if ($appointment->queueEntry !== null) {
            return $appointment->queueEntry;
        }

        $queueEntry = QueueEntry::create([
            'appointment_id' => $appointment->id,
            'queue_date' => today(config('clinic.timezone')),
            'queue_number' => 'Q-PENDING-'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT),
            'status' => 'waiting',
        ]);
        $queueEntry->update(['queue_number' => sprintf('Q-%03d', $queueEntry->id)]);

        return $queueEntry;
    }

    public function transition(
        Request $request,
        Appointment $appointment,
        string $action,
        AuditTrail $auditTrail,
        SmsNotifier $smsNotifier,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->hasRole('patient', 'admin', 'staff', 'doctor'), 403);
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);

        if (
            $user->hasRole('staff', 'doctor')
            && $user->branch_id !== null
            && (int) $appointment->branch_id !== (int) $user->branch_id
        ) {
            abort(404);
        }

        if ($user->role === 'patient' && ! $appointment->patient()->where('user_id', $user->id)->exists()) {
            abort(404);
        }

        $transitions = [
            'approve' => ['pending', 'approved'],
            'reject' => ['pending', 'rejected'],
            'cancel' => [['pending', 'approved', 'checked_in', 'waiting'], 'cancelled'],
            'call' => [['checked_in', 'waiting'], 'waiting'],
            'consult' => ['waiting', 'in_consultation'],
            'no-show' => [['approved', 'checked_in', 'waiting'], 'no_show'],
        ];

        abort_unless(isset($transitions[$action]), 404);
        [$from, $to] = $transitions[$action];

        if ($user->hasRole('doctor') && in_array($action, ['approve', 'reject'], true)) {
            abort(403);
        }

        if ($action === 'cancel' && $user->role !== 'patient' && ! $user->hasRole('admin', 'staff', 'doctor')) {
            abort(403);
        }

        if ($action !== 'cancel' && $user->role === 'patient') {
            abort(403);
        }

        $allowedFrom = is_array($from) ? $from : [$from];

        if (! in_array($appointment->status, $allowedFrom, true)) {
            throw ValidationException::withMessages(['status' => 'This appointment cannot be changed from its current status.']);
        }

        $previousStatus = DB::transaction(function () use ($appointment, $action, $to, $allowedFrom): string {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedAppointment->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['status' => 'This appointment cannot be changed from its current status.']);
            }

            if (
                $action === 'approve'
                && $lockedAppointment->payment_method === 'gcash'
                && $lockedAppointment->payment_proof_status !== 'verified'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Verify the GCash receipt before approving this appointment.',
                ]);
            }

            if ($action === 'approve' && $lockedAppointment->branch_id === null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Assign this appointment to a branch before approving it.',
                ]);
            }

            if ($action === 'approve' && in_array($lockedAppointment->type, ['online', 'appointment'], true)) {
                $branchId = (int) $lockedAppointment->branch_id;
                $appointmentDate = $lockedAppointment->starts_at->toDateString();
                Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
                $dailyLimit = DB::table('appointment_day_capacities')
                    ->where('branch_id', $branchId)
                    ->where('appointment_date', $appointmentDate)
                    ->value('daily_limit');

                if ($dailyLimit === null) {
                    throw ValidationException::withMessages([
                        'status' => 'Set a daily appointment limit for this branch and date before approving requests.',
                    ]);
                }

                $appointmentsAlreadyCounted = $this->countAppointmentsAgainstCapacity(
                    $branchId,
                    $appointmentDate,
                    $lockedAppointment->id,
                );
                if ($appointmentsAlreadyCounted >= (int) $dailyLimit) {
                    throw ValidationException::withMessages([
                        'status' => 'The daily appointment approval limit has been reached for this branch and date.',
                    ]);
                }
            }

            $previousStatus = $lockedAppointment->status;
            $lockedAppointment->update(['status' => $to]);

            if ($action === 'approve' || in_array($to, self::RELEASED_APPOINTMENT_STATUSES, true)) {
                DB::table('appointment_slots')->where('appointment_id', $lockedAppointment->id)->delete();
            }

            $queueEntry = QueueEntry::query()->whereBelongsTo($lockedAppointment)->first();
            if ($queueEntry !== null) {
                $queueStatus = match ($action) {
                    'call' => 'called',
                    'consult' => 'in_consultation',
                    'cancel', 'no-show' => 'cancelled',
                    default => $queueEntry->status,
                };

                $queueEntry->update(['status' => $queueStatus]);
            }

            return $previousStatus;
        });

        $appointment->refresh()->load(['patient.user', 'branch']);
        $scheduleLabel = $this->formatAppointmentSchedule($appointment, 'M j, Y g:i A', 'M j, Y');
        $scheduleMessage = $this->formatAppointmentSchedule($appointment, 'F j, Y \a\t g:i A', 'F j, Y');
        $auditTrail->record(
            $user,
            'appointment_'.$action,
            'appointments',
            $appointment,
            sprintf(
                '%s %s for patient %s on %s at %s; appointment #%d status changed from %s to %s.',
                $user->name,
                match ($action) {
                    'approve' => 'approved the appointment',
                    'reject' => 'rejected the appointment',
                    'cancel' => 'cancelled the appointment',
                    'call' => 'called the patient for the appointment',
                    'consult' => 'started a consultation for the appointment',
                    'no-show' => 'marked the appointment as no-show',
                },
                $appointment->patient->user->name,
                $scheduleLabel,
                $appointment->branch?->name ?? 'an unassigned branch',
                $appointment->id,
                str_replace('_', ' ', $previousStatus),
                str_replace('_', ' ', $appointment->status),
            ),
        );
        $notificationDetails = match ($action) {
            'approve' => ['appointment.approved', 'Appointment Approved', "{$user->name} approved {$appointment->patient->user->name}'s appointment for {$scheduleMessage}."],
            'reject' => ['appointment.rejected', 'Appointment Rejected', "{$user->name} rejected {$appointment->patient->user->name}'s appointment request."],
            'cancel' => $user->role === 'patient'
                ? ['appointment.cancelled', 'Appointment Cancelled', "{$appointment->patient->user->name} cancelled their appointment scheduled for {$scheduleMessage}."]
                : ['appointment.cancelled', 'Appointment Cancelled', "{$user->name} cancelled {$appointment->patient->user->name}'s appointment scheduled for {$scheduleMessage}."],
            'call' => ['appointment.called', 'Patient Called', "{$user->name} called {$appointment->patient->user->name} from the queue."],
            'consult' => ['appointment.consultation_started', 'Consultation Started', "{$user->name} started a consultation with {$appointment->patient->user->name}."],
            'no-show' => ['appointment.no_show', 'Patient Marked No-Show', "{$user->name} marked {$appointment->patient->user->name} as a no-show."],
        };
        $notifications->notifyClinicStaff(
            $user,
            $notificationDetails[0],
            $notificationDetails[1],
            $notificationDetails[2],
            route('clinic.appointment-records', [
                'date' => $appointment->starts_at->toDateString(),
                'status' => $appointment->status,
            ], false).'#appointment-'.$appointment->id,
            'appointment',
            $appointment->id,
        );

        if (in_array($action, ['approve', 'reject'], true)) {
            $message = match ($action) {
                'approve' => 'Your dental appointment is confirmed for '.$scheduleMessage.'.',
                'reject' => 'Your dental appointment request was not approved. Please contact the clinic to reschedule.',
            };
            $smsNotifier->send($appointment->patient, 'appointment_'.$action, $message, $appointment);
        }

        $statusMessage = 'Appointment updated to '.str_replace('_', ' ', $appointment->status).'.';

        if ($action === 'consult' && $user->hasRole('admin', 'staff', 'doctor')) {
            return $this->redirectToAppointment($request, $appointment, $statusMessage);
        }

        return back()->with('status', $statusMessage);
    }

    /**
     * @return array<string, int|string>
     */
    private function appointmentListFilters(Request $request): array
    {
        $filters = [];
        $branchId = $request->query('branch_id');

        if (is_string($branchId) && $branchId === 'unassigned') {
            $filters['branch_id'] = $branchId;
        } elseif (is_string($branchId) && ctype_digit($branchId) && Branch::query()->whereKey($branchId)->exists()) {
            $filters['branch_id'] = $branchId;
        }

        $search = $request->query('search');

        if (is_string($search) && mb_strlen($search) <= 100) {
            $filters['search'] = $search;
        }

        return $filters;
    }

    private function redirectToAppointment(Request $request, Appointment $appointment, string $message): RedirectResponse
    {
        $query = [
            ...$this->appointmentListFilters($request),
            'date' => $appointment->starts_at->toDateString(),
            'status' => $appointment->status,
            'appointment_id' => $appointment->id,
        ];

        return redirect()->to(route('clinic.appointment-records', $query).'#appointment-'.$appointment->id)
            ->with('status', $message);
    }

    public function reschedule(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $user = $request->user();
        $hasAssignedBranch = $user->hasRole('staff', 'doctor') && $user->branch_id !== null;

        if ($hasAssignedBranch && (int) $appointment->branch_id !== (int) $user->branch_id) {
            abort(404);
        }

        $canChangeBranch = $request->user()->hasRole('admin', 'staff');
        $data = $request->validate([
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['prohibited'],
            'starts_at' => ['prohibited'],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
            ],
        ]);
        if (
            $hasAssignedBranch
            && isset($data['branch_id'])
            && (int) $data['branch_id'] !== (int) $user->branch_id
        ) {
            throw ValidationException::withMessages([
                'branch_id' => 'Appointments must remain at your assigned branch.',
            ]);
        }

        $startsAt = $this->appointmentDate($data['appointment_date']);

        [$previousStart, $previousBranchId] = DB::transaction(function () use ($appointment, $startsAt, $request, $auditTrail, $data, $canChangeBranch): array {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(in_array($lockedAppointment->status, ['pending', 'approved'], true), 422);
            abort_unless(in_array($lockedAppointment->type, ['online', 'appointment'], true), 422);

            $previousStart = $lockedAppointment->starts_at;
            $previousBranchId = $lockedAppointment->branch_id;
            $branchId = $canChangeBranch
                ? (int) ($data['branch_id'] ?? $lockedAppointment->branch_id ?? 0)
                : (int) ($lockedAppointment->branch_id ?? 0);

            if (! $canChangeBranch && isset($data['branch_id']) && (int) $data['branch_id'] !== $lockedAppointment->branch_id) {
                abort(403);
            }
            if ($branchId === 0) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Assign this appointment to a branch before rescheduling it.',
                ]);
            }

            Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
            $branchChanged = (int) ($previousBranchId ?? 0) !== $branchId;
            $dateChanged = $startsAt->toDateString() !== $lockedAppointment->starts_at->toDateString();
            if (
                $lockedAppointment->status === 'approved'
                && ($dateChanged || $branchChanged)
                && DB::table('appointment_day_capacities')
                    ->where('branch_id', $branchId)
                    ->where('appointment_date', $startsAt->toDateString())
                    ->value('daily_limit') === null
            ) {
                throw ValidationException::withMessages([
                    'appointment_date' => 'Set a daily appointment limit for this branch and date before rescheduling an approved appointment.',
                ]);
            }

            $this->ensureAppointmentDateAvailable(
                $startsAt,
                $branchId,
                $lockedAppointment->status === 'approved' ? $lockedAppointment->id : null,
            );

            if ($dateChanged || $branchChanged) {
                $lockedAppointment->update(['starts_at' => $startsAt, 'branch_id' => $branchId]);
                DB::table('appointment_slots')->where('appointment_id', $lockedAppointment->id)->delete();
            }

            $appointment->setRawAttributes($lockedAppointment->getAttributes(), true);

            if ($dateChanged || $branchChanged) {
                $auditTrail->record(
                    $request->user(),
                    'appointment_rescheduled',
                    'appointments',
                    $appointment,
                    "Rescheduled {$appointment->patient->user->name}'s appointment #{$appointment->id} from "
                        .$previousStart->format('M j, Y').' to '.$appointment->starts_at->format('M j, Y')
                        .($branchChanged ? '; branch changed from '.(Branch::find($previousBranchId)?->name ?? 'unassigned').' to '.Branch::findOrFail($branchId)->name : '')
                        .'.',
                );
            }

            return [$previousStart, $previousBranchId];
        });

        $appointment->refresh();
        if (
            $previousStart->toDateString() === $appointment->starts_at->toDateString()
            && $previousBranchId === $appointment->branch_id
        ) {
            return back()->with('status', 'The appointment is already scheduled for that date.');
        }
        $notifications->notifyClinicStaff(
            $request->user(),
            'appointment.rescheduled',
            'Appointment Rescheduled',
            "{$request->user()->name} rescheduled {$appointment->patient->user->name}'s appointment from "
                .$previousStart->format('F j, Y').' to '
                .$appointment->starts_at->format('F j, Y').'.',
            route('clinic.appointment-records', [
                'date' => $appointment->starts_at->toDateString(),
                'status' => $appointment->status,
                'branch_id' => $appointment->branch_id,
            ], false).'#appointment-'.$appointment->id,
            'appointment',
            $appointment->id,
        );

        return back()->with('status', 'Appointment rescheduled to '.$appointment->starts_at->format('F j, Y').'.');
    }

    private function appointmentDate(string $date): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $date, config('clinic.timezone'));
    }

    private function ensureAppointmentDateAvailable(
        Carbon $date,
        int $branchId,
        ?int $exceptAppointmentId = null,
    ): void {
        $dateKey = $date->toDateString();
        $clinicToday = today(config('clinic.timezone'))->toDateString();

        if ($dateKey < $clinicToday) {
            throw ValidationException::withMessages([
                'appointment_date' => 'Choose today or a future appointment date.',
            ]);
        }

        $holidayName = $this->holidayCalendar->nameFor($date);
        if ($holidayName !== null) {
            throw ValidationException::withMessages([
                'appointment_date' => "Appointments cannot be booked on {$holidayName}. Please select another date.",
            ]);
        }

        if ($date->dayOfWeekIso === 7) {
            throw ValidationException::withMessages([
                'appointment_date' => 'The Dental Clinic is closed on Sundays. Please select another date.',
            ]);
        }

        $dailyLimit = DB::table('appointment_day_capacities')
            ->where('branch_id', $branchId)
            ->where('appointment_date', $dateKey)
            ->value('daily_limit');

        if (
            $dailyLimit !== null
            && $this->countAppointmentsAgainstCapacity($branchId, $dateKey, $exceptAppointmentId) >= (int) $dailyLimit
        ) {
            throw ValidationException::withMessages([
                'appointment_date' => 'The daily appointment approval limit has been reached for this branch and date. Choose another date.',
            ]);
        }
    }

    private function countAppointmentsAgainstCapacity(
        int $branchId,
        string $date,
        ?int $exceptAppointmentId = null,
    ): int {
        return Appointment::query()
            ->where('branch_id', $branchId)
            ->whereDate('starts_at', $date)
            ->whereIn('type', ['online', 'appointment'])
            ->whereIn('status', self::CAPACITY_COUNTED_STATUSES)
            ->when($exceptAppointmentId !== null, fn ($query) => $query->where('id', '!=', $exceptAppointmentId))
            ->count();
    }

    private function formatAppointmentSchedule(Appointment $appointment, string $dateTimeFormat, string $dateFormat): string
    {
        $format = $appointment->type === 'walk_in' ? $dateTimeFormat : $dateFormat;

        return $appointment->starts_at->format($format);
    }

    private function nextAppointmentStart(CarbonInterface $currentTime, int $durationMinutes): Carbon
    {
        $currentTime = Carbon::instance($currentTime);
        $candidate = $currentTime->copy()->second(0)->microsecond(0)->ceilMinutes(30);

        if (! $candidate->greaterThan($currentTime)) {
            $candidate->addMinutes(30);
        }

        while (true) {
            if ($candidate->dayOfWeekIso === 7) {
                $candidate->next(UnitValue::MONDAY)->setTime(10, 0);

                continue;
            }

            $minutes = ($candidate->hour * 60) + $candidate->minute;

            if ($minutes < 10 * 60) {
                $candidate->setTime(10, 0);

                continue;
            }

            if (($minutes >= 12 * 60 && $minutes < 13 * 60)
                || ($minutes < 12 * 60 && $minutes + $durationMinutes > 12 * 60)) {
                $candidate->setTime(13, 0);

                continue;
            }

            if ($minutes + $durationMinutes > 17 * 60) {
                $candidate->addDay()->setTime(10, 0);

                continue;
            }

            return $candidate;
        }
    }

    /**
     * @return list<string>
     */
    private function appointmentSlotKeys(Carbon $startsAt, int $durationMinutes): array
    {
        $slotKeys = [];
        for ($minute = 0; $minute < $durationMinutes; $minute += 10) {
            $slotKeys[] = $startsAt->copy()->addMinutes($minute)->format('Y-m-d H:i:s');
        }

        return $slotKeys;
    }

    private function clinicDateTime(Carbon $dateTime): Carbon
    {
        return Carbon::createFromFormat(
            '!Y-m-d H:i:s',
            $dateTime->format('Y-m-d H:i:s'),
            config('clinic.timezone'),
        );
    }

    private function claimAppointmentSlots(array $slotKeys, int $branchId, ?int $appointmentId = null): void
    {
        $rows = array_map(
            fn (string $slotKey): array => [
                'branch_id' => $branchId,
                'starts_at' => $slotKey,
                'appointment_id' => $appointmentId,
            ],
            $slotKeys,
        );
        DB::table('appointment_slots')->insertOrIgnore($rows);

        $ownedSlots = DB::table('appointment_slots')
            ->where('branch_id', $branchId)
            ->whereIn('starts_at', $slotKeys)
            ->when(
                $appointmentId === null,
                fn ($query) => $query->whereNull('appointment_id'),
                fn ($query) => $query->where('appointment_id', $appointmentId),
            )
            ->count();

        if ($ownedSlots !== count($slotKeys)) {
            throw ValidationException::withMessages([
                'starts_at' => 'That time is no longer available. Choose another time.',
            ]);
        }
    }

    private function attachAppointmentSlots(array $slotKeys, int $branchId, int $appointmentId): void
    {
        $attached = DB::table('appointment_slots')
            ->where('branch_id', $branchId)
            ->whereIn('starts_at', $slotKeys)
            ->whereNull('appointment_id')
            ->update(['appointment_id' => $appointmentId]);

        if ($attached !== count($slotKeys)) {
            throw new RuntimeException('Unable to attach the reserved appointment slot.');
        }
    }

    private function withinClinicHours(Carbon $startsAt, int $durationMinutes): bool
    {
        $minutes = ($startsAt->hour * 60) + $startsAt->minute;
        $endsAt = $minutes + $durationMinutes;

        return $minutes >= 10 * 60
            && $minutes % 30 === 0
            && $endsAt <= 17 * 60
            && ($minutes >= 13 * 60 || $endsAt <= 12 * 60);
    }

    private function ensureAppointmentAvailability(
        Carbon $startsAt,
        int $durationMinutes,
        int $branchId,
        ?int $ignoreAppointmentId = null,
    ): void {
        $holidayName = $this->holidayCalendar->nameFor($startsAt);
        if ($holidayName !== null) {
            throw ValidationException::withMessages([
                'starts_at' => "Appointments cannot be booked on {$holidayName}. Please select another date.",
            ]);
        }

        if ($startsAt->dayOfWeekIso === 7) {
            throw ValidationException::withMessages([
                'starts_at' => 'The Dental Clinic is closed on Sundays. Please select another date.',
            ]);
        }

        if (! $this->withinClinicHours($startsAt, $durationMinutes)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Appointments are available Monday to Saturday in 30-minute slots from 10:00 AM to 5:00 PM, excluding the 12:00 PM to 1:00 PM lunch break.',
            ]);
        }

        if ($startsAt->lessThanOrEqualTo(now(config('clinic.timezone')))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Choose an appointment time in the future.',
            ]);
        }

        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $availabilityIsBlocked = AppointmentAvailabilityBlock::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $startsAt->format('Y-m-d H:i:s'))
            ->exists();

        if ($availabilityIsBlocked) {
            throw ValidationException::withMessages([
                'starts_at' => 'This time is blocked for the selected branch. Choose another available time.',
            ]);
        }

        $existingAppointments = Appointment::query()
            ->where('branch_id', $branchId)
            ->where('starts_at', '>', $startsAt->copy()->subMinutes(240)->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->whereNotIn('status', self::RELEASED_APPOINTMENT_STATUSES)
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->get(['id', 'starts_at', 'duration_minutes']);

        foreach ($existingAppointments as $existingAppointment) {
            $existingStart = $this->clinicDateTime($existingAppointment->starts_at);
            $existingEnd = $existingStart->copy()->addMinutes((int) $existingAppointment->duration_minutes);

            if ($existingStart->lessThan($endsAt) && $existingEnd->greaterThan($startsAt)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'That time is no longer available. Choose another time.',
                ]);
            }
        }

        $slotKeys = $this->appointmentSlotKeys($startsAt, $durationMinutes);
        $reservedByAnotherAppointment = DB::table('appointment_slots')
            ->where('branch_id', $branchId)
            ->whereIn('starts_at', $slotKeys)
            ->when(
                $ignoreAppointmentId === null,
                fn ($query) => $query,
                fn ($query) => $query->where(function ($query) use ($ignoreAppointmentId): void {
                    $query->whereNull('appointment_id')->orWhere('appointment_id', '!=', $ignoreAppointmentId);
                }),
            )
            ->exists();

        if ($reservedByAnotherAppointment) {
            throw ValidationException::withMessages([
                'starts_at' => 'That time is no longer available. Choose another time.',
            ]);
        }
    }
}
