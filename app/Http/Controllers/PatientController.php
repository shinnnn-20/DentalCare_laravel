<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function create(Request $request): View
    {
        $this->ensureDoctorHasBranch($request);
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');
        $isDoctor = $user->hasRole('doctor');
        $branches = $isAdmin
            ? Branch::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('patients.create', [
            'branches' => $branches,
            'isAdmin' => $isAdmin,
            'isDoctor' => $isDoctor,
            'assignedBranch' => $isDoctor
                ? Branch::query()->findOrFail($user->branch_id)
                : null,
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $isBranchDoctor = $user->hasRole('doctor');
        abort_if($isBranchDoctor && $user->branch_id === null, 403);

        $patients = Patient::query()
            ->with('user')
            ->when(
                $isBranchDoctor,
                fn ($query) => $query->forBranch((int) $user->branch_id),
            )
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where(function ($query) use ($term): void {
                    $query->where('patient_number', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', '%'.$term.'%')
                            ->orWhere('email', 'like', '%'.$term.'%')
                            ->orWhere('phone', 'like', '%'.$term.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('patients.index', compact('patients'));
    }

    public function show(Request $request, Patient $patient): View
    {
        $user = $request->user();
        $isBranchDoctor = $user->hasRole('doctor');
        $this->authorizePatientBranch($request, $patient);

        $filters = $request->validate([
            'record' => ['nullable', 'integer'],
        ]);
        $highlightedDentalRecordId = isset($filters['record']) ? (int) $filters['record'] : null;

        if ($highlightedDentalRecordId !== null) {
            abort_unless(
                $patient->dentalRecords()
                    ->whereKey($highlightedDentalRecordId)
                    ->when(
                        $isBranchDoctor,
                        fn ($query) => $query->whereHas(
                            'appointment',
                            fn ($appointmentQuery) => $appointmentQuery->where('branch_id', $user->branch_id),
                        ),
                    )
                    ->exists(),
                404,
            );
        }

        $patient->load([
            'user',
            'rfidCard',
            'appointments' => fn ($query) => $query
                ->when($isBranchDoctor, fn ($query) => $query->where('branch_id', $user->branch_id))
                ->with(['service', 'services', 'bill'])
                ->latest('starts_at'),
            'dentalRecords' => fn ($query) => $query
                ->when(
                    $isBranchDoctor,
                    fn ($query) => $query->whereHas(
                        'appointment',
                        fn ($appointmentQuery) => $appointmentQuery->where('branch_id', $user->branch_id),
                    ),
                )
                ->with(['creator', 'appointment.service', 'appointment.services'])
                ->latest(),
        ]);

        return view('patients.show', compact('patient', 'highlightedDentalRecordId'));
    }

    public function resetPassword(Request $request, Patient $patient, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $this->authorizePatientBranch($request, $patient);
        $data = $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $patient->loadMissing('user');
        $patient->user->update(['password' => $data['password']]);
        $auditTrail->record(
            $request->user(),
            'patient_password_reset',
            'patients',
            $patient,
            "Reset the password for {$patient->user->name} (patient {$patient->patient_number}); password value was not recorded.",
        );

        return redirect()->route('clinic.patients.show', $patient)
            ->with('status', 'Patient password reset.');
    }

    public function destroy(Request $request, Patient $patient, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'staff'), 403);
        $this->authorizePatientBranch($request, $patient);
        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$patient->patient_number])],
        ], [
            'confirmation.in' => 'Enter the patient number exactly to confirm account removal.',
        ]);

        DB::transaction(function () use ($request, $patient, $auditTrail): void {
            $lockedPatient = Patient::query()
                ->whereKey($patient->id)
                ->lockForUpdate()
                ->firstOrFail();
            $user = User::query()
                ->whereKey($lockedPatient->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($user->hasRole('patient'), 404);

            if (! $user->is_active) {
                return;
            }

            $auditTrail->record(
                $request->user(),
                'patient_access_removed',
                'patients',
                $lockedPatient,
                "Removed portal access for {$user->name} (patient {$lockedPatient->patient_number}); profile details were pseudonymized and clinical and billing records were retained.",
            );

            $user->forceFill([
                'name' => 'Removed Patient '.$user->id,
                'email' => 'removed-patient-'.$user->id.'@example.invalid',
                'email_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'is_active' => false,
                'phone' => null,
            ])->save();

            $lockedPatient->forceFill([
                'date_of_birth' => null,
                'gender' => null,
                'address' => null,
                'emergency_contact' => null,
            ])->save();

            DB::table('email_verification_tokens')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return redirect()->route('clinic.patients.index')
            ->with('status', 'Patient access removed. Profile details were pseudonymized; clinic records were retained.');
    }

    public function store(
        Request $request,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $this->ensureDoctorHasBranch($request);
        $user = $request->user();
        $branchId = $user->hasRole('staff', 'doctor')
            ? $user->branch_id
            : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL\pM\s.\'-]+$/u'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:female,male,other,prefer_not_to_say'],
            'phone' => ['required', 'string', 'max:11', 'regex:/^[0-9]+$/'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'address' => ['required', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'branch_id' => $user->hasRole('admin')
                ? ['required', 'integer', Rule::exists('branches', 'id')]
                : ($user->hasRole('doctor')
                    ? ['required', 'integer', Rule::in([(int) $user->branch_id])]
                    : ['prohibited']),
        ]);
        if ($user->hasRole('admin')) {
            $branchId = (int) $data['branch_id'];
        }

        $patient = DB::transaction(function () use ($data, $auditTrail, $request, $branchId): Patient {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => 'patient',
                'is_active' => true,
            ]);
            $patient = Patient::create([
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'patient_number' => 'P-PENDING-'.Str::uuid(),
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'address' => $data['address'],
                'emergency_contact' => $data['emergency_contact'] ?? null,
            ]);
            $patient->update(['patient_number' => sprintf('P-%s-%05d', now()->format('Y'), $patient->id)]);
            $auditTrail->record(
                $request->user(),
                'patient_registered',
                'patients',
                $patient,
                "Registered patient {$patient->user->name} with patient number {$patient->patient_number}"
                    .($branchId === null
                        ? ' without an assigned branch.'
                        : ' at '.(Branch::query()->whereKey($branchId)->value('name') ?? 'an unknown branch').'.'),
            );

            return $patient;
        });
        $notifications->notifyClinicStaff(
            $request->user(),
            'patient.created',
            'Patient Record Created',
            "{$request->user()->name} created a patient account for {$patient->user->name}.",
            route('clinic.patients.show', $patient, false),
            'patient',
            $patient->id,
        );

        return redirect()->route('clinic.patients.show', $patient)->with('status', 'Patient account created.');
    }

    public function update(
        Request $request,
        Patient $patient,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $this->authorizePatientBranch($request, $patient);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($patient->user_id)],
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:female,male,other,prefer_not_to_say'],
            'address' => ['required', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
        ]);

        $patient->loadMissing('user');
        $previousDetails = [
            'name' => $patient->user->name,
            'email' => $patient->user->email,
            'phone' => $patient->user->phone,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'gender' => $patient->gender,
            'address' => $patient->address,
            'emergency_contact' => $patient->emergency_contact,
        ];

        DB::transaction(function () use ($data, $patient): void {
            $patient->user->update([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'],
            ]);
            $patient->update([
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'address' => $data['address'],
                'emergency_contact' => $data['emergency_contact'] ?? null,
            ]);
        });

        $changedFields = [];
        $updatedValues = [
            'name' => $patient->user->name,
            'email' => $patient->user->email,
            'phone' => $patient->user->phone,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'gender' => $patient->gender,
            'address' => $patient->address,
            'emergency_contact' => $patient->emergency_contact,
        ];
        foreach ($updatedValues as $field => $value) {
            if ($previousDetails[$field] !== $value) {
                $changedFields[] = str_replace('_', ' ', $field);
            }
        }
        $auditTrail->record(
            $request->user(),
            'patient_updated',
            'patients',
            $patient,
            $changedFields === []
                ? "Saved patient {$patient->patient_number} without changing patient details."
                : 'Updated '.implode(', ', $changedFields)." for patient {$patient->patient_number}.",
        );
        $notifications->notifyClinicStaff(
            $request->user(),
            'patient.updated',
            'Patient Information Updated',
            "{$request->user()->name} updated the patient information for {$patient->user->name}.",
            route('clinic.patients.show', $patient, false),
            'patient',
            $patient->id,
        );

        return back()->with('status', 'Patient information updated.');
    }

    private function ensureDoctorHasBranch(Request $request): void
    {
        abort_if($request->user()->hasRole('doctor') && $request->user()->branch_id === null, 403);
    }

    private function authorizePatientBranch(Request $request, Patient $patient): void
    {
        $user = $request->user();

        if (! $user->hasRole('doctor')) {
            return;
        }

        abort_if($user->branch_id === null, 403);
        abort_unless(
            Patient::query()->forBranch((int) $user->branch_id)->whereKey($patient->id)->exists(),
            404,
        );
    }
}
