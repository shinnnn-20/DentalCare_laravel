<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('staff.index', [
            'clinicAccounts' => User::query()->whereIn('role', ['staff', 'doctor'])->orderBy('role')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:staff,doctor'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $account = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => true,
        ]);

        $roleLabel = ucfirst($account->role);
        $module = $account->role === 'doctor' ? 'doctors' : 'staff';
        $auditTrail->record($request->user(), $account->role.'_created', $module, $account);

        return back()->with('status', "{$roleLabel} account created.");
    }

    public function toggle(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($staff->hasRole('staff', 'doctor'), 404);
        $staff->update(['is_active' => ! $staff->is_active]);
        $roleLabel = ucfirst($staff->role);
        $action = $staff->is_active ? $staff->role.'_activated' : $staff->role.'_disabled';
        $module = $staff->role === 'doctor' ? 'doctors' : 'staff';
        $auditTrail->record($request->user(), $action, $module, $staff);

        return back()->with('status', "{$roleLabel} account status updated.");
    }

    public function resetPassword(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($staff->hasRole('staff', 'doctor'), 404);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $staff->update(['password' => $data['password']]);
        $module = $staff->role === 'doctor' ? 'doctors' : 'staff';
        $auditTrail->record($request->user(), $staff->role.'_password_reset', $module, $staff);

        return back()->with('status', ucfirst($staff->role).' password reset.');
    }
}
