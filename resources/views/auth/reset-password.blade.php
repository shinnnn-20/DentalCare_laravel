@extends('layouts.app')

@section('title', 'Choose a new password')

@section('content')
    <section class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10">
        <div class="mb-8">
            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('login') }}">← Sign in</a>
            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-teal-700">{{ config('clinic.name') }}</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Choose a new password</h1>
            <p class="mt-2 text-slate-500">Create a new password for your account.</p>
        </div>
        <form method="POST" action="{{ route('password.update') }}" class="grid gap-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="form-label">Email address
                <input class="form-input" type="email" name="email" value="{{ old('email', $email) }}" maxlength="255" autocomplete="email" required>
                @error('email')<span class="text-xs font-normal text-rose-700">{{ $message }}</span>@enderror
            </label>
            <label class="form-label">New password
                <div class="password-input-wrap">
                    <input class="form-input" type="password" name="password" minlength="8" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                @error('password')<span class="text-xs font-normal text-rose-700">{{ $message }}</span>@enderror
                <span class="text-xs font-normal text-slate-500">At least 8 characters, one capital letter, and one special character.</span>
            </label>
            <label class="form-label">Confirm new password
                <div class="password-input-wrap">
                    <input class="form-input" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </label>
            <button class="btn-primary w-full">Reset password</button>
        </form>
    </section>
@endsection
