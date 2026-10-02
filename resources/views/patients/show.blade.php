@extends('layouts.app')

@section('title', 'Patient profile')

@section('content')
    <div class="mb-6"><a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.index') }}">← Patient directory</a></div>
    <section class="rounded-2xl bg-teal-800 p-6 text-white sm:p-8">
        <p class="text-sm font-medium text-teal-100">Patient profile · {{ $patient->patient_number }}</p>
        <div class="mt-3 flex flex-wrap items-start justify-between gap-5">
            <div><h1 class="text-3xl font-bold">{{ $patient->user->name }}</h1><p class="mt-2 text-teal-100">{{ $patient->user->email }} · {{ $patient->user->phone }}</p></div>
            <div class="rounded-xl bg-white/10 px-4 py-3"><p class="text-xs uppercase tracking-wide text-teal-100">RFID card</p><p class="mt-1 font-semibold">{{ $patient->rfidCard?->uid ?? 'Not assigned' }}</p></div>
        </div>
    </section>
    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_2fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Patient information</h2>
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
