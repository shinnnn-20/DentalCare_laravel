<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function create(): View
    {
        return view('patients.create');
    }

    public function index(Request $request): View
    {
        $patients = Patient::query()
            ->with('user')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where('patient_number', 'like', '%'.$term.'%')
                    ->orWhereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%'));
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('patients.index', compact('patients'));
    }

    public function show(Patient $patient): View
    {
        $patient->load([
            'user',
            'rfidCard',
            'appointments' => fn ($query) => $query->with(['service', 'bill'])->latest('starts_at'),
            'dentalRecords' => fn ($query) => $query->latest(),
        ]);

        return view('patients.show', compact('patient'));
    }

    public function resetPassword(Request $request, Patient $patient, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $patient->user->update(['password' => $data['password']]);
        $auditTrail->record($request->user(), 'patient_password_reset', 'patients', $patient);

        return redirect()->route('clinic.patients.show', $patient)
            ->with('status', 'Patient password reset.');
    }

    public function destroy(Request $request, Patient $patient, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
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
                'Patient access removed and profile details pseudonymized; clinical and billing records retained.',
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:female,male,other,prefer_not_to_say'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'address' => ['required', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $patient = DB::transaction(function () use ($data, $auditTrail, $request): Patient {
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
                'patient_number' => 'P-PENDING-'.Str::uuid(),
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'address' => $data['address'],
                'emergency_contact' => $data['emergency_contact'] ?? null,
            ]);
            $patient->update(['patient_number' => sprintf('P-%s-%05d', now()->format('Y'), $patient->id)]);
            $auditTrail->record($request->user(), 'patient_registered', 'patients', $patient);

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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($patient->user_id)],
            'phone' => ['required', 'string', 'max:30'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:female,male,other,prefer_not_to_say'],
            'address' => ['required', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
        ]);

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

        $auditTrail->record($request->user(), 'patient_updated', 'patients', $patient);
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
}
