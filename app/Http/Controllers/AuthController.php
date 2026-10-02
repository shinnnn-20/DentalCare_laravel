<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

        if (! Auth::attempt([
            'email' => mb_strtolower($credentials['email']),
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match an active clinic account.',
            ]);
        }

        $request->session()->regenerate();
        $auditTrail->record($request->user(), 'login', 'authentication');

        return redirect()->intended(route('dashboard'));
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function storePatient(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
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

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Your patient account has been created.');
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
