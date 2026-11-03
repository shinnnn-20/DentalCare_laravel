@extends('layouts.app')

@section('title', 'Email verification')

@section('content')
    @php
        $messages = [
            \App\Enums\EmailVerificationResult::Verified->value => ['Email Verified Successfully!', 'Your account has been verified. You can now log in.'],
            \App\Enums\EmailVerificationResult::AlreadyVerified->value => ['Your email is already verified.', 'You can log in to your patient account.'],
        ];
        [$heading, $message] = $messages[$result->value];
    @endphp

    <section class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-10">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-teal-700 text-xl font-bold text-white" aria-hidden="true">✓</div>
        <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-teal-700">Patient email verification</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ $heading }}</h1>
        <p class="mt-3 text-slate-500">{{ $message }}</p>
        <div class="mt-7 grid gap-3 sm:grid-cols-2">
            <a class="btn-primary w-full sm:col-span-2" href="{{ route('login') }}">Go to Login</a>
        </div>
    </section>
@endsection
