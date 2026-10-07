@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <section class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <div class="mb-7">
            <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-700 text-xl font-bold text-white">D</div>
            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-teal-700">Patient email verification</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Verify Your Email</h1>
            <p class="mt-2 text-slate-500">We sent a 6-digit verification code to <span class="font-semibold text-slate-700">{{ $user->email }}</span>. Enter it below to verify your account.</p>
        </div>
        <form method="POST" action="{{ route('verification.verify') }}" class="grid gap-4">
            @csrf
            <label class="form-label">6-digit verification code
                <input class="form-input text-center text-xl tracking-[0.35em]" type="text" name="code" value="{{ old('code') }}" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" aria-describedby="code-help" required autofocus>
            </label>
            <p id="code-help" class="-mt-2 text-sm text-slate-500">The code expires after 15 minutes.</p>
            <button class="btn-primary w-full">Verify Email</button>
        </form>
        <div class="mt-6 border-t border-slate-100 pt-5 text-center">
            <p class="text-sm text-slate-500">Didn't receive the code?</p>
            <form method="POST" action="{{ route('verification.resend') }}" class="mt-3">
                @csrf
                <button class="btn-secondary w-full" @disabled($resendCooldown > 0)>
                    @if ($resendCooldown > 0)
                        Resend Code in <span data-resend-countdown="{{ $resendCooldown }}">{{ $resendCooldown }}</span>s
                    @else
                        Resend Code
                    @endif
                </button>
            </form>
        </div>
        <p class="mt-6 text-center text-sm text-slate-500">Already verified? <a class="font-semibold text-teal-800 hover:underline" href="{{ route('login') }}">Go to Login</a></p>
    </section>
    <script>
        const countdown = document.querySelector('[data-resend-countdown]');
        if (countdown) {
            const button = countdown.closest('button');
            let remaining = Number(countdown.dataset.resendCountdown);
            const timer = window.setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    window.clearInterval(timer);
                    button.disabled = false;
                    button.textContent = 'Resend Code';
                    return;
                }
                countdown.textContent = String(remaining);
            }, 1000);
        }
    </script>
@endsection
