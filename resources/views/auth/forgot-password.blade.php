@extends('layouts.app')

@section('title', 'Forgot password')

@section('content')
    <section class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10">
        <div class="mb-8">
            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('login') }}">← Sign in</a>
            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-teal-700">{{ config('clinic.name') }}</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Reset your password</h1>
            <p class="mt-2 text-slate-500">Enter your account email and we’ll send a password reset link if an account exists.</p>
        </div>
        @if (session('status'))
            <p class="mb-5 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="status">{{ session('status') }}</p>
        @endif
        <form method="POST" action="{{ route('password.email') }}" class="grid gap-5">
            @csrf
            <label class="form-label">Email address
                <input class="form-input" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required autofocus>
                @error('email')<span class="text-xs font-normal text-rose-700">{{ $message }}</span>@enderror
            </label>
            <button class="btn-primary w-full">Send reset link</button>
        </form>
    </section>
@endsection
