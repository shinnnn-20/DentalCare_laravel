@extends('layouts.app')

@section('title', 'Dental record')

@section('content')
    <div class="mb-6"><a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ auth()->user()->hasRole('doctor') ? route('clinic.queue.index') : route('appointments.index') }}">{{ auth()->user()->hasRole('doctor') ? '← Patient queue' : '← Appointments' }}</a></div>
    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-sm font-medium text-teal-700">{{ $appointment->patient->patient_number }} · {{ $appointment->starts_at->format('M j, Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold">Dental record for {{ $appointment->patient->user->name }}</h1>
        <p class="mt-2 text-sm text-slate-500">Appointment treatment(s): {{ $appointment->treatmentNames() }}</p>
        <form method="POST" action="{{ route('clinic.records.store', ['appointment' => $appointment->id, ...$appointmentListFilters]) }}" class="mt-6 grid gap-5" data-confirm="Save this dental record to the patient's clinical history?" data-confirm-title="Save dental record" data-confirm-accept="Save record">
            @csrf
            <label class="form-label">Treating dentist
                @if (auth()->user()->hasRole('doctor'))
                    <select class="form-input" aria-label="Treating dentist" disabled>
                        <option selected>{{ auth()->user()->name }}</option>
                    </select>
                @else
                    <select class="form-input" name="treating_dentist_id" required>
                        <option value="">Choose a dentist</option>
                        @foreach ($treatingDentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) old('treating_dentist_id') === (string) $dentist->id)>{{ $dentist->name }}</option>
                        @endforeach
                    </select>
                @endif
            </label>
            <label class="form-label">Diagnosis<textarea class="form-input" name="diagnosis" rows="3" required>{{ old('diagnosis') }}</textarea></label>
            <label class="form-label">Treatment<textarea class="form-input" name="treatment" rows="3" required>{{ old('treatment') }}</textarea></label>
            <label class="form-label">Prescription <span class="font-normal text-slate-400">(optional)</span><textarea class="form-input" name="prescription" rows="2">{{ old('prescription') }}</textarea></label>
            <label class="form-label">Notes <span class="font-normal text-slate-400">(optional)</span><textarea class="form-input" name="notes" rows="2">{{ old('notes') }}</textarea></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="form-label">Follow-up date <span class="font-normal text-slate-400">(optional)</span><input class="form-input" type="date" name="follow_up_date" min="{{ today()->toDateString() }}" value="{{ old('follow_up_date') }}"></label>
                <fieldset
                    class="min-w-0"
                    data-follow-up-time-picker
                    data-availability-url="{{ route('appointments.availability') }}"
                    data-branch-id="{{ $appointment->branch_id ?? '' }}"
                    data-exclude-appointment-id="{{ $appointment->id }}"
                >
                    <legend class="form-label">Follow-up time <span class="font-normal text-slate-400">(optional)</span></legend>
                    <div class="relative">
                        <div class="time-picker">
                            <button
                                class="time-select-trigger"
                                type="button"
                                aria-label="Select follow-up time"
                                aria-haspopup="listbox"
                                aria-expanded="false"
                                data-follow-up-time-trigger
                                disabled
                            >
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-teal-700" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="12" cy="12" r="8.5"></circle>
                                        <path stroke-linecap="round" d="M12 7v5l3 2"></path>
                                    </svg>
                                </span>
                                <span data-follow-up-time-label>Select time</span>
                            </button>
                            <input type="hidden" name="follow_up_time" value="{{ old('follow_up_time') }}" data-follow-up-time-value>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500" id="follow-up-time-help" data-follow-up-time-status role="status" aria-live="polite">Choose a follow-up date to see available times.</p>
                </fieldset>
            </div>
            <button class="btn-primary justify-self-start">Save dental record</button>
        </form>
    </section>
@endsection
