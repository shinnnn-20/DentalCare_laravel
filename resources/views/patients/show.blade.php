@extends('layouts.app')

@section('title', 'Patient profile')

@section('content')
    <div class="mb-6"><a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.index') }}">← Patient directory</a></div>
    <section class="rounded-2xl bg-teal-800 p-6 text-white sm:p-8">
        <p class="text-sm font-medium text-teal-100">Patient profile · {{ $patient->patient_number }}</p>
        <div class="mt-3 flex flex-wrap items-start justify-between gap-5">
            <div>
                <h1 class="text-3xl font-bold">{{ $patient->user->name }}</h1>
                <p class="mt-2 text-teal-100">{{ $patient->user->email }} · {{ $patient->user->phone ?? 'No phone number' }}</p>
                @unless ($patient->user->is_active)
                    <span class="mt-3 inline-flex rounded-full bg-white/15 px-3 py-1 text-sm font-semibold">Access removed</span>
                @endunless
            </div>
            <div class="rounded-xl bg-white/10 px-4 py-3"><p class="text-xs uppercase tracking-wide text-teal-100">RFID card</p><p class="mt-1 font-semibold">{{ $patient->rfidCard?->uid ?? 'Not assigned' }}</p></div>
        </div>
    </section>
    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_2fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Patient information</h2>
            @if ($patient->user->is_active)
                <form method="POST" action="{{ route('clinic.patients.update', $patient) }}" class="mt-4 grid gap-3">
                @csrf @method('PUT')
                <label class="form-label">Full name<input class="form-input" name="name" value="{{ $patient->user->name }}" required></label>
                <label class="form-label">Email<input class="form-input" type="email" name="email" value="{{ $patient->user->email }}" required></label>
                <label class="form-label">Phone<input class="form-input" name="phone" value="{{ $patient->user->phone }}" required></label>
                <label class="form-label">Date of birth<input class="form-input" type="date" name="date_of_birth" value="{{ $patient->date_of_birth?->format('Y-m-d') }}" required></label>
                <label class="form-label">Gender<select class="form-input" name="gender" required>@foreach (['female', 'male', 'other', 'prefer_not_to_say'] as $gender)<option value="{{ $gender }}" @selected($patient->gender === $gender)>{{ str_replace('_', ' ', ucfirst($gender)) }}</option>@endforeach</select></label>
                <label class="form-label">Address<textarea class="form-input" name="address" rows="2" required>{{ $patient->address }}</textarea></label>
                <label class="form-label">Emergency contact<input class="form-input" name="emergency_contact" value="{{ $patient->emergency_contact }}"></label>
                <button class="btn-secondary justify-self-start">Save patient details</button>
                </form>
                @if (auth()->user()->hasRole('admin'))
                    <form method="POST" action="{{ route('clinic.patients.password', $patient) }}" class="mt-6 grid gap-3 border-t border-slate-100 pt-5">
                        @csrf @method('PUT')
                        <h3 class="font-semibold">Reset patient password</h3>
                        <p class="text-sm text-slate-500">Choose a new password of at least 12 characters and share it with the patient securely.</p>
                        <label class="form-label">New password<input class="form-input" type="password" name="password" minlength="12" autocomplete="new-password" required></label>
                        <label class="form-label">Confirm new password<input class="form-input" type="password" name="password_confirmation" autocomplete="new-password" required></label>
                        <button class="btn-secondary justify-self-start">Reset password</button>
                    </form>
                @endif
            @endif
            @if ($patient->user->is_active)
                <form method="POST" action="{{ route('clinic.patients.destroy', $patient) }}" class="mt-6 grid gap-3 border-t border-rose-100 pt-5">
                    @csrf @method('DELETE')
                    <h3 class="font-semibold text-rose-800">Remove patient access</h3>
                    <p class="text-sm text-slate-600">This disables the account, clears contact and demographic details, and signs out existing sessions. Appointments, dental records, and billing history will be retained. The patient number remains to link those records.</p>
                    <label class="form-label">Type <span class="font-semibold">{{ $patient->patient_number }}</span> to confirm
                        <input class="form-input" name="confirmation" autocomplete="off" required>
                    </label>
                    @error('confirmation')
                        <p class="text-sm text-rose-700" role="alert">{{ $message }}</p>
                    @enderror
                    <button class="justify-self-start rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">Remove access and pseudonymize</button>
                </form>
            @endif
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white">
            <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Appointment history</h2>
            <div class="divide-y divide-slate-100">
                @forelse ($patient->appointments as $appointment)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div><p class="font-medium">{{ $appointment->service->name }}</p><p class="mt-1 text-sm text-slate-500">{{ $appointment->starts_at->format('M j, Y · g:i A') }} · {{ str_replace('_', ' ', $appointment->type) }}</p></div>
                        <span class="status-badge capitalize">{{ str_replace('_', ' ', $appointment->status) }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No appointments recorded.</p>
                @endforelse
            </div>
        </section>
    </div>
    <section class="mt-5 rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Dental history</h2>
        <div class="divide-y divide-slate-100">
            @forelse ($patient->dentalRecords as $record)
                <article class="grid gap-2 px-5 py-4 sm:grid-cols-[150px_1fr]">
                    <p class="text-sm text-slate-500">{{ $record->created_at->format('M j, Y') }}</p>
                    <div><h3 class="font-semibold">{{ $record->treatment }}</h3><p class="mt-1 text-sm"><span class="font-medium">Diagnosis:</span> {{ $record->diagnosis }}</p>@if ($record->prescription)<p class="mt-1 text-sm text-slate-600"><span class="font-medium">Prescription:</span> {{ $record->prescription }}</p>@endif</div>
                </article>
            @empty
                <p class="px-5 py-8 text-sm text-slate-500">No dental records available.</p>
            @endforelse
        </div>
    </section>
@endsection
