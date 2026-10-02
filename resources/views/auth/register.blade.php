@extends('layouts.app')

@section('title', 'Patient registration')

@section('content')
    <section class="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-10">
        <div class="mb-8">
            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('login') }}">← Sign in</a>
            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-teal-700">Patient registration</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Create your account</h1>
            <p class="mt-2 text-slate-500">Your account is for patient access only. Clinic staff accounts are created by the clinic administrator.</p>
        </div>
        <form method="POST" action="{{ route('register.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf
            <label class="form-label sm:col-span-2">Full name<input class="form-input" name="name" value="{{ old('name') }}" required autocomplete="name"></label>
            <label class="form-label">Date of birth<input class="form-input" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required></label>
            <label class="form-label">Gender
                <select class="form-input" name="gender" required>
                    <option value="">Select</option>
                    @foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">Contact number<input class="form-input" name="phone" value="{{ old('phone') }}" required autocomplete="tel"></label>
            <label class="form-label">Email<input class="form-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <label class="form-label sm:col-span-2">Address<textarea class="form-input" name="address" rows="2" required>{{ old('address') }}</textarea></label>
            <label class="form-label sm:col-span-2">Emergency contact <span class="font-normal text-slate-400">(optional)</span><input class="form-input" name="emergency_contact" value="{{ old('emergency_contact') }}"></label>
            <label class="form-label">Password<input class="form-input" type="password" name="password" minlength="12" required autocomplete="new-password"><span class="text-xs font-normal text-slate-500">At least 12 characters.</span></label>
            <label class="form-label">Confirm password<input class="form-input" type="password" name="password_confirmation" required autocomplete="new-password"></label>
            <button class="btn-primary sm:col-span-2">Create patient account</button>
        </form>
    </section>
@endsection
