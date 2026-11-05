@extends('layouts.app')

@section('title', 'Staff and doctor accounts')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">{{ auth()->user()->role === 'admin' ? 'Administration' : 'Branch account management' }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Staff and doctor accounts</h1><p class="mt-2 text-slate-500">Manage staff and doctor accounts assigned to your branch. Account branch assignments remain admin-only.</p></div>
    @if (auth()->user()->hasRole('admin', 'doctor'))
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">Create clinic account</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-confirm="Create this clinic account and assign it to the selected branch?" data-confirm-title="Confirm account creation" data-confirm-accept="Create account">
            @csrf
            <label class="form-label">Full name<input class="form-input" name="name" required></label>
            <label class="form-label">Email<input class="form-input" type="email" name="email" required></label>
            <label class="form-label">Phone <span class="font-normal text-slate-400">(optional)</span><input class="form-input" name="phone"></label>
            <label class="form-label">Account type
                <select class="form-input" name="role" required>
                    <option value="staff" @selected(old('role', 'staff') === 'staff')>Staff</option>
                    @if (auth()->user()->role === 'admin')
                        <option value="doctor" @selected(old('role') === 'doctor')>Doctor</option>
                    @endif
                </select>
            </label>
            @if (auth()->user()->role === 'admin')
                <label class="form-label">Assigned branch
                    <select class="form-input" name="branch_id" required>
                        <option value="">Choose a branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <div class="form-label">Assigned branch<p class="form-input bg-slate-50">{{ auth()->user()->branch?->name }}</p></div>
            @endif
            <div class="grid items-start gap-4 sm:grid-cols-2 lg:col-span-2">
                <label class="form-label">Temporary password<input class="form-input" type="password" name="password" minlength="8" required><span class="text-xs font-normal text-slate-500">At least 8 characters, one capital letter, and one special character.</span></label>
                <label class="form-label">Confirm password<input class="form-input" type="password" name="password_confirmation" required></label>
            </div>
            <button class="btn-primary self-start lg:mt-6">Create account</button>
        </form>
        </section>
    @endif
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table table-fixed"><colgroup><col class="w-[12%]"><col class="w-[8%]"><col class="w-[16%]"><col class="w-[18%]"><col class="w-[10%]"><col class="w-[36%]"></colgroup><thead><tr><th>Name</th><th>Account type</th><th>Email</th><th>Assigned branch</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse ($clinicAccounts as $staff)
                <tr><td data-label="Name" class="font-semibold">{{ $staff->name }}</td><td data-label="Account type">{{ ucfirst($staff->role) }}</td><td data-label="Email">{{ $staff->email }}</td>
                    <td data-label="Assigned branch">
                        @if (auth()->user()->role === 'admin')
                            <form method="POST" action="{{ route('admin.staff.branch.update', $staff) }}" class="flex min-w-0 gap-2" data-confirm="Change {{ $staff->name }}'s assigned branch?" data-confirm-title="Confirm branch change" data-confirm-accept="Save branch">
                                @csrf
                                @method('PATCH')
                                <select class="form-input min-w-0" name="branch_id" aria-label="Assigned branch for {{ $staff->name }}" required>
                                    <option value="" @selected($staff->branch_id === null)>No branch assigned</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected($staff->branch_id === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn-small shrink-0">Save</button>
                            </form>
                        @else
                            {{ $staff->branch?->name ?? 'No branch assigned' }}
                        @endif
                    </td>
                    <td data-label="Status" class="whitespace-nowrap"><span class="status-badge">{{ $staff->is_active ? 'Active' : 'Disabled' }}</span></td>
                    <td data-label="Actions">
                        <div class="grid min-w-0 gap-2 lg:min-w-[24rem]">
                            @if (auth()->user()->role === 'admin' || (auth()->id() !== $staff->id && !(auth()->user()->role === 'doctor' && $staff->role === 'doctor')))
                                <details class="rounded-lg border border-slate-200 p-2">
                                    <summary class="cursor-pointer text-sm font-medium text-teal-800">Edit account</summary>
                                    <form method="POST" action="{{ route('admin.staff.update', $staff) }}" class="mt-3 grid gap-2" data-confirm="Save these changes to {{ $staff->name }}'s account?" data-confirm-title="Confirm account changes" data-confirm-accept="Save changes">
                                        @csrf
                                        @method('PATCH')
                                        <label class="form-label">Full name<input class="form-input" name="name" value="{{ $staff->name }}" required></label>
                                        <label class="form-label">Email<input class="form-input" type="email" name="email" value="{{ $staff->email }}" required></label>
                                        <label class="form-label">Phone <span class="font-normal text-slate-400">(optional)</span><input class="form-input" name="phone" value="{{ $staff->phone }}"></label>
                                        <button class="btn-small justify-self-start">Save account</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.staff.toggle', $staff) }}" data-confirm="{{ $staff->is_active ? 'Disable this account? The user will no longer be able to sign in.' : 'Reactivate this account and restore sign-in access?' }}" data-confirm-title="{{ $staff->is_active ? 'Confirm account disable' : 'Confirm account activation' }}" data-confirm-accept="{{ $staff->is_active ? 'Disable account' : 'Activate account' }}" data-confirm-variant="warning">
                                    @csrf
                                    @method('PATCH')
                                    <button @class([
                                        'btn-danger' => $staff->is_active,
                                        'btn-small' => ! $staff->is_active,
                                    ])>{{ $staff->is_active ? 'Disable' : 'Activate' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff.password', $staff) }}" class="grid grid-cols-1 items-center gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]" data-confirm="Reset {{ $staff->name }}'s password? Share the new password securely." data-confirm-title="Confirm password reset" data-confirm-accept="Reset password">
                                    @csrf
                                    @method('PUT')
                                    <input type="password" class="form-input min-w-0" name="password" placeholder="New password" minlength="8" title="At least 8 characters, one capital letter, and one special character" required>
                                    <input type="password" class="form-input min-w-0" name="password_confirmation" placeholder="Confirm" required>
                                    <button class="btn-small min-h-11">Reset</button>
                                </form>
                            @else
                                <span class="text-sm text-slate-500">{{ auth()->id() === $staff->id ? 'Your account' : "Another doctor's account (admin only)" }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty<tr><td colspan="6" class="py-10 text-center text-slate-500">No clinic staff or doctor accounts have been created yet.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $clinicAccounts->links() }}</div>
    </section>
@endsection
