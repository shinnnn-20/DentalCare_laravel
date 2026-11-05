@extends('layouts.app')

@section('title', 'Change password')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">Account security</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">Change password</h1>
        <p class="mt-2 text-slate-500">Enter your current password, then choose a new password for your account.</p>
    </div>

    <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-5">
        <form method="POST" action="{{ route('account.password.update') }}" class="grid gap-4" data-confirm="Change your account password?" data-confirm-title="Confirm password change" data-confirm-accept="Change password">
            @csrf
            @method('PUT')
            <label class="form-label">Current password
                <input class="form-input" type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label class="form-label">New password
                <input class="form-input" type="password" name="password" autocomplete="new-password" minlength="8" title="At least 8 characters, one capital letter, and one special character" required>
                <span class="text-xs font-normal text-slate-500">At least 8 characters, one capital letter, and one special character.</span>
            </label>
            <label class="form-label">Confirm new password
                <input class="form-input" type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>
            <button class="btn-primary justify-self-start">Change password</button>
        </form>
    </section>
@endsection
