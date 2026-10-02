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
            'staffMembers' => User::query()->where('role', 'staff')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $staff = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => 'staff',
            'is_active' => true,
        ]);

        $auditTrail->record($request->user(), 'staff_created', 'staff', $staff);

        return back()->with('status', 'Staff account created.');
    }

    public function toggle(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);
        $staff->update(['is_active' => ! $staff->is_active]);
        $auditTrail->record($request->user(), $staff->is_active ? 'staff_activated' : 'staff_disabled', 'staff', $staff);

        return back()->with('status', 'Staff account status updated.');
    }

    public function resetPassword(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $staff->update(['password' => $data['password']]);
        $auditTrail->record($request->user(), 'staff_password_reset', 'staff', $staff);

        return back()->with('status', 'Staff password reset.');
    }
}
