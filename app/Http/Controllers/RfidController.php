<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\RfidCard;
use App\Models\RfidLog;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidController extends Controller
{
    public function patientCard(Request $request): View
    {
        $patient = $request->user()->patient()->with('rfidCard')->firstOrFail();

        return view('rfid.patient', ['card' => $patient->rfidCard]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isBranchDoctor = $user->hasRole('doctor');
        abort_if($isBranchDoctor && $user->branch_id === null, 403);

        return view('rfid.index', [
            'patients' => Patient::query()
                ->with(['user', 'rfidCard'])
                ->when(
                    $isBranchDoctor,
                    fn ($query) => $query->forBranch((int) $user->branch_id),
                )
                ->orderBy('patient_number')
                ->paginate(20),
            'isReadOnly' => $isBranchDoctor,
        ]);
    }

    public function assign(Request $request, Patient $patient, AuditTrail $auditTrail): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin', 'staff'), 403);

        $request->merge([
            'uid' => mb_strtoupper(trim((string) $request->input('uid'))),
        ]);
        $card = $patient->rfidCard;
        $wasAssigned = $card !== null;
        $previousCardDetails = $card === null ? null : [
            'is_active' => $card->is_active,
            'valid_from' => $card->valid_from?->toDateString(),
            'valid_until' => $card->valid_until?->toDateString(),
        ];
        $data = $request->validate([
            'uid' => ['required', 'string', 'max:100', Rule::unique('rfid_cards', 'uid')->ignore($card?->id)],
            'valid_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'valid_until' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                Rule::when($request->filled('valid_from'), ['after:valid_from']),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $cardAttributes = [
            'uid' => $data['uid'],
            'is_active' => isset($data['is_active'])
                ? (bool) $data['is_active']
                : ($card?->is_active ?? true),
        ];

        foreach (['valid_from', 'valid_until'] as $validityField) {
            if (array_key_exists($validityField, $data)) {
                $cardAttributes[$validityField] = $data[$validityField] === null
                    ? null
                    : Carbon::createFromFormat(
                        '!Y-m-d',
                        $data[$validityField],
                        config('clinic.timezone'),
                    );
            }
        }

        DB::transaction(function () use ($request, $patient, $cardAttributes, $wasAssigned, $previousCardDetails, $auditTrail): void {
            $card = RfidCard::updateOrCreate(
                ['patient_id' => $patient->id],
                $cardAttributes,
            );
            $validityPeriod = 'active '.($card->is_active ? 'enabled' : 'disabled')
                .'; valid from '.($card->valid_from?->format('Y-m-d') ?? 'any date')
                .'; valid until '.($card->valid_until?->format('Y-m-d') ?? 'no expiry');
            $description = $wasAssigned
                ? "Updated RFID card settings for patient {$patient->patient_number}: {$validityPeriod}."
                : "Assigned an RFID card to patient {$patient->patient_number}: {$validityPeriod}; card UID was not recorded.";

            if ($wasAssigned && $previousCardDetails !== null) {
                $changedFields = [];
                $currentDetails = [
                    'is_active' => $card->is_active,
                    'valid_from' => $card->valid_from?->toDateString(),
                    'valid_until' => $card->valid_until?->toDateString(),
                ];
                foreach ($currentDetails as $field => $value) {
                    if ($previousCardDetails[$field] !== $value) {
                        $changedFields[] = str_replace('_', ' ', $field);
                    }
                }
                $description = $changedFields === []
                    ? "Saved RFID card settings for patient {$patient->patient_number} without changing card status or validity dates."
                    : 'Changed '.implode(', ', $changedFields)." for the RFID card assigned to patient {$patient->patient_number}; {$validityPeriod}.";
            }

            $auditTrail->record(
                $request->user(),
                $wasAssigned ? 'rfid_card_updated' : 'rfid_assigned',
                'rfid',
                $card,
                $description,
            );
        });

        return back()->with('status', 'RFID card assigned.');
    }

    public function scanPage(Request $request): View
    {
        if ($request->filled('uid')) {
            $data = $request->validate([
                'uid' => ['required', 'string', 'max:100'],
            ]);

            return $this->scanResult($request, mb_strtoupper(trim($data['uid'])));
        }

        return view('rfid.scan');
    }

    public function scan(Request $request): View
    {
        $data = $request->validate(['uid' => ['required', 'string', 'max:100']]);
        $uid = mb_strtoupper(trim($data['uid']));

        return $this->scanResult($request, $uid, true);
    }

    private function scanResult(Request $request, string $uid, bool $logScan = false): View
    {
        $user = $request->user();
        $isBranchDoctor = $user->hasRole('doctor');
        abort_if($isBranchDoctor && $user->branch_id === null, 403);

        $card = RfidCard::query()
            ->with(['patient.user'])
            ->where('uid', $uid)
            ->when(
                $isBranchDoctor,
                fn ($query) => $query->whereHas(
                    'patient',
                    fn ($patientQuery) => $patientQuery->forBranch((int) $user->branch_id),
                ),
            )
            ->first();
        $now = now(config('clinic.timezone'));
        $cardStatus = $card?->validityStatus($now) ?? 'not_registered';
        $patient = $cardStatus === 'active' ? $card?->patient : null;
        $filters = $request->validate([
            'record_search' => ['nullable', 'string', 'max:100'],
            'record_from' => ['nullable', 'date_format:Y-m-d'],
            'record_until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($request->filled('record_from'), ['after_or_equal:record_from']),
            ],
            'appointment_search' => ['nullable', 'string', 'max:100'],
            'appointment_status' => ['nullable', 'in:pending,approved,rejected,checked_in,waiting,in_consultation,billed,completed,cancelled,no_show'],
            'appointment_from' => ['nullable', 'date_format:Y-m-d'],
            'appointment_until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($request->filled('appointment_from'), ['after_or_equal:appointment_from']),
            ],
            'section' => ['nullable', Rule::in(['records', 'appointments'])],
            'records_page' => ['sometimes', 'integer', 'min:1'],
            'appointments_page' => ['sometimes', 'integer', 'min:1'],
        ]);

        if ($logScan) {
            RfidLog::create([
                'rfid_card_id' => $card?->id,
                'patient_id' => $card?->patient_id,
                'scanned_by' => $request->user()->id,
                'uid' => $uid,
                'result' => $cardStatus === 'not_registered' ? 'unknown' : ($cardStatus === 'active' ? 'identified' : $cardStatus),
                'scanned_at' => $now,
            ]);
        }

        $activeSection = $filters['section'] ?? 'appointments';
        $paginationQuery = [
            ...array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            'uid' => $uid,
            'section' => $activeSection,
        ];

        return view('rfid.scan', [
            'uid' => $uid,
            'cardStatus' => $cardStatus,
            'scanMessage' => match ($cardStatus) {
                'active' => null,
                'not_yet_valid' => 'This RFID card is not yet valid.',
                'expired' => 'This RFID card has expired.',
                'disabled' => 'This RFID card has been disabled.',
                default => 'No registered RFID card matches this UID.',
            },
            'patient' => $patient,
            'nextAppointment' => $patient?->appointments()
                ->with(['branch', 'service', 'services', 'queueEntry'])
                ->when(
                    $request->user()->hasRole('staff', 'doctor') && $request->user()->branch_id !== null,
                    fn ($query) => $query->where('branch_id', $request->user()->branch_id),
                )
                ->where('starts_at', '>=', $now)
                ->whereIn('status', ['pending', 'approved', 'checked_in', 'waiting', 'in_consultation', 'billed'])
                ->orderBy('starts_at')
                ->orderBy('id')
                ->first(),
            'filters' => $filters,
            'activeSection' => $activeSection,
            'paginationQuery' => $paginationQuery,
            'dentalRecords' => $patient?->dentalRecords()
                ->with(['appointment.service', 'appointment.services'])
                ->when(
                    $isBranchDoctor,
                    fn ($query) => $query->whereHas(
                        'appointment',
                        fn ($appointmentQuery) => $appointmentQuery->where('branch_id', $user->branch_id),
                    ),
                )
                ->when($filters['record_search'] ?? null, function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('diagnosis', 'like', '%'.$search.'%')
                            ->orWhere('treatment', 'like', '%'.$search.'%')
                            ->orWhere('prescription', 'like', '%'.$search.'%')
                            ->orWhereHas('appointment', function ($appointmentQuery) use ($search): void {
                                $appointmentQuery->where('status', 'like', '%'.str_replace(' ', '_', $search).'%')
                                    ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'))
                                    ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'));
                            });
                    });
                })
                ->when($filters['record_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['record_until'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(5, ['*'], 'records_page')
                ->appends($paginationQuery),
            'appointments' => $patient?->appointments()
                ->with(['branch', 'service', 'services', 'queueEntry'])
                ->when(
                    $request->user()->hasRole('staff', 'doctor') && $request->user()->branch_id !== null,
                    fn ($query) => $query->where('branch_id', $request->user()->branch_id),
                )
                ->when($filters['appointment_search'] ?? null, function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('status', 'like', '%'.str_replace(' ', '_', $search).'%')
                            ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('name', 'like', '%'.$search.'%'));
                    });
                })
                ->when($filters['appointment_status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['appointment_from'] ?? null, fn ($query, string $date) => $query->whereDate('starts_at', '>=', $date))
                ->when($filters['appointment_until'] ?? null, fn ($query, string $date) => $query->whereDate('starts_at', '<=', $date))
                ->orderByRaw('CASE WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
                ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
                ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
                ->orderByDesc('id')
                ->paginate(5, ['*'], 'appointments_page')
                ->appends($paginationQuery),
        ]);
    }
}
