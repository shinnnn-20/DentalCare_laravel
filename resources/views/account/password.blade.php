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
                <div class="password-input-wrap">
                    <input class="form-input" type="password" name="current_password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </label>
            <label class="form-label">New password
                <div class="password-input-wrap">
                    <input class="form-input" type="password" name="password" autocomplete="new-password" minlength="8" title="At least 8 characters, one capital letter, and one special character" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <span class="text-xs font-normal text-slate-500">At least 8 characters, one capital letter, and one special character.</span>
            </label>
            <label class="form-label">Confirm new password
                <div class="password-input-wrap">
                    <input class="form-input" type="password" name="password_confirmation" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </label>
            <button class="btn-primary justify-self-start">Change password</button>
        </form>
    </section>
@endsection
