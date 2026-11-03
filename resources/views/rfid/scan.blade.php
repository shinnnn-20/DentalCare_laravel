@extends('layouts.app')

@section('title', 'RFID check-in')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Front desk</p><h1 class="mt-1 text-3xl font-bold tracking-tight">RFID check-in</h1><p class="mt-2 text-slate-500">Tap a card or enter its UID to identify the patient and see their upcoming appointments first, with dental records available separately.</p></div>
    <section class="max-w-6xl rounded-2xl border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('clinic.rfid.scan.submit') }}" class="flex flex-col gap-3 sm:flex-row">
            @csrf
            <input class="form-input flex-1 text-lg" name="uid" value="{{ old('uid', $uid ?? '') }}" placeholder="RFID UID" autocomplete="off" required autofocus>
            <button class="btn-primary">Identify patient</button>
        </form>
        @isset($uid)
            @if ($patient)
                <div class="mt-6 min-w-0 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Patient identified</p>
                            <h2 class="mt-2 text-xl font-bold">{{ $patient->user->name }}</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ $patient->patient_number }} · {{ $patient->user->phone }}</p>
                        </div>
                        <nav class="flex flex-wrap gap-2" aria-label="Patient information">
                            <a @class([
                                'rounded-lg px-4 py-2 text-sm font-semibold',
                                'bg-teal-800 text-white' => $activeSection === 'records',
                                'bg-white text-slate-700 hover:bg-teal-50' => $activeSection !== 'records',
                            ]) href="{{ route('clinic.rfid.scan', [...$paginationQuery, 'section' => 'records']) }}" @if ($activeSection === 'records') aria-current="page" @endif>
                                Dental records
                            </a>
                            <a @class([
                                'rounded-lg px-4 py-2 text-sm font-semibold',
                                'bg-teal-800 text-white' => $activeSection === 'appointments',
                                'bg-white text-slate-700 hover:bg-teal-50' => $activeSection !== 'appointments',
                            ]) href="{{ route('clinic.rfid.scan', [...$paginationQuery, 'section' => 'appointments']) }}" @if ($activeSection === 'appointments') aria-current="page" @endif>
                                Booked appointments
                            </a>
                        </nav>
                    </div>
                    @if ($activeSection === 'appointments')
                        <section class="mt-5 grid min-w-0 gap-4 rounded-xl border border-teal-200 bg-white p-5 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center" aria-labelledby="next-appointment-heading">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-start gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Next appointment</p>
                                        <h3 id="next-appointment-heading" class="mt-1 text-lg font-bold text-slate-900">
                                            @if ($nextAppointment)
                                                {{ $nextAppointment->treatmentNames() }}
                                            @else
                                                No upcoming appointments
                                            @endif
                                        </h3>
                                    </div>
                                    @if ($nextAppointment)
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold capitalize text-amber-900">
                                            {{ str_replace('_', ' ', $nextAppointment->status) }}
                                        </span>
                                    @endif
                                </div>
                                @if ($nextAppointment)
                                    <time class="mt-3 block text-base font-semibold text-slate-800" datetime="{{ $nextAppointment->starts_at->toIso8601String() }}">
                                        {{ $nextAppointment->starts_at->format('M j, Y g:i A') }}
                                    </time>
                                    <p class="mt-2 text-sm text-slate-600">Branch: {{ $nextAppointment->branch?->name ?? 'Branch not assigned' }}</p>
                                @else
                                    <p class="mt-2 text-sm text-slate-600">There is no future appointment scheduled for this patient.</p>
                                @endif
                            </div>
                            @if (
                                $nextAppointment
                                && $nextAppointment->status === 'approved'
                                && $nextAppointment->starts_at->toDateString() >= today(config('clinic.timezone'))->toDateString()
                                && $nextAppointment->queueEntry === null
                            )
                                <form class="sm:justify-self-end"                                 method="POST" action="{{ route('clinic.queue.check-in', $nextAppointment) }}">
                                    @csrf
                                    <button class="btn-primary w-full sm:w-auto">Check in</button>
                                </form>
                            @endif
                        </section>
                    @endif
                    @if ($activeSection === 'records')
                    <h3 class="mt-5 text-sm font-semibold">Dental records</h3>
                    <form method="GET" action="{{ route('clinic.rfid.scan') }}" class="mt-3 grid gap-3 rounded-xl border border-emerald-100 bg-white/70 p-4 sm:grid-cols-2">
                        <input type="hidden" name="uid" value="{{ $uid }}">
                        <input type="hidden" name="section" value="records">
                        <input type="hidden" name="appointment_search" value="{{ $filters['appointment_search'] ?? '' }}">
                        <input type="hidden" name="appointment_status" value="{{ $filters['appointment_status'] ?? '' }}">
                        <input type="hidden" name="appointment_from" value="{{ $filters['appointment_from'] ?? '' }}">
                        <input type="hidden" name="appointment_until" value="{{ $filters['appointment_until'] ?? '' }}">
                        <input type="hidden" name="appointments_page" value="{{ request('appointments_page', 1) }}">
                        <label class="form-label sm:col-span-2">Search by service or treatment performed
                            <input class="form-input" type="search" name="record_search" value="{{ $filters['record_search'] ?? '' }}" maxlength="100" placeholder="Service, add-on service, or treatment">
                        </label>
                        <label class="form-label">From date
                            <input class="form-input date-time-input" type="date" name="record_from" value="{{ $filters['record_from'] ?? '' }}">
                        </label>
                        <label class="form-label">To date
                            <input class="form-input date-time-input" type="date" name="record_until" value="{{ $filters['record_until'] ?? '' }}">
                        </label>
                        <div class="flex flex-wrap items-end gap-3 sm:col-span-2">
                            <button class="btn-secondary">Filter records</button>
                            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.rfid.scan', array_filter([
                                'uid' => $uid,
                                'appointment_search' => $filters['appointment_search'] ?? null,
                                'appointment_status' => $filters['appointment_status'] ?? null,
                                'appointment_from' => $filters['appointment_from'] ?? null,
                                'appointment_until' => $filters['appointment_until'] ?? null,
                                'section' => 'records',
                            ], fn ($value) => $value !== null && $value !== '')) }}">Clear record filters</a>
                        </div>
                    </form>
                    <div class="mt-2 grid gap-2">
                        @forelse ($dentalRecords as $record)
                            <div class="rounded-xl bg-white p-3">
                                <p class="text-xs text-slate-500">{{ $record->created_at->format('M j, Y') }}</p>
                                <p class="mt-1 font-medium">Diagnosis: {{ $record->diagnosis }}</p>
                                <p class="mt-1 text-sm text-slate-600">Treatment: {{ $record->treatment }}</p>
                                @if ($record->appointment)
                                    <p class="mt-1 text-sm text-slate-600">Services performed: {{ $record->appointment->treatmentNames() }}</p>
                                @endif
                                @if ($record->prescription)
                                    <p class="mt-1 text-sm text-slate-600">Prescription: {{ $record->prescription }}</p>
                                @endif
                                @if ($record->follow_up_date)
                                    <p class="mt-1 text-sm text-slate-600">Follow-up: {{ $record->follow_up_date->format('M j, Y') }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-xl bg-white p-3 text-sm text-slate-600">{{ ! empty($filters['record_search']) || ! empty($filters['record_from']) || ! empty($filters['record_until']) ? 'No dental records match these filters.' : 'No dental records found for this patient.' }}</p>
                        @endforelse
                    </div>
                    @if ($dentalRecords->hasPages())
                        <div class="mt-3">{{ $dentalRecords->links() }}</div>
                    @endif
                    @else
                    <h3 class="mt-5 text-sm font-semibold">Booked appointments</h3>
                    <p class="mt-1 text-sm text-slate-600">Upcoming appointments appear first, followed by appointment history. Use the date range to narrow the list.</p>
                    <form method="GET" action="{{ route('clinic.rfid.scan') }}" class="mt-3 grid gap-3 rounded-xl border border-emerald-100 bg-white/70 p-4 sm:grid-cols-2">
                        <input type="hidden" name="uid" value="{{ $uid }}">
                        <input type="hidden" name="section" value="appointments">
                        <input type="hidden" name="record_search" value="{{ $filters['record_search'] ?? '' }}">
                        <input type="hidden" name="record_from" value="{{ $filters['record_from'] ?? '' }}">
                        <input type="hidden" name="record_until" value="{{ $filters['record_until'] ?? '' }}">
                        <input type="hidden" name="records_page" value="{{ request('records_page', 1) }}">
                        <label class="form-label">Search service or branch
                            <input class="form-input" type="search" name="appointment_search" value="{{ $filters['appointment_search'] ?? '' }}" maxlength="100" placeholder="Service or branch name">
                        </label>
                        <label class="form-label">Status
                            <select class="form-input" name="appointment_status">
                                <option value="">All statuses</option>
                                @foreach (['pending', 'approved', 'rejected', 'checked_in', 'waiting', 'in_consultation', 'billed', 'completed', 'cancelled', 'no_show'] as $status)
                                    <option value="{{ $status }}" @selected(($filters['appointment_status'] ?? '') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-label">From date
                            <input class="form-input date-time-input" type="date" name="appointment_from" value="{{ $filters['appointment_from'] ?? '' }}">
                        </label>
                        <label class="form-label">To date
                            <input class="form-input date-time-input" type="date" name="appointment_until" value="{{ $filters['appointment_until'] ?? '' }}">
                        </label>
                        <div class="flex flex-wrap items-end gap-3 sm:col-span-2">
                            <button class="btn-secondary">Filter appointments</button>
                            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.rfid.scan', array_filter([
                                'uid' => $uid,
                                'record_search' => $filters['record_search'] ?? null,
                                'record_from' => $filters['record_from'] ?? null,
                                'record_until' => $filters['record_until'] ?? null,
                                'section' => 'appointments',
                            ], fn ($value) => $value !== null && $value !== '')) }}">Clear appointment filters</a>
                        </div>
                    </form>
                    <div class="mt-2 grid gap-2">
                        @forelse ($appointments as $appointment)
                            <div class="flex flex-col gap-3 rounded-xl bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium">{{ $appointment->starts_at->format('M j, Y g:i A') }} · {{ $appointment->treatmentNames() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Branch: {{ $appointment->branch?->name ?? 'Branch not assigned' }}</p>
                                    <p class="mt-1 text-xs capitalize text-slate-500">Status: {{ str_replace('_', ' ', $appointment->status) }}</p>
                                </div>
                                @if (
                                    (! $nextAppointment || $appointment->id !== $nextAppointment->id)
                                    &&
                                    $appointment->status === 'approved'
                                    && $appointment->starts_at->toDateString() >= today(config('clinic.timezone'))->toDateString()
                                    && $appointment->queueEntry === null
                                )
                                    <form method="POST" action="{{ route('clinic.queue.check-in', $appointment) }}">
                                        @csrf
                                        <button class="btn-primary w-full sm:w-auto">Check in</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-xl bg-white p-3 text-sm text-slate-600">{{ ! empty($filters['appointment_search']) || ! empty($filters['appointment_status']) || ! empty($filters['appointment_from']) || ! empty($filters['appointment_until']) ? 'No booked appointments match these filters.' : 'No booked appointments found for this patient.' }}</p>
                        @endforelse
                    </div>
                    @if ($appointments->hasPages())
                        <div class="mt-3">{{ $appointments->links() }}</div>
                    @endif
                    @endif
                    <p class="mt-3 text-sm text-slate-600">Use Check in on an approved appointment to add the patient to the clinic queue.</p>
                    @if (auth()->user()->hasRole('admin', 'staff'))
                        <a class="mt-4 inline-flex text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $patient) }}">Open patient profile →</a>
                    @endif
                </div>
            @else
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900" role="alert">
                    <p class="font-semibold">{{ $scanMessage }}</p>
                    <p class="mt-1">UID: <strong>{{ $uid }}</strong></p>
                    @if ($cardStatus === 'not_registered')
                        <p class="mt-1">Verify the UID or assign the card to a patient.</p>
                    @endif
                </div>
            @endif
        @endisset
    </section>
@endsection
