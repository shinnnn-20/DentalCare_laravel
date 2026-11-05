<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentAvailabilityBlock;
use App\Models\Branch;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentAvailabilityController extends Controller
{
    private const REASONS = [
        'Long dental procedure',
        'Dentist unavailable',
        'Staff unavailable',
        'Equipment maintenance',
        'Clinic activity',
        'Other',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $hasAssignedBranch = $user->hasRole('staff', 'doctor') && $user->branch_id !== null;
        $branches = Branch::query()
            ->when($hasAssignedBranch, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('id')
            ->get();
        $clinicNow = now(config('clinic.timezone'));
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if (
            $hasAssignedBranch
            && isset($filters['branch_id'])
            && (int) $filters['branch_id'] !== (int) $user->branch_id
        ) {
            abort(404);
        }

        $branchId = $hasAssignedBranch
            ? (int) $user->branch_id
            : ($filters['branch_id'] ?? null);
        $selectedDate = isset($filters['date'])
            ? Carbon::createFromFormat('!Y-m-d', $filters['date'], config('clinic.timezone'))
            : null;

        $availabilityBlocks = AppointmentAvailabilityBlock::query()
            ->with(['branch', 'creator'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($selectedDate !== null, fn ($query) => $query
                ->where('starts_at', '<', $selectedDate->copy()->endOfDay()->format('Y-m-d H:i:s'))
                ->where('ends_at', '>', $selectedDate->copy()->startOfDay()->format('Y-m-d H:i:s')))
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('appointments.availability', [
            'availabilityBlocks' => $availabilityBlocks,
            'branches' => $branches,
            'clinicNow' => $clinicNow,
            'reasons' => self::REASONS,
            'hasAssignedBranch' => $hasAssignedBranch,
            'selectedBranchId' => $branchId,
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'reason' => ['required', Rule::in(self::REASONS)],
            'description' => ['nullable', 'string', 'max:1000'],
            'confirm_existing_appointments' => ['accepted'],
        ]);
        if (
            $user->hasRole('staff', 'doctor')
            && $user->branch_id !== null
            && (int) $data['branch_id'] !== (int) $user->branch_id
        ) {
            throw ValidationException::withMessages([
                'branch_id' => 'Availability can only be managed for your assigned branch.',
            ]);
        }

        [$startsAt, $endsAt] = $this->validatedRange($data['date'], $data['starts_at'], $data['ends_at']);
        $branchId = (int) $data['branch_id'];

        DB::transaction(function () use ($request, $data, $startsAt, $endsAt, $branchId, $auditTrail): void {
            $branch = Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
            $this->ensureNoOverlappingBlock($branchId, $startsAt, $endsAt);

            $block = AppointmentAvailabilityBlock::query()->create([
                'branch_id' => $branch->id,
                'created_by' => $request->user()->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);

            $auditTrail->record(
                $request->user(),
                'appointment_availability_blocked',
                'appointment_availability',
                $block,
                $branch->name.' · '.$startsAt->format('M j, Y g:i A').'–'.$endsAt->format('g:i A').' · '.$data['reason'],
            );
        });

        return redirect()->route('clinic.availability.index')->with('status', 'Appointment availability blocked.');
    }

    public function update(Request $request, AppointmentAvailabilityBlock $availabilityBlock, AuditTrail $auditTrail): RedirectResponse
    {
        abort_if($request->user()->hasRole('doctor') && $request->user()->branch_id === null, 403);
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'confirm_existing_appointments' => ['sometimes', 'boolean'],
        ]);
        $isActive = $request->boolean('is_active');

        DB::transaction(function () use ($request, $availabilityBlock, $isActive, $data, $auditTrail): void {
            $lockedBlock = AppointmentAvailabilityBlock::query()
                ->whereKey($availabilityBlock->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (
                $request->user()->hasRole('staff', 'doctor')
                && $request->user()->branch_id !== null
                && (int) $lockedBlock->branch_id !== (int) $request->user()->branch_id
            ) {
                abort(404);
            }

            $branch = Branch::query()->whereKey($lockedBlock->branch_id)->lockForUpdate()->firstOrFail();

            if ($isActive) {
                $startsAt = Carbon::createFromFormat(
                    '!Y-m-d H:i:s',
                    $lockedBlock->starts_at->format('Y-m-d H:i:s'),
                    config('clinic.timezone'),
                );
                $endsAt = Carbon::createFromFormat(
                    '!Y-m-d H:i:s',
                    $lockedBlock->ends_at->format('Y-m-d H:i:s'),
                    config('clinic.timezone'),
                );
                $this->ensureNoOverlappingBlock(
                    (int) $lockedBlock->branch_id,
                    $startsAt,
                    $endsAt,
                    $lockedBlock->id,
                );
                $overlappingAppointments = $this->overlappingAppointments(
                    (int) $lockedBlock->branch_id,
                    $startsAt,
                    $endsAt,
                );

                if ($overlappingAppointments->isNotEmpty() && ! ($data['confirm_existing_appointments'] ?? false)) {
                    throw ValidationException::withMessages([
                        'confirm_existing_appointments' => 'This time overlaps '.$overlappingAppointments->count()
                            .' existing appointment(s). Confirm to re-enable this block; existing appointments will remain booked.',
                    ]);
                }
            }

            $lockedBlock->update(['is_active' => $isActive]);
            $auditTrail->record(
                $request->user(),
                $isActive ? 'appointment_availability_reenabled' : 'appointment_availability_disabled',
                'appointment_availability',
                $lockedBlock,
                $branch->name.' · '.$lockedBlock->starts_at->format('M j, Y g:i A').'–'.$lockedBlock->ends_at->format('g:i A'),
            );
        });

        return redirect()->route('clinic.availability.index')->with(
            'status',
            $isActive ? 'Appointment availability block re-enabled.' : 'Appointment availability block disabled.',
        );
    }

    public function destroy(Request $request, AppointmentAvailabilityBlock $availabilityBlock, AuditTrail $auditTrail): RedirectResponse
    {
        abort_if($request->user()->hasRole('doctor') && $request->user()->branch_id === null, 403);
        DB::transaction(function () use ($request, $availabilityBlock, $auditTrail): void {
            $lockedBlock = AppointmentAvailabilityBlock::query()
                ->whereKey($availabilityBlock->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (
                $request->user()->hasRole('staff', 'doctor')
                && $request->user()->branch_id !== null
                && (int) $lockedBlock->branch_id !== (int) $request->user()->branch_id
            ) {
                abort(404);
            }

            $branch = Branch::query()->whereKey($lockedBlock->branch_id)->lockForUpdate()->firstOrFail();

            $auditTrail->record(
                $request->user(),
                'appointment_availability_deleted',
                'appointment_availability',
                $lockedBlock,
                $branch->name.' · '.$lockedBlock->starts_at->format('M j, Y g:i A').'–'.$lockedBlock->ends_at->format('g:i A').' · '.$lockedBlock->reason,
            );
            $lockedBlock->delete();
        });

        return redirect()->route('clinic.availability.index')->with('status', 'Appointment availability block deleted.');
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function validatedRange(string $date, string $startTime, string $endTime): array
    {
        $timezone = config('clinic.timezone');
        $clinicNow = now($timezone);
        $startsAt = Carbon::createFromFormat('!Y-m-d H:i', $date.' '.$startTime, $timezone);
        $endsAt = Carbon::createFromFormat('!Y-m-d H:i', $date.' '.$endTime, $timezone);
        $opensAt = $startsAt->copy()->setTime(10, 0);
        $closesAt = $startsAt->copy()->setTime(17, 0);

        if ($startsAt->toDateString() < $clinicNow->toDateString()) {
            throw ValidationException::withMessages([
                'date' => 'Choose today or a future date to block availability.',
            ]);
        }

        if ($startsAt->dayOfWeekIso === 7) {
            throw ValidationException::withMessages([
                'date' => 'The Dental Clinic is closed on Sundays. Choose another date.',
            ]);
        }

        if ($startsAt->lessThanOrEqualTo($clinicNow)) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a future time to block.']);
        }

        if (
            $startsAt->greaterThanOrEqualTo($endsAt)
            || $startsAt->minute % 30 !== 0
            || $endsAt->minute % 30 !== 0
            || $startsAt->lessThan($opensAt)
            || $endsAt->greaterThan($closesAt)
        ) {
            throw ValidationException::withMessages([
                'starts_at' => 'Choose a continuous range on 30-minute intervals between 10:00 AM and 5:00 PM.',
            ]);
        }

        return [$startsAt, $endsAt];
    }

    private function ensureNoOverlappingBlock(
        int $branchId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreBlockId = null,
    ): void {
        $hasOverlappingBlock = AppointmentAvailabilityBlock::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $startsAt->format('Y-m-d H:i:s'))
            ->when($ignoreBlockId !== null, fn ($query) => $query->whereKeyNot($ignoreBlockId))
            ->exists();

        if ($hasOverlappingBlock) {
            throw ValidationException::withMessages([
                'starts_at' => 'An active availability block already overlaps this time. Adjust or re-enable the existing block.',
            ]);
        }
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function overlappingAppointments(int $branchId, Carbon $startsAt, Carbon $endsAt): Collection
    {
        $maximumDuration = (int) Appointment::query()
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'no_show'])
            ->max('duration_minutes');

        return Appointment::query()
            ->where('branch_id', $branchId)
            ->where('starts_at', '>=', $startsAt->copy()->subMinutes($maximumDuration)->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'no_show'])
            ->get(['id', 'starts_at', 'duration_minutes'])
            ->filter(function (Appointment $appointment) use ($startsAt, $endsAt): bool {
                $appointmentStartsAt = Carbon::createFromFormat(
                    '!Y-m-d H:i:s',
                    $appointment->starts_at->format('Y-m-d H:i:s'),
                    config('clinic.timezone'),
                );
                $appointmentEndsAt = $appointmentStartsAt->copy()->addMinutes((int) $appointment->duration_minutes);

                return $appointmentStartsAt->lessThan($endsAt) && $appointmentEndsAt->greaterThan($startsAt);
            });
    }
}
