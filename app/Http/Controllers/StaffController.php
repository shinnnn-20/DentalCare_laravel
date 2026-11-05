<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    private const ASSIGNABLE_BRANCH_SLUGS = ['general-trias', 'dasmarinas', 'trece'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('admin', 'doctor'), 403);
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);

        return view('staff.index', [
            'clinicAccounts' => User::query()
                ->with('branch')
                ->whereIn('role', ['staff', 'doctor'])
                ->when(
                    ! $user->hasRole('admin'),
                    fn ($query) => $query->where('branch_id', $user->branch_id),
                )
                ->orderBy('role')
                ->orderBy('name')
                ->paginate(20),
            'branches' => $user->hasRole('admin') ? $this->assignableBranches() : new Collection,
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $actor = $request->user();
        $isAdmin = $actor->hasRole('admin');
        abort_unless($isAdmin || $actor->hasRole('doctor'), 403);
        abort_if($actor->hasRole('doctor') && $actor->branch_id === null, 403);

        $branches = $this->assignableBranches();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => $isAdmin
                ? ['required', 'in:staff,doctor']
                : ['required', 'in:staff'],
            'branch_id' => $isAdmin
                ? ['required', 'integer', Rule::in($branches->modelKeys())]
                : ['prohibited'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $account = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => true,
            'branch_id' => $isAdmin ? (int) $data['branch_id'] : $actor->branch_id,
        ]);

        $roleLabel = ucfirst($account->role);
        $module = $account->role === 'doctor' ? 'doctors' : 'staff';
        $branchName = Branch::query()->whereKey($account->branch_id)->value('name') ?? 'unassigned';
        $auditTrail->record(
            $request->user(),
            $account->role.'_created',
            $module,
            $account,
            "Created {$account->role} account {$account->name} ({$account->email}) for branch {$branchName}.",
        );

        return back()->with('status', "{$roleLabel} account created.");
    }

    public function updateBranch(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        abort_unless($staff->hasRole('staff', 'doctor'), 404);
        $branches = $this->assignableBranches();
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($branches->modelKeys())],
        ]);
        $branch = Branch::query()->findOrFail((int) $data['branch_id']);

        $previousBranchName = Branch::query()->whereKey($staff->branch_id)->value('name') ?? 'unassigned';
        $staff->update(['branch_id' => (int) $data['branch_id']]);
        $auditTrail->record(
            $request->user(),
            $staff->role.'_branch_updated',
            $staff->role === 'doctor' ? 'doctors' : 'staff',
            $staff,
            "Changed {$staff->role} {$staff->name}'s branch assignment from {$previousBranchName} to {$branch->name}.",
        );

        return back()->with('status', ucfirst($staff->role).' branch assignment updated.');
    }

    public function toggle(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        $this->authorizeAccountManagement($request->user(), $staff);
        $staff->update(['is_active' => ! $staff->is_active]);
        $roleLabel = ucfirst($staff->role);
        $action = $staff->is_active ? $staff->role.'_activated' : $staff->role.'_disabled';
        $module = $staff->role === 'doctor' ? 'doctors' : 'staff';
        $auditTrail->record(
            $request->user(),
            $action,
            $module,
            $staff,
            ucfirst($staff->role).' account '.$staff->name.' ('.$staff->email.') was '
                .($staff->is_active ? 'activated.' : 'deactivated.'),
        );

        return back()->with('status', "{$roleLabel} account status updated.");
    }

    public function update(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        $this->authorizeAccountManagement($request->user(), $staff);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['prohibited'],
            'branch_id' => ['prohibited'],
        ]);

        $previousDetails = [
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
        ];
        $staff->update([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
        ]);
        $module = $staff->role === 'doctor' ? 'doctors' : 'staff';
        $changedFields = [];
        foreach (['name', 'email', 'phone'] as $field) {
            if ($previousDetails[$field] !== $staff->getAttribute($field)) {
                $changedFields[] = $field;
            }
        }
        $auditTrail->record(
            $request->user(),
            $staff->role.'_updated',
            $module,
            $staff,
            $changedFields === []
                ? ucfirst($staff->role).' account '.$staff->name.' was saved without changing account details.'
                : 'Updated '.implode(', ', $changedFields).' for '.$staff->role.' account '.$staff->name.'.',
        );

        return back()->with('status', ucfirst($staff->role).' account updated.');
    }

    public function resetPassword(Request $request, User $staff, AuditTrail $auditTrail): RedirectResponse
    {
        $this->authorizeAccountManagement($request->user(), $staff);
        $data = $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $staff->update(['password' => $data['password']]);
        $module = $staff->role === 'doctor' ? 'doctors' : 'staff';
        $auditTrail->record(
            $request->user(),
            $staff->role.'_password_reset',
            $module,
            $staff,
            'Reset the password for '.$staff->role.' account '.$staff->name.'; password value was not recorded.',
        );

        return back()->with('status', ucfirst($staff->role).' password reset.');
    }

    private function authorizeAccountManagement(?Authenticatable $actor, User $account): void
    {
        abort_unless($actor instanceof User && $account->hasRole('staff', 'doctor'), 404);

        if ($actor->hasRole('admin')) {
            return;
        }

        abort_unless(
            $actor->hasRole('doctor')
                && $actor->id !== $account->id
                && $account->hasRole('staff')
                && $actor->branch_id !== null
                && $account->branch_id !== null
                && (int) $actor->branch_id === (int) $account->branch_id,
            404,
        );
    }

    private function assignableBranches(): Collection
    {
        return Branch::query()
            ->whereIn('slug', self::ASSIGNABLE_BRANCH_SLUGS)
            ->orderBy('id')
            ->get();
    }
}
