@extends('layouts.app')

@section('title', 'Dental record')

@section('content')
    <div class="mb-6"><a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('appointments.index') }}">← Appointments</a></div>
    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-sm font-medium text-teal-700">{{ $appointment->patient->patient_number }} · {{ $appointment->starts_at->format('M j, Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold">Dental record for {{ $appointment->patient->user->name }}</h1>
        <p class="mt-2 text-sm text-slate-500">Appointment service: {{ $appointment->service->name }}</p>
        <form method="POST" action="{{ route('clinic.records.store', $appointment) }}" class="mt-6 grid gap-5">
            @csrf
            <label class="form-label">Diagnosis<textarea class="form-input" name="diagnosis" rows="3" required>{{ old('diagnosis') }}</textarea></label>
            <label class="form-label">Treatment<textarea class="form-input" name="treatment" rows="3" required>{{ old('treatment') }}</textarea></label>
            <label class="form-label">Prescription <span class="font-normal text-slate-400">(optional)</span><textarea class="form-input" name="prescription" rows="2">{{ old('prescription') }}</textarea></label>
            <label class="form-label">Notes <span class="font-normal text-slate-400">(optional)</span><textarea class="form-input" name="notes" rows="2">{{ old('notes') }}</textarea></label>
            <label class="form-label">Follow-up date <span class="font-normal text-slate-400">(optional)</span><input class="form-input max-w-xs" type="date" name="follow_up_date" min="{{ today()->toDateString() }}" value="{{ old('follow_up_date') }}"></label>
            <button class="btn-primary justify-self-start">Save dental record</button>
        </form>
    </section>
@endsection
