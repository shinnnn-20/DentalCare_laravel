<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use App\Services\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower($credentials['email']))
            ->where('is_active', true)
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match an active clinic account.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $auditTrail->record($request->user(), 'login', 'authentication');

        return redirect()->intended(route('dashboard'));
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function storePatient(
        Request $request,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
        EmailVerificationService $emailVerification,
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

        $user = DB::transaction(function () use ($data, $auditTrail): User {
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

            $patient->update([
                'patient_number' => sprintf('P-%s-%05d', now()->format('Y'), $patient->id),
            ]);

            $auditTrail->record($user, 'patient_registered', 'patients', $patient);

            return $user;
        });

        $request->session()->put('verification_user_id', $user->id);
        $patient = $user->patient()->firstOrFail();
        $notifications->notifyClinicStaff(
            $user,
            'patient.registered',
            'New Patient Registered',
            "{$user->name} registered a new patient account.",
            route('clinic.patients.show', $patient, false),
            'patient',
            $patient->id,
        );

        try {
            $emailVerification->send($user, false);
        } catch (Throwable $exception) {
            Log::error('Patient email verification message could not be sent after registration.', [
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return redirect()->route('verification.notice')
                ->withErrors(['email' => 'Unable to send the verification code. Please use Resend Code to try again.']);
        }

        return redirect()->route('verification.notice');
    }

    public function logout(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $user = $request->user();
        $auditTrail->record($user, 'logout', 'authentication');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
