@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <section class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10">
        <div class="mb-8">
            <div class="grid h-12 w-12 place-items-center rounded-2xl bg-teal-700 text-xl font-bold text-white">D</div>
            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-teal-700">Dental clinic portal</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Welcome back</h1>
            <p class="mt-2 text-slate-500">Sign in to continue to your clinic account.</p>
        </div>
        <form method="POST" action="{{ route('login.store') }}" class="grid gap-5">
            @csrf
            <label class="form-label">Email address
                <input class="form-input" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </label>
            <label class="form-label">Password
                <input class="form-input" type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="btn-primary w-full">Sign in</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500">New patient? <a class="font-semibold text-teal-800 hover:underline" href="{{ route('register') }}">Create an account</a></p>
    </section>
@endsection
