@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">{{ auth()->user()->role === 'patient' ? 'Patient portal' : 'Clinic operations' }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Appointments</h1>
        <p class="mt-2 text-slate-500">{{ auth()->user()->role === 'patient' ? 'Request an appointment and track its approval status.' : 'Review online bookings, add walk-ins, and manage the clinic schedule.' }}</p>
    </div>

    @if (auth()->user()->role === 'patient' || auth()->user()->hasRole('admin', 'staff'))
        <section class="mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-gradient-to-r from-teal-50 via-white to-white px-5 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-teal-700 text-lg font-bold text-white" aria-hidden="true">+</span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ auth()->user()->role === 'patient' ? 'Request an appointment' : 'Register a walk-in' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->role === 'patient' ? 'Choose a service, then pick an available date and time. Requests require clinic approval.' : 'Choose a patient, service, date, and available time. Walk-ins join the waiting queue immediately.' }}</p>
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('appointments.store') }}" class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7 xl:grid-cols-12" data-appointment-schedule-form data-availability-url="{{ route('appointments.availability') }}" data-current-month="{{ today(config('clinic.timezone'))->format('Y-m') }}" @if (auth()->user()->role === 'patient') data-patient-booking @endif>
                @csrf
                <label class="form-label sm:col-span-2 xl:col-span-4">Branch
                    <select class="form-input" name="branch_id" data-branch-select required>
                        <option value="">Choose a branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
                @if (auth()->user()->hasRole('admin', 'staff'))
                    <label class="form-label sm:col-span-2 xl:col-span-4">Patient
                        <select name="patient_id" class="form-input" required>
                            <option value="">Choose a patient</option>
                            @foreach ($patients as $patient)
                                <option value="{{ $patient->id }}" @selected(old('patient_id') == $patient->id)>{{ $patient->patient_number }} · {{ $patient->user->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <fieldset class="grid min-w-0 gap-4 rounded-2xl border border-teal-100 bg-gradient-to-br from-teal-50/70 via-white to-white p-4 sm:col-span-2 sm:p-5 xl:col-span-12">
                    <legend class="sr-only">Select Dental Treatment</legend>
                    <div class="flex items-start gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-700 text-white shadow-sm" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5c-3.3-2-7.5-.1-7.5 4.3 0 3.4 1.6 5.3 2.3 8.3.5 2.2 1.1 4.4 2.5 4.4 1.2 0 1.1-2.3 2.7-2.3s1.5 2.3 2.7 2.3c1.4 0 2-2.2 2.5-4.4.7-3 2.3-4.9 2.3-8.3 0-4.4-4.2-6.3-7.5-4.3Z"></path>
                                <path stroke-linecap="round" d="M9 8.5c.5-.8 1.5-1.2 2.5-1.2"></path>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Select Dental Treatment</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Choose a service to view its fee and appointment details.</p>
                        </div>
                    </div>
                    <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($services->groupBy('category') as $category => $categoryServices)
                            <label class="grid min-w-0 content-start gap-2 rounded-xl border border-slate-200 bg-white p-3 text-sm font-semibold text-slate-700 shadow-sm transition-colors sm:p-3.5" data-service-category-card>
                                <span class="flex min-h-10 items-center justify-between gap-2">
                                    <span>{{ $category ?: 'Other Services' }}</span>
                                    <span class="hidden shrink-0 rounded-full bg-teal-700 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-wide text-white shadow-sm" data-category-active></span>
                                </span>
                                <span class="relative block">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-teal-700" aria-hidden="true">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" d="M9 6h11M9 12h11M9 18h11"></path>
                                            <circle cx="4.5" cy="6" r=".75" fill="currentColor" stroke="none"></circle>
                                            <circle cx="4.5" cy="12" r=".75" fill="currentColor" stroke="none"></circle>
                                            <circle cx="4.5" cy="18" r=".75" fill="currentColor" stroke="none"></circle>
                                        </svg>
                                    </span>
                                    <select
                                        class="form-input min-h-12 appearance-none border-slate-200 bg-white pl-11 pr-11 font-medium shadow-sm hover:border-teal-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-100/80"
                                        data-service-category
                                        aria-label="{{ $category ?: 'Other Services' }}"
                                        @if ($loop->first) name="service_id" required @endif
                                    >
                                        <option value="">Choose a treatment...</option>
                                        @foreach ($categoryServices as $service)
                                            @php($displayPrice = $service->requires_price_confirmation && $service->price_display ? $service->price_display : config('clinic.currency_symbol').number_format((float) $service->price, 2))
                                            <option value="{{ $service->id }}" data-service-name="{{ $service->name }}" data-price="{{ $displayPrice }}" data-price-display="{{ $service->price_display }}" data-raw-price="{{ (float) $service->price }}" data-requires-confirmation="{{ $service->requires_price_confirmation ? 'true' : 'false' }}" data-category="{{ $category ?: 'Other Services' }}" data-duration="{{ (int) $service->duration_minutes }}" @selected(old('service_id') == $service->id || in_array($service->id, (array) old('service_ids', [])))>{{ $service->name }} — {{ $displayPrice }}</option>
                                        @endforeach
                                    </select>
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400" aria-hidden="true">
                                        <svg class="h-5 w-5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m7 10 5 5 5-5"></path>
                                        </svg>
                                    </span>
                                </span>
                                <span class="hidden rounded-lg border border-teal-300 bg-teal-50 p-2.5 text-teal-950 shadow-inner" data-category-selections></span>
                            </label>
                        @endforeach
                    </div>
                    <p class="max-w-3xl text-xs leading-5 text-slate-500">Reference fees are transcribed from the supplied chart list and have not been verified against the clinic's approved fee schedule. They are estimates only; clinic staff must confirm the final amount before billing.</p>
                </fieldset>
                <div class="sm:col-span-2 xl:col-span-12 rounded-2xl border border-teal-200 bg-gradient-to-br from-teal-50/60 via-white to-white p-5 shadow-sm" data-appointment-summary-box>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-teal-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-teal-700 text-white text-sm" aria-hidden="true">✓</span>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">Appointment Summary &amp; Estimated Cost</h4>
                                <p class="text-xs text-slate-500">Selected treatments for this visit. Add treatments from categories above or remove them below.</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-800" data-selected-count-badge>0 treatments selected</span>
                    </div>

                    <div class="mt-3 divide-y divide-slate-100 min-h-10" data-selected-treatments-list>
                        <p class="py-3 text-xs text-slate-500 italic" data-no-treatments-message>No treatments selected yet. Choose one or more treatments from the categories above.</p>
                    </div>

                    <div data-service-ids-container></div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-teal-100 pt-4">
                        <div>
                            <p class="text-[0.7rem] uppercase tracking-wider font-semibold text-slate-400">Combined Estimated Total</p>
                            <p class="text-2xl font-black text-teal-900" data-combined-estimated-cost>₱0.00</p>
                            <p class="text-xs text-slate-500 font-medium" data-combined-breakdown></p>
                        </div>
                        <p class="text-xs text-slate-400 max-w-sm">Prices shown are estimates based on standard clinic rates or reference fees. Staff will confirm final prices before billing.</p>
                    </div>
                </div>
                <div class="grid min-w-0 content-start gap-2 sm:col-span-2 xl:col-span-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Appointment date</p>
                        <p class="mt-1 text-xs text-slate-500">{{ auth()->user()->role === 'patient' ? 'Choose a highlighted day with open slots.' : 'Choose a highlighted day to see available appointment times.' }}</p>
                    </div>
                    <input type="hidden" name="appointment_date" value="{{ old('appointment_date') }}" data-appointment-date>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 sm:p-4" data-availability-calendar>
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <button class="calendar-nav-button" type="button" data-calendar-previous aria-label="Previous month">←</button>
                            <p class="text-sm font-bold text-slate-900 sm:text-base" data-calendar-month></p>
                            <button class="calendar-nav-button" type="button" data-calendar-next aria-label="Next month">→</button>
                        </div>
                        <div class="mb-2 grid grid-cols-7 text-center text-[0.7rem] font-bold uppercase tracking-wide text-slate-400" aria-hidden="true">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1.5" data-calendar-days role="group" aria-label="Available appointment dates"></div>
                        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-200 pt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-teal-600"></span>Available</span>
                            <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>Unavailable</span>
                        </div>
                    </div>
                </div>
                @if (auth()->user()->role === 'patient')
                    <div class="form-label sm:col-span-2 xl:col-span-4">Appointment Time
                        <span class="relative block">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-teal-700" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="12" cy="12" r="8.5"></circle>
                                    <path stroke-linecap="round" d="M12 7v5l3 2"></path>
                                </svg>
                            </span>
                            <select class="form-input date-time-input time-select pl-10 pr-10" name="appointment_time" data-appointment-time required disabled>
                                <option value="">Select time</option>
                                @foreach ($appointmentTimes as $timeOption)
                                    <option value="{{ $timeOption }}" @selected(old('appointment_time') === $timeOption)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $timeOption)->format('g:i A') }}</option>
                                @endforeach
                            </select>
                        </span>
                        <span class="text-xs font-normal text-slate-500">Select a branch and date to see available 30-minute slots. Clinic hours are 10:00 AM–5:00 PM, with lunch from 12:00 PM–1:00 PM.</span>
                    </div>
                @else
                    <div class="form-label sm:col-span-2 xl:col-span-4">Appointment Time
                        <span class="relative block">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-teal-700" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="12" cy="12" r="8.5"></circle>
                                    <path stroke-linecap="round" d="M12 7v5l3 2"></path>
                                </svg>
                            </span>
                            <select class="form-input date-time-input time-select pl-10 pr-10" name="appointment_time" data-appointment-time disabled>
                                <option value="">Select time</option>
                                @foreach ($appointmentTimes as $timeOption)
                                    <option value="{{ $timeOption }}" @selected(old('appointment_time') === $timeOption)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $timeOption)->format('g:i A') }}</option>
                                @endforeach
                            </select>
                        </span>
                        <span class="text-xs font-normal text-slate-500">Available times are specific to the selected branch. Clinic hours are 10:00 AM–5:00 PM, with lunch from 12:00 PM–1:00 PM.</span>
                    </div>
                @endif
                <p class="hidden rounded-xl bg-teal-50 px-4 py-3 text-sm font-medium text-teal-900 sm:col-span-2 xl:col-span-12" data-availability-message role="status" aria-live="polite"></p>
                <p class="hidden rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 sm:col-span-2 xl:col-span-12" data-sunday-error role="alert">The Dental Clinic is closed on Sundays. Please select another date.</p>
                <label class="form-label sm:col-span-2 xl:col-span-12">Notes <span class="font-normal text-slate-400">(optional)</span><input class="form-input" name="notes" maxlength="2000" placeholder="Reason for visit"></label>
                <div class="sm:col-span-2 xl:col-span-12">
                    <button class="btn-primary w-full sm:w-auto">{{ auth()->user()->role === 'patient' ? 'Submit appointment request' : 'Add walk-in to queue' }}</button>
                </div>
            </form>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white">
        <form method="GET" class="grid gap-3 border-b border-slate-100 bg-slate-50/60 p-4 sm:flex sm:flex-wrap sm:items-end sm:p-5">
            <label class="form-label sm:min-w-48">Filter by Branch
                <select class="form-input" name="branch_id">
                    <option value="">All Branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                    @if ($hasUnassignedAppointments)
                        <option value="unassigned" @selected(request('branch_id') === 'unassigned')>Not Assigned</option>
                    @endif
                </select>
            </label>
            @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                <label class="form-label sm:min-w-48">Filter by date<input class="form-input date-time-input" type="date" name="date" value="{{ request('date') }}"></label>
            @endif
            <label class="form-label sm:min-w-48">Search
                <input class="form-input" type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Patient, service, or ID">
            </label>
            <label class="form-label sm:min-w-48">Status
                <select class="form-input" name="status"><option value="">All statuses</option>@foreach (['pending', 'approved', 'checked_in', 'waiting', 'in_consultation', 'billed', 'completed', 'cancelled', 'no_show'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>
            </label>
            <button class="btn-secondary self-end">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Date & time</th>@if (auth()->user()->hasRole('admin', 'staff', 'doctor'))<th>Patient</th>@endif<th>Branch</th><th>Service</th><th>Type</th><th>Status</th><th>Queue</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr id="appointment-{{ $appointment->id }}">
                        <td data-label="Date & time">
                            <span class="inline-flex flex-col gap-1 whitespace-nowrap">
                                <span class="font-semibold text-slate-800">{{ $appointment->starts_at->format('M j, Y') }}</span>
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500"><span aria-hidden="true">◷</span>{{ $appointment->starts_at->format('g:i A') }}</span>
                            </span>
                        </td>
                        @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                            <td data-label="Patient">
                                @if (auth()->user()->hasRole('admin', 'staff'))
                                    <a class="font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $appointment->patient) }}">{{ $appointment->patient->user->name }}</a>
                                @else
                                    <span class="font-semibold">{{ $appointment->patient->user->name }}</span>
                                @endif
                                <div class="text-xs text-slate-400">{{ $appointment->patient->patient_number }}</div>
                            </td>
                        @endif
                        <td data-label="Branch"><span class="inline-flex max-w-full rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $appointment->branch?->name ?? 'Not Assigned' }}</span></td>
                        <td data-label="Service">
                            @php($appointmentServices = $appointment->services->isNotEmpty() ? $appointment->services : ($appointment->service ? collect([$appointment->service]) : collect()))
                            <div class="flex flex-wrap gap-1">
                                @forelse ($appointmentServices as $apptService)
                                    <span class="inline-flex rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-900">{{ $apptService->name }}</span>
                                @empty
                                    <span class="text-xs text-slate-400">No services</span>
                                @endforelse
                            </div>
                        </td><td data-label="Type" class="capitalize">{{ str_replace('_', ' ', $appointment->type) }}</td>
                        <td data-label="Status"><span class="status-badge capitalize">{{ str_replace('_', ' ', $appointment->status) }}</span></td>
                        <td data-label="Queue">{{ $appointment->queueEntry?->queue_number ?? '—' }}</td>
                        <td data-label="Actions"><div class="flex flex-wrap gap-2">
                            @if (auth()->user()->role === 'patient' && in_array($appointment->status, ['pending', 'approved'], true))
                                <form method="POST" action="{{ route('appointments.transition', [$appointment, 'cancel']) }}">@csrf<button class="btn-small text-rose-700">Cancel</button></form>
                            @elseif (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                                @if (in_array($appointment->status, ['pending', 'approved'], true))
                                    @if (in_array($appointment->id, $reschedulableAppointmentIds, true))
                                        <details class="min-w-0">
                                            <summary class="btn-small cursor-pointer list-none text-teal-800">Reschedule</summary>
                                            <form method="POST" action="{{ route('clinic.appointments.reschedule', $appointment) }}" class="mt-2 flex min-w-0 flex-wrap gap-1" data-appointment-schedule-form data-availability-url="{{ route('appointments.availability') }}" data-current-month="{{ today(config('clinic.timezone'))->format('Y-m') }}" data-exclude-appointment-id="{{ $appointment->id }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="service_id" value="{{ $appointment->service_id }}">
                                                @if (auth()->user()->hasRole('admin', 'staff'))
                                                    <label class="form-label w-full">Branch
                                                        <select class="form-input" name="branch_id" data-branch-select required>
                                                            <option value="">Choose a branch</option>
                                                            @foreach ($branches as $branch)
                                                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $appointment->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                @else
                                                    <input type="hidden" name="branch_id" data-branch-select value="{{ $appointment->branch_id }}">
                                                @endif
                                                <input class="form-input date-time-input w-full min-w-0 sm:w-auto sm:min-w-40" type="date" name="appointment_date" min="{{ today(config('clinic.timezone'))->toDateString() }}" value="{{ old('appointment_date', $appointment->starts_at->format('Y-m-d')) }}" data-appointment-date required>
                                                <span class="relative block min-w-0 sm:w-44">
                                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-teal-700" aria-hidden="true">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                            <circle cx="12" cy="12" r="8.5"></circle>
                                                            <path stroke-linecap="round" d="M12 7v5l3 2"></path>
                                                        </svg>
                                                    </span>
                                                    <select class="form-input date-time-input time-select min-h-10 pl-9 pr-8 text-xs" name="appointment_time" data-appointment-time required disabled>
                                                    @php($currentTime = $appointment->starts_at->format('H:i'))
                                                    <option value="">Select time</option>
                                                    @foreach ($appointmentTimes as $timeOption)
                                                        <option value="{{ $timeOption }}" @selected(old('appointment_time', $currentTime) === $timeOption)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $timeOption)->format('g:i A') }}</option>
                                                    @endforeach
                                                    </select>
                                                </span>
                                                <button type="submit" class="btn-small text-teal-800">Save new time</button>
                                            </form>
                                            <p class="hidden mt-2 text-sm font-medium text-slate-600" data-availability-message role="status" aria-live="polite"></p>
                                            <p class="mt-2 text-xs text-slate-500">Choose a branch and 30-minute slot; clinic hours are 10:00 AM–5:00 PM, with lunch from 12:00 PM–1:00 PM.</p>
                                            <p class="hidden mt-2 text-sm font-medium text-rose-700" data-sunday-error role="alert">The Dental Clinic is closed on Sundays. Please select another date.</p>
                                        </details>
                                    @else
                                        @if (auth()->user()->hasRole('admin', 'staff'))
                                            @if ($appointment->starts_at->toDateString() < today(config('clinic.timezone'))->toDateString())
                                                <p class="text-xs font-medium text-slate-500">Past appointments cannot be rescheduled.</p>
                                            @else
                                                <p class="text-xs font-medium text-slate-500">Appointments cannot be rescheduled after their scheduled time.</p>
                                            @endif
                                        @else
                                            <p class="text-xs font-medium text-slate-500">Appointments must be rescheduled at least one calendar day in advance.</p>
                                        @endif
                                    @endif
                                @endif
                                @if (auth()->user()->hasRole('admin', 'staff'))
                                    @if ($appointment->status === 'pending')
                                        <form method="POST" action="{{ route('appointments.transition', [$appointment, 'approve']) }}">@csrf<button class="btn-small text-teal-800">Approve</button></form>
                                        <form method="POST" action="{{ route('appointments.transition', [$appointment, 'reject']) }}">@csrf<button class="btn-small text-rose-700">Reject</button></form>
                                    @elseif ($appointment->status === 'in_consultation')
                                        @if (auth()->user()->role === 'admin' && ! $appointment->dentalRecord)
                                            <a class="btn-small text-teal-800" href="{{ route('clinic.records.create', $appointment) }}">Dental record</a>
                                        @elseif ($appointment->dentalRecord && ! $appointment->bill)
                                            <a class="btn-small text-teal-800" href="{{ route('clinic.bills.create', $appointment) }}">Create bill</a>
                                        @endif
                                    @endif
                                    @if ($appointment->status === 'billed' && $appointment->bill && $appointment->bill->payment_status === 'paid')
                                        <form method="POST" action="{{ route('appointments.transition', [$appointment, 'complete']) }}">@csrf<button class="btn-small text-teal-800">Complete</button></form>
                                    @endif
                                    @if ($appointment->bill)
                                        <a class="btn-small text-teal-800" href="{{ route('clinic.billing.index', ['bill' => $appointment->bill->id]) }}#bill-{{ $appointment->bill->id }}">View bill</a>
                                    @endif
                                @endif
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->hasRole('admin', 'staff', 'doctor') ? 8 : 7 }}" class="py-10 text-center text-slate-500">No appointments match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $appointments->links() }}</div>
    </section>
    <script>
        document.querySelectorAll('[data-appointment-schedule-form]').forEach((form) => {
            const dateInput = form.querySelector('[data-appointment-date]');
            const timeSelect = form.querySelector('[data-appointment-time]');
            const branchSelect = form.querySelector('[data-branch-select]');
            const availabilityMessage = form.querySelector('[data-availability-message]');
            const calendar = form.querySelector('[data-availability-calendar]');
            const errorMessage = form.querySelector('[data-sunday-error]');
            const sundayMessage = 'The Dental Clinic is closed on Sundays. Please select another date.';
            const patientBooking = form.hasAttribute('data-patient-booking');
            const excludedAppointmentId = form.dataset.excludeAppointmentId;
            const serviceSelects = form.querySelectorAll('[data-service-category]');
            const timePicker = document.createElement('div');
            const timeTrigger = document.createElement('button');
            const timeMenu = document.createElement('div');
            const timeOptionsId = `appointment-time-options-${Math.random().toString(36).slice(2)}`;
            const timeIsRequired = timeSelect.required;
            const appointmentTimeOptions = Array.from(timeSelect.options)
                .filter((option) => option.value !== '')
                .map((option) => ({ value: option.value, label: option.textContent }));
            let availability = {};
            let currentMonth = dateInput.value
                ? dateInput.value.slice(0, 7)
                : form.dataset.currentMonth;
            let availabilityRequest = 0;
            let preferredTime = timeSelect.value;

            timePicker.className = 'time-picker';
            timeTrigger.type = 'button';
            timeTrigger.className = 'time-select-trigger';
            timeTrigger.setAttribute('role', 'combobox');
            timeTrigger.setAttribute('aria-label', 'Select appointment time');
            timeTrigger.setAttribute('aria-haspopup', 'listbox');
            timeTrigger.setAttribute('aria-expanded', 'false');
            timeTrigger.setAttribute('aria-controls', timeOptionsId);
            timeTrigger.setAttribute('aria-required', String(timeIsRequired));
            timeMenu.id = timeOptionsId;
            timeMenu.className = 'time-select-menu hidden';
            timeMenu.setAttribute('role', 'listbox');
            timeMenu.setAttribute('aria-label', 'Available appointment times');
            timeSelect.required = false;
            timeSelect.classList.add('time-select-native');
            timeSelect.tabIndex = -1;
            timeSelect.setAttribute('aria-hidden', 'true');
            timeSelect.parentNode.insertBefore(timePicker, timeSelect);
            timePicker.append(timeTrigger, timeSelect);
            document.body.append(timeMenu);

            const closeTimeMenu = (returnFocus = false) => {
                timeMenu.classList.add('hidden');
                timeMenu.classList.remove('time-select-menu-fixed');
                timeMenu.style.removeProperty('left');
                timeMenu.style.removeProperty('right');
                timeMenu.style.removeProperty('width');
                timeMenu.style.removeProperty('top');
                timeMenu.style.removeProperty('bottom');
                timeTrigger.setAttribute('aria-expanded', 'false');
                if (returnFocus) {
                    timeTrigger.focus();
                }
            };

            const renderTimePicker = () => {
                const selectedOption = timeSelect.selectedOptions[0];
                timeTrigger.textContent = selectedOption?.value
                    ? new Intl.DateTimeFormat(undefined, {
                        hour: 'numeric',
                        minute: '2-digit',
                    }).format(new Date(`2000-01-01T${selectedOption.value}:00`))
                    : 'Select time';
                timeTrigger.disabled = form.dataset.availabilityLoaded !== 'true'
                    || !dateInput.value
                    || timeSelect.options.length <= 1;
                timeTrigger.setAttribute('aria-disabled', String(timeTrigger.disabled));
                timeMenu.replaceChildren();

                Array.from(timeSelect.options).filter((option) => option.value !== '').forEach((option) => {
                    const item = document.createElement('div');
                    const timeLabel = document.createElement('span');
                    const statusLabel = document.createElement('span');
                    const isAvailable = option.dataset.available === 'true';
                    item.className = isAvailable
                        ? 'time-select-option'
                        : 'time-select-option time-select-option-unavailable';
                    timeLabel.textContent = option.dataset.timeLabel || option.textContent;
                    statusLabel.className = 'time-select-status';
                    statusLabel.textContent = option.dataset.status || 'Checking…';
                    item.append(timeLabel, statusLabel);
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', String(option.selected));
                    item.setAttribute('aria-disabled', String(option.disabled));
                    item.tabIndex = -1;
                    if (!option.disabled) {
                        item.addEventListener('click', () => {
                            timeSelect.value = option.value;
                            timeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            closeTimeMenu(true);
                        });
                    }
                    item.addEventListener('keydown', (event) => {
                        const options = Array.from(timeMenu.querySelectorAll('[role="option"]:not([aria-disabled="true"])'));
                        const index = options.indexOf(item);
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            options[(index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length]?.focus();
                        } else if (event.key === 'Home' || event.key === 'End') {
                            event.preventDefault();
                            options[event.key === 'Home' ? 0 : options.length - 1]?.focus();
                        } else if (event.key === 'Escape') {
                            event.preventDefault();
                            closeTimeMenu(true);
                        } else if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            item.click();
                        }
                    });
                    timeMenu.append(item);
                });
            };

            timeTrigger.addEventListener('click', () => {
                if (timeTrigger.disabled) {
                    return;
                }
                const isOpening = timeMenu.classList.contains('hidden');
                timeMenu.classList.toggle('hidden', !isOpening);
                timeTrigger.setAttribute('aria-expanded', String(isOpening));
                if (isOpening) {
                    const triggerBounds = timeTrigger.getBoundingClientRect();
                    const viewportPadding = 8;
                    const menuHeight = Math.min(timeMenu.scrollHeight, window.innerHeight * 0.4, 192);
                    const spaceBelow = window.innerHeight - triggerBounds.bottom;
                    const opensAbove = spaceBelow < menuHeight + viewportPadding && triggerBounds.top > spaceBelow;
                    const menuWidth = Math.min(triggerBounds.width, window.innerWidth - (viewportPadding * 2));
                    const menuLeft = Math.max(
                        viewportPadding,
                        Math.min(triggerBounds.left, window.innerWidth - menuWidth - viewportPadding),
                    );
                    timeMenu.classList.add('time-select-menu-fixed');
                    timeMenu.style.left = `${menuLeft}px`;
                    timeMenu.style.right = 'auto';
                    timeMenu.style.width = `${menuWidth}px`;
                    timeMenu.style.top = opensAbove ? 'auto' : `${triggerBounds.bottom + 4}px`;
                    timeMenu.style.bottom = opensAbove
                        ? `${window.innerHeight - triggerBounds.top + 4}px`
                        : 'auto';
                    (timeMenu.querySelector('[aria-selected="true"]:not([aria-disabled="true"])')
                        || timeMenu.querySelector('[role="option"]:not([aria-disabled="true"])'))?.focus();
                }
            });
            timeTrigger.addEventListener('keydown', (event) => {
                if (['ArrowDown', 'Enter', ' '].includes(event.key) && !timeTrigger.disabled) {
                    event.preventDefault();
                    timeTrigger.click();
                }
            });
            document.addEventListener('click', (event) => {
                if (!timePicker.contains(event.target) && !timeMenu.contains(event.target)) {
                    closeTimeMenu();
                }
            });
            timePicker.addEventListener('focusout', (event) => {
                if (!timePicker.contains(event.relatedTarget) && !timeMenu.contains(event.relatedTarget)) {
                    closeTimeMenu();
                }
            });
            window.addEventListener('scroll', (event) => {
                if (!timeMenu.contains(event.target)) {
                    closeTimeMenu();
                }
            }, true);
            window.addEventListener('resize', () => closeTimeMenu());
            timeSelect.addEventListener('change', renderTimePicker);
            renderTimePicker();

            const formatMonth = (date) => (
                `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
            );

            const updateSundayValidation = () => {
                const isSunday = dateInput.value !== ''
                    && new Date(`${dateInput.value}T12:00:00`).getDay() === 0;

                if (dateInput.type !== 'hidden') {
                    dateInput.setCustomValidity(isSunday ? sundayMessage : '');
                }
                errorMessage?.classList.toggle('hidden', !isSunday);
            };

            const updateTimes = () => {
                const selectedTime = preferredTime || timeSelect.value;
                const availableTimes = availability[dateInput.value] || [];
                timeSelect.replaceChildren(new Option('Select time', ''));

                appointmentTimeOptions.forEach(({ value: time, label }) => {
                    const slotDate = new Date(`2000-01-01T${time}:00`);
                    const formattedTime = new Intl.DateTimeFormat(undefined, {
                        hour: 'numeric',
                        minute: '2-digit',
                    }).format(slotDate);
                    const isAvailabilityLoaded = form.dataset.availabilityLoaded === 'true';
                    const isAvailable = isAvailabilityLoaded && dateInput.value !== '' && availableTimes.includes(time);
                    const status = !dateInput.value
                        ? 'Choose a date'
                        : !isAvailabilityLoaded
                            ? 'Checking…'
                            : isAvailable
                                ? 'Available'
                                : 'Unavailable';
                    const option = new Option(
                        `${formattedTime} — ${status}`,
                        time,
                        false,
                        isAvailable && time === selectedTime,
                    );
                    option.disabled = !isAvailable;
                    option.dataset.available = String(isAvailable);
                    option.dataset.status = status;
                    option.dataset.timeLabel = formattedTime || label;
                    timeSelect.add(option);
                });

                if (form.dataset.availabilityLoaded === 'true'
                    && selectedTime
                    && !availableTimes.includes(selectedTime)) {
                    preferredTime = '';
                }

                timeSelect.disabled = form.dataset.availabilityLoaded !== 'true'
                    || !dateInput.value
                    || availableTimes.length === 0;
                timeSelect.setCustomValidity('');
                renderTimePicker();

                if (!availabilityMessage) {
                    return;
                }

                availabilityMessage.classList.remove('hidden');
                if (!dateInput.value) {
                    availabilityMessage.textContent = patientBooking
                        ? 'Select a highlighted date to see available times.'
                        : 'Select a date to see available times.';
                } else if (availableTimes.length === 0) {
                    availabilityMessage.textContent = 'No appointment times are available on this date.';
                } else {
                    const unavailableCount = appointmentTimeOptions.length - availableTimes.length;
                    availabilityMessage.textContent = `${availableTimes.length} times available; ${unavailableCount} unavailable.`;
                }
            };

            const selectedTreatments = new Map();
            const treatmentsList = form.querySelector('[data-selected-treatments-list]');
            const countBadge = form.querySelector('[data-selected-count-badge]');
            const noTreatmentsMsg = form.querySelector('[data-no-treatments-message]');
            const combinedCostEl = form.querySelector('[data-combined-estimated-cost]');
            const combinedBreakdownEl = form.querySelector('[data-combined-breakdown]');
            const hiddenContainer = form.querySelector('[data-service-ids-container]');
            const formatEstimate = (amount) => `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

            const getTreatmentEstimate = (option) => {
                const rawPrice = Number(option.dataset.rawPrice);
                const requiresConfirmation = option.dataset.requiresConfirmation === 'true';

                if (!requiresConfirmation || rawPrice > 0) {
                    return { minimum: rawPrice, maximum: rawPrice };
                }

                const priceReferences = Array.from(
                    (option.dataset.priceDisplay || '').matchAll(/₱\s*([\d,]+(?:\.\d{1,2})?)/g),
                    (match) => Number(match[1].replace(/,/g, '')),
                ).filter(Number.isFinite);

                if (priceReferences.length === 0) {
                    return { minimum: null, maximum: null };
                }

                const display = option.dataset.priceDisplay || '';
                const hasRange = priceReferences.length > 1 && /[–—-]\s*₱?\s*[\d,]/.test(display);
                const hasUnknownUpperBound = /\band up\b|\bper\b|\bplus\b|\+/.test(display.toLowerCase());

                return {
                    minimum: priceReferences[0],
                    maximum: hasRange
                        ? priceReferences[1]
                        : (hasUnknownUpperBound ? null : priceReferences[0]),
                };
            };

            const getServiceOption = (serviceId) => {
                for (const select of serviceSelects) {
                    const opt = Array.from(select.options).find((o) => o.value === String(serviceId));
                    if (opt) {
                        return opt;
                    }
                }
                return null;
            };

            const renderSelectedTreatments = () => {
                const count = selectedTreatments.size;
                if (countBadge) {
                    countBadge.textContent = `${count} treatment${count === 1 ? '' : 's'} selected`;
                }

                serviceSelects.forEach((select) => {
                    const categoryLabel = select.closest('label');
                    const activeBadge = categoryLabel?.querySelector('[data-category-active]');
                    const selectedServices = categoryLabel?.querySelector('[data-category-selections]');
                    const catOptions = Array.from(select.options).map((o) => o.value).filter(Boolean);
                    const selectedCategoryTreatments = catOptions
                        .map((id) => selectedTreatments.get(String(id)))
                        .filter(Boolean);
                    const catSelectedCount = selectedCategoryTreatments.length;

                    categoryLabel?.classList.toggle('border-teal-500', catSelectedCount > 0);
                    categoryLabel?.classList.toggle('bg-teal-50', catSelectedCount > 0);
                    categoryLabel?.classList.toggle('ring-2', catSelectedCount > 0);
                    categoryLabel?.classList.toggle('ring-teal-100', catSelectedCount > 0);
                    categoryLabel?.classList.toggle('shadow-md', catSelectedCount > 0);

                    if (activeBadge) {
                        activeBadge.classList.toggle('hidden', catSelectedCount === 0);
                        activeBadge.textContent = `${catSelectedCount} SELECTED`;
                    }
                    if (selectedServices) {
                        selectedServices.replaceChildren();
                        selectedServices.classList.toggle('hidden', catSelectedCount === 0);
                        selectedServices.classList.toggle('grid', catSelectedCount > 0);

                        if (catSelectedCount > 0) {
                            const selectionsHeading = document.createElement('span');
                            selectionsHeading.className = 'text-[0.65rem] font-bold uppercase tracking-wider text-teal-800';
                            selectionsHeading.textContent = 'Added to this appointment';
                            selectedServices.appendChild(selectionsHeading);

                            selectedCategoryTreatments.forEach((treatment) => {
                                const selectedService = document.createElement('span');
                                selectedService.className = 'flex min-w-0 items-center gap-2 rounded-md border border-teal-200 bg-white px-2.5 py-2 text-sm font-semibold text-teal-950 shadow-sm';

                                const checkmark = document.createElement('span');
                                checkmark.className = 'grid h-5 w-5 shrink-0 place-items-center rounded-full bg-teal-700 text-xs font-bold text-white';
                                checkmark.setAttribute('aria-hidden', 'true');
                                checkmark.textContent = '✓';

                                const serviceName = document.createElement('span');
                                serviceName.className = 'min-w-0';
                                serviceName.textContent = treatment.name;

                                selectedService.append(checkmark, serviceName);
                                selectedServices.appendChild(selectedService);
                            });
                        }
                    }
                    select.value = '';
                });

                if (treatmentsList) {
                    treatmentsList.replaceChildren();

                    if (count === 0) {
                        if (noTreatmentsMsg) {
                            treatmentsList.appendChild(noTreatmentsMsg);
                        }
                        if (combinedCostEl) {
                            combinedCostEl.textContent = '₱0.00';
                        }
                        if (combinedBreakdownEl) {
                            combinedBreakdownEl.textContent = '';
                        }
                    } else {
                        let minimumTotal = 0;
                        let maximumTotal = 0;
                        let hasUnpricedTreatment = false;
                        let hasOpenEndedPrice = false;
                        let hasConfirmationPrice = false;
                        const priceBreakdowns = [];

                        selectedTreatments.forEach((treatment) => {
                            const row = document.createElement('div');
                            row.className = 'flex items-center justify-between gap-3 py-2.5';

                            const left = document.createElement('div');
                            left.className = 'min-w-0';

                            const nameSpan = document.createElement('span');
                            nameSpan.className = 'font-semibold text-slate-800 text-sm block';
                            nameSpan.textContent = treatment.name;

                            const catSpan = document.createElement('span');
                            catSpan.className = 'text-xs text-slate-400 block';
                            catSpan.textContent = treatment.category;

                            left.append(nameSpan, catSpan);

                            const right = document.createElement('div');
                            right.className = 'flex items-center gap-3 shrink-0';

                            const priceSpan = document.createElement('span');
                            priceSpan.className = 'font-bold text-slate-900 text-sm';
                            priceSpan.textContent = treatment.estimatedMinimum === null
                                ? 'Price to confirm'
                                : treatment.price;

                            const removeBtn = document.createElement('button');
                            removeBtn.type = 'button';
                            removeBtn.className = 'rounded-lg bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition-colors';
                            removeBtn.textContent = '✕ Remove';
                            removeBtn.setAttribute('aria-label', `Remove ${treatment.name}`);
                            removeBtn.addEventListener('click', () => removeTreatment(treatment.id));

                            right.append(priceSpan, removeBtn);
                            row.append(left, right);
                            treatmentsList.appendChild(row);

                            hasConfirmationPrice ||= treatment.requiresConfirmation;
                            if (treatment.estimatedMinimum === null) {
                                hasUnpricedTreatment = true;
                                priceBreakdowns.push('Price to confirm');
                                return;
                            }

                            minimumTotal += treatment.estimatedMinimum;
                            priceBreakdowns.push(treatment.estimatedMinimum === treatment.estimatedMaximum
                                ? formatEstimate(treatment.estimatedMinimum)
                                : `${formatEstimate(treatment.estimatedMinimum)}${treatment.estimatedMaximum === null ? '+' : `–${formatEstimate(treatment.estimatedMaximum)}`}`);

                            if (treatment.estimatedMaximum === null) {
                                hasOpenEndedPrice = true;
                            } else {
                                maximumTotal += treatment.estimatedMaximum;
                            }
                        });

                        if (combinedCostEl) {
                            if (hasUnpricedTreatment) {
                                combinedCostEl.textContent = minimumTotal > 0
                                    ? `${formatEstimate(minimumTotal)} + price confirmation required`
                                    : 'Price confirmation required';
                            } else if (hasOpenEndedPrice) {
                                combinedCostEl.textContent = `${formatEstimate(minimumTotal)}+${hasConfirmationPrice ? ' (est.)' : ''}`;
                            } else if (minimumTotal !== maximumTotal) {
                                combinedCostEl.textContent = `${formatEstimate(minimumTotal)}–${formatEstimate(maximumTotal)}${hasConfirmationPrice ? ' (est.)' : ''}`;
                            } else {
                                combinedCostEl.textContent = `${formatEstimate(minimumTotal)}${hasConfirmationPrice ? ' (est.)' : ''}`;
                            }
                        }

                        if (combinedBreakdownEl) {
                            if (hasUnpricedTreatment) {
                                combinedBreakdownEl.textContent = `Known-price subtotal: ${formatEstimate(minimumTotal)}. Final total includes prices confirmed by clinic staff.`;
                            } else if (hasConfirmationPrice) {
                                combinedBreakdownEl.textContent = `${priceBreakdowns.join(' + ')}. Reference estimates only; final prices require clinic confirmation.`;
                            } else if (priceBreakdowns.length > 1) {
                                combinedBreakdownEl.textContent = `${priceBreakdowns.join(' + ')} = ${formatEstimate(minimumTotal)}`;
                            } else {
                                combinedBreakdownEl.textContent = '';
                            }
                        }
                    }
                }

                if (hiddenContainer) {
                    hiddenContainer.replaceChildren();
                    selectedTreatments.forEach((treatment) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'service_ids[]';
                        input.value = treatment.id;
                        hiddenContainer.appendChild(input);
                    });

                    if (count > 0) {
                        const firstId = Array.from(selectedTreatments.keys())[0];
                        const primaryHidden = document.createElement('input');
                        primaryHidden.type = 'hidden';
                        primaryHidden.name = 'service_id';
                        primaryHidden.value = firstId;
                        hiddenContainer.appendChild(primaryHidden);
                    }
                }

                if (serviceSelects.length > 0) {
                    const primarySelect = serviceSelects[0];
                    primarySelect.name = 'service_id';
                    if (count > 0) {
                        primarySelect.required = false;
                        primarySelect.setCustomValidity('');
                    } else {
                        primarySelect.required = true;
                        primarySelect.setCustomValidity('Choose one or more treatments before continuing.');
                    }
                }
            };

            const addTreatment = (serviceId) => {
                if (!serviceId || selectedTreatments.has(String(serviceId))) {
                    return;
                }
                const opt = getServiceOption(serviceId);
                if (!opt) {
                    return;
                }

                const estimate = getTreatmentEstimate(opt);
                selectedTreatments.set(String(serviceId), {
                    id: String(serviceId),
                    name: opt.dataset.serviceName || opt.textContent.split(' — ')[0],
                    price: opt.dataset.price || '',
                    estimatedMinimum: estimate.minimum,
                    estimatedMaximum: estimate.maximum,
                    requiresConfirmation: opt.dataset.requiresConfirmation === 'true',
                    category: opt.dataset.category || '',
                });
                renderSelectedTreatments();
            };

            const removeTreatment = (serviceId) => {
                selectedTreatments.delete(String(serviceId));
                renderSelectedTreatments();
                if (dateInput.value || patientBooking) {
                    loadAvailability(currentMonth);
                }
            };

            const renderCalendar = () => {
                if (!calendar) {
                    return;
                }

                const daysContainer = calendar.querySelector('[data-calendar-days]');
                const monthLabel = calendar.querySelector('[data-calendar-month]');
                const monthDate = new Date(`${currentMonth}-01T12:00:00`);
                const firstDayOffset = (monthDate.getDay() + 6) % 7;
                const daysInMonth = new Date(
                    monthDate.getFullYear(),
                    monthDate.getMonth() + 1,
                    0,
                ).getDate();
                monthLabel.textContent = new Intl.DateTimeFormat(undefined, {
                    month: 'long',
                    year: 'numeric',
                }).format(monthDate);
                daysContainer.replaceChildren();

                for (let blank = 0; blank < firstDayOffset; blank += 1) {
                    const spacer = document.createElement('span');
                    spacer.setAttribute('aria-hidden', 'true');
                    daysContainer.append(spacer);
                }

                for (let day = 1; day <= daysInMonth; day += 1) {
                    const date = `${currentMonth}-${String(day).padStart(2, '0')}`;
                    const times = availability[date] || [];
                    const isAvailable = times.length > 0;
                    const button = document.createElement('button');
                    const isSelected = dateInput.value === date;
                    button.type = 'button';
                    button.disabled = !isAvailable;
                    button.className = isAvailable
                        ? 'calendar-day calendar-day-available'
                        : 'calendar-day calendar-day-unavailable';
                    if (isAvailable && isSelected) {
                        button.classList.add('calendar-day-selected');
                    }
                    button.textContent = day;
                    button.setAttribute('aria-pressed', String(isSelected));
                    button.setAttribute(
                        'aria-label',
                        `${new Intl.DateTimeFormat(undefined, { dateStyle: 'full' }).format(new Date(`${date}T12:00:00`))}: ${isAvailable ? `${times.length} available times` : 'unavailable'}`,
                    );
                    button.addEventListener('click', () => {
                        dateInput.value = date;
                        dateInput.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    daysContainer.append(button);
                }

                calendar.querySelector('[data-calendar-previous]').disabled = currentMonth <= form.dataset.currentMonth;
                calendar.querySelector('[data-calendar-next]').disabled = form.dataset.availabilityLoaded !== 'true';
            };

            const loadAvailability = async (month) => {
                const requestId = ++availabilityRequest;
                closeTimeMenu();
                currentMonth = month;
                availability = {};
                form.dataset.availabilityLoaded = 'false';
                timeSelect.disabled = true;
                if (availabilityMessage) {
                    availabilityMessage.classList.remove('hidden');
                    availabilityMessage.textContent = 'Loading available appointment times…';
                }
                updateTimes();
                renderCalendar();

                const url = new URL(form.dataset.availabilityUrl, window.location.origin);
                url.searchParams.set('month', month);
                if (!branchSelect?.value) {
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Choose a branch to see its available appointment times.';
                    }
                    return false;
                }
                url.searchParams.set('branch_id', branchSelect.value);
                if (excludedAppointmentId) {
                    url.searchParams.set('exclude_appointment_id', excludedAppointmentId);
                }
                if (selectedTreatments.size > 0) {
                    const sIds = Array.from(selectedTreatments.keys());
                    url.searchParams.set('service_id', sIds[0]);
                    sIds.forEach((id) => url.searchParams.append('service_ids[]', id));
                } else {
                    const selectedService = form.querySelector('[name="service_id"]:checked')
                        || form.querySelector('[name="service_id"]:not([type="radio"])');
                    if (selectedService?.value) {
                        url.searchParams.set('service_id', selectedService.value);
                    }
                }

                try {
                    const response = await fetch(url, {
                        cache: 'no-store',
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) {
                        throw new Error(`Availability request failed with status ${response.status}.`);
                    }

                    const result = await response.json();
                    if (requestId !== availabilityRequest) {
                        return;
                    }
                    availability = result.days;
                    currentMonth = result.month;
                    form.dataset.availabilityLoaded = 'true';
                    renderCalendar();
                    updateTimes();
                    return true;
                } catch (error) {
                    if (requestId !== availabilityRequest) {
                        return false;
                    }
                    console.error(error);
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Unable to load appointment availability. Refresh the page and try again.';
                    }
                    return false;
                }
            };

            dateInput.addEventListener('change', () => {
                updateSundayValidation();
                if (dateInput.value) {
                    preferredTime = '';
                    loadAvailability(dateInput.value.slice(0, 7));
                    return;
                }
                preferredTime = '';
                renderCalendar();
                updateTimes();
            });
            timeSelect.addEventListener('change', () => {
                preferredTime = timeSelect.value;
                timeSelect.setCustomValidity('');
            });
            branchSelect?.addEventListener('change', () => {
                preferredTime = '';
                timeSelect.value = '';
                loadAvailability(dateInput.value ? dateInput.value.slice(0, 7) : currentMonth);
            });
            serviceSelects.forEach((select) => select.addEventListener('change', () => {
                if (select.value) {
                    addTreatment(select.value);
                    if (dateInput.value || patientBooking) {
                        loadAvailability(currentMonth);
                    }
                }
            }));
            calendar?.querySelector('[data-calendar-previous]').addEventListener('click', () => {
                const previous = new Date(`${currentMonth}-01T12:00:00`);
                previous.setMonth(previous.getMonth() - 1);
                loadAvailability(formatMonth(previous));
            });
            calendar?.querySelector('[data-calendar-next]').addEventListener('click', () => {
                const next = new Date(`${currentMonth}-01T12:00:00`);
                next.setMonth(next.getMonth() + 1);
                loadAvailability(formatMonth(next));
            });
            form.addEventListener('submit', async (event) => {
                if (form.dataset.submittingAfterAvailabilityRefresh === 'true') {
                    delete form.dataset.submittingAfterAvailabilityRefresh;
                    return;
                }

                event.preventDefault();
                updateSundayValidation();

                if (serviceSelects.length > 0 && selectedTreatments.size === 0) {
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Choose one or more treatments before continuing.';
                    }
                    serviceSelects[0]?.focus();
                    return;
                }

                if (patientBooking && !dateInput.value) {
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Choose an available date and time to continue.';
                    }
                    calendar?.querySelector('.calendar-day-available')?.focus();
                    return;
                }

                if (patientBooking && !timeSelect.value) {
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Choose one of the available appointment times to continue.';
                    }
                    timeTrigger.focus();
                    return;
                }

                if (timeIsRequired && !timeSelect.value) {
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Choose an available appointment time before continuing.';
                    }
                    timeTrigger.focus();
                    return;
                }

                if (!form.reportValidity()) {
                    return;
                }

                const selectedMonth = dateInput.value
                    ? dateInput.value.slice(0, 7)
                    : currentMonth;
                if (!await loadAvailability(selectedMonth)) {
                    return;
                }

                const availableTimes = availability[dateInput.value] || [];
                if (dateInput.value && !availableTimes.includes(timeSelect.value)) {
                    timeSelect.setCustomValidity('That time is no longer available. Choose another time.');
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'That time is no longer available. Choose another time.';
                    }
                    timeTrigger.focus();
                    return;
                }

                form.dataset.submittingAfterAvailabilityRefresh = 'true';
                form.requestSubmit(event.submitter);
            });
            updateSundayValidation();
            serviceSelects.forEach((select) => {
                Array.from(select.options).forEach((opt) => {
                    if (opt.selected && opt.value) {
                        addTreatment(opt.value);
                    }
                });
            });
            renderSelectedTreatments();
            loadAvailability(currentMonth);
        });
    </script>
@endsection
