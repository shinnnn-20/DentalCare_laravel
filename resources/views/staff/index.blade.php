@extends('layouts.app')

@section('title', 'Staff and doctor accounts')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Administration</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Staff and doctor accounts</h1><p class="mt-2 text-slate-500">Manage clinic staff and doctor access. Doctors can view future booked appointments and reschedule them.</p></div>
    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">Create clinic account</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <label class="form-label">Full name<input class="form-input" name="name" required></label>
            <label class="form-label">Email<input class="form-input" type="email" name="email" required></label>
            <label class="form-label">Phone <span class="font-normal text-slate-400">(optional)</span><input class="form-input" name="phone"></label>
            <label class="form-label">Account type
                <select class="form-input" name="role" required>
                    <option value="staff" @selected(old('role', 'staff') === 'staff')>Staff</option>
                    <option value="doctor" @selected(old('role') === 'doctor')>Doctor</option>
                </select>
            </label>
            <label class="form-label">Temporary password<input class="form-input" type="password" name="password" minlength="12" required></label>
            <label class="form-label">Confirm password<input class="form-input" type="password" name="password_confirmation" required></label>
            <button class="btn-primary self-end">Create account</button>
        </form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Name</th><th>Account type</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse ($clinicAccounts as $staff)
                <tr><td data-label="Name" class="font-semibold">{{ $staff->name }}</td><td data-label="Account type">{{ ucfirst($staff->role) }}</td><td data-label="Email">{{ $staff->email }}</td><td data-label="Status"><span class="status-badge">{{ $staff->is_active ? 'Active' : 'Disabled' }}</span></td>
                    <td data-label="Actions"><div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.staff.toggle', $staff) }}">@csrf @method('PATCH')<button class="btn-small text-teal-800">{{ $staff->is_active ? 'Disable' : 'Activate' }}</button></form>
                        <form method="POST" action="{{ route('admin.staff.password', $staff) }}" class="flex flex-wrap gap-2">@csrf @method('PUT')<input type="password" class="form-input min-w-0 flex-1 sm:min-w-32" name="password" placeholder="New password" minlength="12" required><input type="password" class="form-input min-w-0 flex-1 sm:min-w-32" name="password_confirmation" placeholder="Confirm" required><button class="btn-small text-teal-800">Reset</button></form>
                    </div></td>
                </tr>
            @empty<tr><td colspan="5" class="py-10 text-center text-slate-500">No clinic staff or doctor accounts have been created yet.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $clinicAccounts->links() }}</div>
    </section>
@endsection
