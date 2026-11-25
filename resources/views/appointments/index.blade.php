@extends('layouts.app')

@section('title', $isAppointmentRecordsPage ? 'Appointment Records' : 'Appointments')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">{{ auth()->user()->role === 'patient' ? 'Patient portal' : 'Clinic operations' }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">{{ $isAppointmentRecordsPage ? 'Appointment Records' : 'Appointments' }}</h1>
        <p class="mt-2 text-slate-500">{{ $isAppointmentRecordsPage ? 'Review ongoing visits, pending requests, and appointment history.' : (auth()->user()->role === 'patient' ? 'Request an appointment and track its approval status.' : 'Schedule appointments and register walk-ins. Manage existing records in Appointment Records.') }}</p>
    </div>

    @if (! $isAppointmentRecordsPage && (auth()->user()->role === 'patient' || auth()->user()->hasRole('admin', 'staff', 'doctor')))
        <section class="mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-gradient-to-r from-teal-50 via-white to-white px-5 py-5 sm:px-7">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-teal-700 text-lg font-bold text-white" aria-hidden="true">+</span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900" data-booking-title>{{ auth()->user()->role === 'patient' ? 'Request an appointment' : 'Register an appointment' }}</h2>
                        <p class="mt-1 text-sm text-slate-500" data-booking-description>{{ auth()->user()->role === 'patient' ? 'Choose a service and date. The front desk will review your request and decide how many appointments to approve for that day.' : 'Choose a patient and service. Walk-ins join the waiting queue immediately.' }}</p>
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('appointments.store') }}" class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7 xl:grid-cols-12" data-appointment-schedule-form data-availability-url="{{ route('appointments.availability') }}" data-current-month="{{ today(config('clinic.timezone'))->format('Y-m') }}" data-schedule-confirm="{{ auth()->user()->role === 'patient' ? 'Submit this appointment date for front-desk approval?' : 'Register this walk-in appointment and add it to the queue?' }}" data-schedule-confirm-title="Confirm appointment" @if (auth()->user()->role === 'patient') enctype="multipart/form-data" data-patient-booking data-max-treatments="2" @endif @if (auth()->user()->hasRole('doctor')) data-doctor-booking data-doctor-branch-id="{{ auth()->user()->branch_id }}" @endif>
                @csrf
                <label class="form-label sm:col-span-2 xl:col-span-4">Branch
                    <select class="form-input" name="branch_id" data-branch-select required>
                        <option value="">Choose a branch</option>
                        @foreach ($bookingBranches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $hasAssignedBranch ? auth()->user()->branch_id : '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
                @if (auth()->user()->hasRole('doctor'))
                    <label class="form-label sm:col-span-2 xl:col-span-4">Visit type
                        <select class="form-input" name="booking_mode" data-booking-mode>
                            <option value="walk_in" @selected(old('booking_mode', 'walk_in') === 'walk_in')>Walk-in</option>
                            <option value="appointment" @selected(old('booking_mode') === 'appointment')>Appointment</option>
                        </select>
                    </label>
                @endif
                @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                    <div class="form-label relative sm:col-span-2 xl:col-span-4" data-patient-search-picker>
                        <div class="flex items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <label for="appointment-patient-search">Patient</label>
                                <input
                                    id="appointment-patient-search"
                                    class="form-input"
                                    type="search"
                                    placeholder="Search patient name or number..."
                                    autocomplete="off"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="appointment-patient-search-results"
                                    data-patient-search
                                    required
                                >
                            </div>
                            <button
                                class="btn-primary h-11 w-11 shrink-0 justify-center p-0 text-xl"
                                type="button"
                                aria-label="Register walk-in patient"
                                title="Register walk-in patient"
                                data-walk-in-patient-register
                                data-requires-branch="{{ auth()->user()->hasRole('admin') ? 'true' : 'false' }}"
                                @class(['hidden' => auth()->user()->hasRole('doctor') && old('booking_mode', 'walk_in') !== 'walk_in'])
                                @disabled(auth()->user()->hasRole('admin') && ! old('branch_id'))
                            >+</button>
                        </div>
                        <select name="patient_id" class="hidden" data-patient-select aria-hidden="true" tabindex="-1">
                            <option value="">Choose a patient</option>
                            @foreach ($patients as $patient)
                                <option value="{{ $patient->id }}" data-patient-label="{{ $patient->patient_number }} · {{ $patient->user->name }}" data-patient-search="{{ $patient->patient_number }} {{ $patient->user->name }}" @selected(old('patient_id') == $patient->id)>{{ $patient->patient_number }} · {{ $patient->user->name }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-x-0 top-full z-30 mt-1 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg" data-patient-search-panel>
                            <div id="appointment-patient-search-results" class="max-h-60 overflow-y-auto p-1" role="listbox" aria-label="Matching patients" data-patient-search-results></div>
                            <p class="hidden px-3 py-2 text-sm text-slate-500" role="status" data-patient-search-empty>No patients match that search.</p>
                        </div>
                        @if (auth()->user()->hasRole('admin'))
                            <p class="mt-1 text-xs text-slate-500" @class(['hidden' => old('branch_id')]) data-patient-registration-branch-hint>Choose a branch before registering a walk-in patient.</p>
                        @endif
                    </div>
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
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ auth()->user()->role === 'patient' ? 'Choose up to 2 treatments for this visit. Book additional treatments in a separate appointment.' : 'Choose a service to view its fee and appointment details.' }}</p>
                        </div>
                    </div>
                    <button class="inline-flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-teal-200 bg-white px-4 py-2.5 text-left text-sm font-semibold text-teal-800 shadow-sm transition hover:border-teal-400 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700" type="button" aria-expanded="false" aria-controls="appointment-service-browser" data-service-browser-toggle>
                        <span data-service-browser-label>Browse services</span>
                        <svg class="h-5 w-5 shrink-0 transition-transform" data-service-browser-chevron viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m7 10 5 5 5-5"></path>
                        </svg>
                    </button>
                    <div id="appointment-service-browser" class="hidden min-w-0" data-service-browser>
                        <p class="hidden mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status" data-service-browser-empty>Choose a branch to view its available services.</p>
                        @php($serviceCategories = $services->groupBy('category'))
                        <label class="form-label">Service category
                            <select class="form-input" data-service-category-picker disabled>
                                <option value="">Choose a category...</option>
                                @foreach ($serviceCategories as $category => $categoryServices)
                                    <option value="{{ $loop->index }}">{{ $category ?: 'Other Services' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="min-w-0">
                        @foreach ($serviceCategories as $category => $categoryServices)
                            <div class="hidden min-w-0 rounded-xl border border-slate-200 bg-white p-3" data-service-category-card data-category-index="{{ $loop->index }}">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-slate-800">{{ $category ?: 'Other Services' }}</h4>
                                    <span class="hidden shrink-0 rounded-full bg-teal-700 px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-white" data-category-active></span>
                                </div>
                                <select
                                    class="hidden"
                                    data-service-category
                                    aria-label="{{ $category ?: 'Other Services' }}"
                                    aria-hidden="true"
                                    tabindex="-1"
                                    @if ($loop->first) name="service_id" @endif
                                >
                                    <option value="">Choose a treatment...</option>
                                    @foreach ($categoryServices as $service)
                                        @php($displayPrice = $service->requires_price_confirmation && $service->price_display ? $service->price_display : config('clinic.currency_symbol').number_format((float) $service->price, 2))
                                        <option value="{{ $service->id }}" data-branch-ids="{{ implode(',', $serviceBranchIds[$service->id] ?? []) }}" data-service-name="{{ $service->name }}" data-price="{{ $displayPrice }}" data-price-display="{{ $service->price_display }}" data-raw-price="{{ (float) $service->price }}" data-requires-confirmation="{{ $service->requires_price_confirmation ? 'true' : 'false' }}" data-category="{{ $category ?: 'Other Services' }}" data-duration="{{ (int) $service->duration_minutes }}" @selected(old('service_id') == $service->id || in_array($service->id, (array) old('service_ids', [])))>{{ $service->name }} — {{ $displayPrice }}</option>
                                    @endforeach
                                </select>
                                    <span class="mt-2 hidden rounded-lg border border-teal-300 bg-teal-50 p-2.5 text-teal-950 shadow-inner" data-category-selections></span>
                            </div>
                        @endforeach
                        </div>
                        <p class="mt-3 max-w-3xl text-xs leading-5 text-slate-500">Reference fees are transcribed from the supplied chart list and have not been verified against the clinic's approved fee schedule. They are estimates only; clinic staff must confirm the final amount before billing.</p>
                    </div>
                    <p class="hidden rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert" data-service-message></p>
                    @if (auth()->user()->role === 'patient')
                        <p class="hidden rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status" aria-live="polite" data-treatment-limit-message>You’ve selected the maximum of 2 treatments. Remove a treatment to choose another, or book additional treatments in a separate appointment.</p>
                    @endif
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
                @if (auth()->user()->role === 'patient' || auth()->user()->hasRole('doctor'))
                    <div class="grid min-w-0 content-start gap-2 sm:col-span-2 xl:col-span-6" data-appointment-date-container>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Appointment date</p>
                            <p class="mt-1 text-xs text-slate-500">Choose an available clinic date. Appointment requests do not reserve a time slot.</p>
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
                                <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-teal-600"></span>Available date</span>
                                <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>Unavailable</span>
                                <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Holiday</span>
                                <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>Fully booked</span>
                            </div>
                        </div>
                    </div>
                    <p class="hidden rounded-xl bg-teal-50 px-4 py-3 text-sm font-medium text-teal-900 sm:col-span-2 xl:col-span-12" data-availability-message role="status" aria-live="polite"></p>
                    <p class="hidden rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 sm:col-span-2 xl:col-span-12" data-sunday-error role="alert">The Dental Clinic is closed on Sundays. Please select another date.</p>
                @endif
                @if (auth()->user()->role === 'patient')
                    <label class="form-label sm:col-span-2 xl:col-span-6">Payment method
                        <select class="form-input" name="payment_method" data-patient-payment-method required>
                            <option value="walk_in" @selected(old('payment_method', 'walk_in') === 'walk_in')>Pay at clinic (walk-in)</option>
                            <option value="gcash" @selected(old('payment_method') === 'gcash')>GCash (receipt review)</option>
                        </select>
                        @error('payment_method')
                            <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>
                        @enderror
                    </label>
                @endif
                <label class="form-label sm:col-span-2 xl:col-span-12">Notes <span class="font-normal text-slate-400">(optional)</span><input class="form-input" name="notes" maxlength="2000" placeholder="Reason for visit" value="{{ old('notes') }}"></label>
                @if (auth()->user()->role === 'patient')
                    <section class="grid gap-4 rounded-2xl border border-teal-200 bg-teal-50/60 p-4 sm:col-span-2 sm:p-5 xl:col-span-12" data-gcash-receipt-panel @class(['hidden' => old('payment_method', 'walk_in') !== 'gcash'])>
                        <div>
                            <h3 class="font-semibold text-slate-900">GCash receipt for appointment review</h3>
                            <p class="mt-1 text-sm text-slate-700">Use the clinic's GCash QR code as arranged with the clinic, then upload your receipt screenshot. No preset payment amount is required when booking. Staff must verify the receipt before they can approve your appointment. Receipt verification does not approve the appointment or pay your final bill.</p>
                        </div>
                        <img class="mx-auto w-full max-w-64 rounded-xl border border-slate-200 bg-white p-2" src="{{ asset('images/gcash-deposit-qr.png') }}" alt="Clinic GCash payment QR code">
                        <label class="form-label">Upload GCash receipt screenshot
                            <input class="form-input" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" data-payment-proof required @disabled(old('payment_method', 'walk_in') !== 'gcash')>
                            <span class="mt-1 block text-xs font-normal text-slate-500">JPG, PNG, or WebP; maximum 5 MB.</span>
                            @error('payment_proof')
                                <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>
                            @enderror
                        </label>
                    </section>
                @endif
                <div class="sm:col-span-2 xl:col-span-12">
                    <button class="btn-primary w-full sm:w-auto" data-booking-submit>{{ auth()->user()->role === 'patient' ? 'Submit appointment request' : 'Add walk-in to queue' }}</button>
                </div>
            </form>
            @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                <dialog class="m-auto w-[min(42rem,calc(100vw-2rem))] max-w-none rounded-2xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm" aria-labelledby="walk-in-patient-dialog-title" data-walk-in-patient-dialog>
                    <form method="POST" action="{{ route('clinic.patients.store') }}" data-walk-in-patient-form>
                        @csrf
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-5 sm:p-6">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900" id="walk-in-patient-dialog-title">Register a walk-in patient</h2>
                                <p class="mt-1 text-sm text-slate-500">Create the patient account, then continue with this appointment.</p>
                            </div>
                        </div>
                        <div class="max-h-[70vh] overflow-y-auto p-5 sm:p-6">
                            <p class="mb-4 hidden rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert" data-patient-registration-error></p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="form-label sm:col-span-2">Full name<input class="form-input" name="name" maxlength="255" pattern="[\p{L}\p{M} .'\-]+" title="Use letters, spaces, apostrophes, periods, or hyphens." required></label>
                                @if (auth()->user()->hasRole('admin'))
                                    <input type="hidden" name="branch_id" data-patient-registration-branch required>
                                @elseif (auth()->user()->hasRole('doctor'))
                                    <input type="hidden" name="branch_id" value="{{ auth()->user()->branch_id }}" data-patient-registration-branch>
                                @endif
                                @include('patients._registration-fields')
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                            <button class="btn-secondary" type="button" data-walk-in-patient-close>Cancel</button>
                            <button class="btn-primary" type="submit" data-patient-registration-submit>Create patient account</button>
                        </div>
                    </form>
                </dialog>
            @endif
        </section>
    @endif

    @if (! auth()->user()->hasRole('admin', 'staff') || $isAppointmentRecordsPage)
        @if ($highlightedAppointmentId !== null)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <p>{{ auth()->user()->role === 'doctor' ? 'This visit is highlighted. Review the appointment and its clinical history.' : 'This visit is highlighted. Open its dental record or create a bill from the appointment actions.' }}</p>
                <a class="font-semibold text-amber-900 underline underline-offset-2" href="{{ route($isAppointmentRecordsPage ? 'clinic.appointment-records' : 'appointments.index', collect(request()->query())->except(['appointment_id', 'page'])->all()) }}">Dismiss highlight</a>
            </div>
        @endif
    <section class="rounded-2xl border border-slate-200 bg-white">
        <form method="GET" action="{{ route($isAppointmentRecordsPage ? 'clinic.appointment-records' : 'appointments.index') }}" class="grid gap-3 border-b border-slate-100 bg-slate-50/60 p-4 sm:flex sm:flex-wrap sm:items-end sm:p-5">
            <label class="form-label sm:min-w-48">Filter by Branch
                <select class="form-input" name="branch_id">
                    @unless ($hasAssignedBranch)
                        <option value="">All Branches</option>
                    @endunless
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') === (string) $branch->id || ($hasAssignedBranch && request('branch_id') === null && (int) auth()->user()->branch_id === $branch->id))>{{ $branch->name }}</option>
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
                <select class="form-input" name="status"><option value="">All statuses</option>@foreach (['pending', 'approved', 'rejected', 'checked_in', 'waiting', 'in_consultation', 'billed', 'completed', 'cancelled', 'no_show'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>
            </label>
            <button class="btn-secondary self-end">Filter</button>
        </form>
        @if ($isAppointmentRecordsPage && auth()->user()->hasRole('admin', 'staff'))
            <section class="grid gap-5 border-b border-slate-100 bg-teal-50/50 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Daily approval limit</h2>
                    <p class="mt-1 text-sm text-slate-600">Set a maximum of 10 scheduled appointments staff can approve for one branch and date. Filter this list to the same branch and date before approving. Pending requests and walk-ins do not use a spot; cancellations and rejections free one, while completed and no-show appointments still count.</p>
                    @if ($capacityManagementBranchId !== null)
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-700" aria-live="polite">
                            <span><strong>{{ $capacityUsed }}</strong> of <strong>{{ $capacityDailyLimit ?? 'not set' }}</strong> appointments counted</span>
                            @if ($capacityRemaining !== null)
                                <span><strong>{{ $capacityRemaining }}</strong> spot(s) remaining</span>
                            @endif
                            <span><strong>{{ $capacityPending }}</strong> pending request(s)</span>
                        </div>
                    @else
                        <p class="mt-3 text-sm font-medium text-amber-800">Choose a branch to view its current daily count.</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('clinic.appointment-capacities.store') }}" class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(8rem,0.7fr)_auto]" data-confirm="Save the daily appointment approval limit for this branch and date?" data-confirm-title="Save daily approval limit" data-confirm-accept="Save limit">
                    @csrf
                    <label class="form-label">Branch
                        <select class="form-input" name="branch_id" required>
                            <option value="">Choose a branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $capacityManagementBranchId) === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-label">Appointment date
                        <input class="form-input date-time-input" type="date" name="appointment_date" min="{{ today(config('clinic.timezone'))->toDateString() }}" value="{{ old('appointment_date', $capacityManagementDate) }}" required>
                    </label>
                    <label class="form-label">Maximum approvals
                        <input class="form-input" type="number" name="daily_limit" min="0" max="10" value="{{ old('daily_limit', $capacityDailyLimit) }}" required>
                    </label>
                    <button class="btn-primary sm:col-span-2 xl:col-span-1">Save limit</button>
                </form>
            </section>
        @endif
        <div class="space-y-5 p-4 sm:p-5">
        @foreach ($appointmentSections as $sectionTitle => $sectionAppointments)
            @if ($isAppointmentRecordsPage)
                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ $sectionTitle }}</h2>
            @endif
            <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Appointment date</th>@if (auth()->user()->hasRole('admin', 'staff', 'doctor'))<th>Patient</th>@endif @if ($isAppointmentRecordsPage)<th>Follow-up date</th>@endif<th>Branch</th><th>Service</th><th>Type</th><th>Status</th><th>Queue</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($sectionAppointments as $appointment)
                    <tr id="appointment-{{ $appointment->id }}" @class(['bg-amber-50 outline outline-2 outline-amber-400 outline-offset-[-2px]' => $highlightedAppointmentId === $appointment->id]) @if ($highlightedAppointmentId === $appointment->id) aria-current="true" @endif>
                        <td data-label="Appointment date">
                            <span class="inline-flex flex-col gap-1 whitespace-nowrap">
                                <span class="font-semibold text-slate-800">{{ $appointment->starts_at->format('M j, Y') }}</span>
                                @if ($appointment->type === 'walk_in')
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500"><span aria-hidden="true">◷</span>{{ $appointment->starts_at->format('g:i A') }}</span>
                                @endif
                            </span>
                        </td>
                        @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                            <td data-label="Patient">
                                @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                                    <a class="font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $appointment->patient) }}">{{ $appointment->patient->user->name }}</a>
                                @else
                                    <span class="font-semibold">{{ $appointment->patient->user->name }}</span>
                                @endif
                                <div class="text-xs text-slate-400">{{ $appointment->patient->patient_number }}</div>
                            </td>
                        @endif
                        @if ($isAppointmentRecordsPage)
                            <td data-label="Follow-up date">
                                {{ $appointment->dentalRecord?->follow_up_date?->format('M j, Y') ?? '—' }}
                                @if ($appointment->dentalRecord?->formattedFollowUpTime())
                                    <span class="block text-xs text-slate-500">{{ $appointment->dentalRecord->formattedFollowUpTime() }}</span>
                                @endif
                            </td>
                        @endif
                        <td data-label="Branch"><span class="inline-flex max-w-full rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $appointment->branch?->name ?? 'Not Assigned' }}</span></td>
                        <td data-label="Service">
                            @php($appointmentServices = $appointment->services->isNotEmpty() ? $appointment->services : ($appointment->service ? collect([$appointment->service]) : collect()))
                            @php($addOnItems = $appointment->bill?->items->where('is_add_on', true) ?? collect())
                            <p class="mb-1 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500">Original booked services</p>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($appointmentServices as $apptService)
                                    <span class="inline-flex rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-900">{{ $apptService->name }}</span>
                                @empty
                                    <span class="text-xs text-slate-400">No services</span>
                                @endforelse
                            </div>
                            <p class="mb-1 mt-2 text-[0.65rem] font-semibold uppercase tracking-wide text-teal-800">Add-on Services</p>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($addOnItems as $addOnItem)
                                    <span class="inline-flex rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-900">{{ $addOnItem->service_name }}</span>
                                @empty
                                    <span class="text-xs text-slate-400">No add-on services.</span>
                                @endforelse
                            </div>
                        </td><td data-label="Type" class="capitalize">{{ str_replace('_', ' ', $appointment->type) }}</td>
                        <td data-label="Status">
                            <span class="status-badge capitalize">{{ str_replace('_', ' ', $appointment->status) }}</span>
                            @if ($appointment->payment_method === 'walk_in')
                                <p class="mt-1 text-xs text-slate-600">Payment: Pay at clinic</p>
                            @elseif ($appointment->payment_method === 'gcash')
                                <p class="mt-1 text-xs font-medium text-slate-700">Payment: GCash</p>
                                <p class="text-xs text-slate-600">Receipt: {{ match ($appointment->payment_proof_status) {
                                    'pending_review' => 'Pending staff review',
                                    'verified' => 'Verified',
                                    'rejected' => 'Rejected',
                                    default => 'Not submitted',
                                } }}</p>
                            @endif
                        </td>
                        <td data-label="Queue">{{ $appointment->queueEntry?->queue_number ?? '—' }}</td>
                        <td data-label="Actions"><div class="flex flex-wrap gap-2">
                            @if (
                                $isAppointmentRecordsPage
                                && auth()->user()->hasRole('admin', 'staff')
                                && $appointment->payment_method === 'gcash'
                                && $appointment->payment_proof_path !== null
                            )
                                <a class="btn-small" href="{{ route('appointments.payment-proof', $appointment) }}" target="_blank" rel="noopener">View receipt screenshot</a>
                                @if ($appointment->payment_proof_status === 'pending_review')
                                    <form method="POST" action="{{ route('clinic.appointments.payment-proof.review', $appointment) }}" data-confirm="Confirm that the GCash receipt matches the payment received?" data-confirm-title="Verify GCash receipt" data-confirm-accept="Verify receipt">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="verified">
                                        <button class="btn-small">Verify receipt</button>
                                    </form>
                                    <form method="POST" action="{{ route('clinic.appointments.payment-proof.review', $appointment) }}" data-confirm="Reject this GCash receipt? The patient can submit a replacement while the appointment is active." data-confirm-title="Reject GCash receipt" data-confirm-accept="Reject receipt" data-confirm-variant="warning">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="rejected">
                                        <button class="btn-danger">Reject receipt</button>
                                    </form>
                                @elseif ($appointment->payment_proof_reviewed_at)
                                    <span class="text-xs text-slate-500">Reviewed by {{ $appointment->paymentProofReviewer?->name ?? 'Clinic staff' }} · {{ $appointment->payment_proof_reviewed_at->timezone(config('clinic.timezone'))->format('M j, Y g:i A') }}</span>
                                @endif
                            @endif
                            @if (
                                auth()->user()->role === 'patient'
                                && $appointment->payment_method === 'gcash'
                                && $appointment->payment_proof_status === 'rejected'
                                && in_array($appointment->status, ['pending', 'approved'], true)
                            )
                                <form method="POST" action="{{ route('appointments.payment-proof.resubmit', $appointment) }}" enctype="multipart/form-data" class="grid max-w-xs gap-2">
                                    @csrf
                                    <label class="form-label text-xs">Upload replacement receipt
                                        <input class="form-input" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required>
                                    </label>
                                    <button class="btn-small justify-self-start">Resubmit receipt</button>
                                </form>
                            @endif
                            @if (auth()->user()->role === 'patient' && in_array($appointment->status, ['pending', 'approved'], true))
                                <form method="POST" action="{{ route('appointments.transition', [$appointment, 'cancel']) }}" data-confirm="Cancel this appointment request?" data-confirm-title="Cancel appointment" data-confirm-accept="Cancel appointment" data-confirm-variant="warning">@csrf<button class="btn-danger">Cancel</button></form>
                            @elseif (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                                @if (in_array($appointment->status, ['pending', 'approved'], true))
                                    @if (in_array($appointment->id, $reschedulableAppointmentIds, true))
                                        <details class="min-w-0">
                                            <summary class="btn-small cursor-pointer list-none">Reschedule</summary>
                                            <form method="POST" action="{{ route('clinic.appointments.reschedule', $appointment) }}" class="mt-2 grid min-w-0 gap-2" data-appointment-schedule-form data-availability-url="{{ route('appointments.availability') }}" data-current-month="{{ today(config('clinic.timezone'))->format('Y-m') }}" data-exclude-appointment-id="{{ $appointment->id }}" data-exclude-appointment-branch-id="{{ $appointment->branch_id }}" data-schedule-confirm="Save this new appointment date?" data-schedule-confirm-title="Confirm reschedule">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="service_id" value="{{ $appointment->service_id }}">
                                                @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
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
                                                <input type="hidden" name="appointment_date" value="{{ old('appointment_date', $appointment->starts_at->format('Y-m-d')) }}" data-appointment-date>
                                                <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-slate-50 p-2" data-availability-calendar>
                                                    <div class="mb-2 flex items-center justify-between gap-2">
                                                        <button class="calendar-nav-button" type="button" data-calendar-previous aria-label="Previous month">←</button>
                                                        <p class="text-sm font-bold text-slate-900" data-calendar-month></p>
                                                        <button class="calendar-nav-button" type="button" data-calendar-next aria-label="Next month">→</button>
                                                    </div>
                                                    <div class="mb-1 grid grid-cols-7 text-center text-[0.65rem] font-bold uppercase text-slate-400" aria-hidden="true">
                                                        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                                                    </div>
                                                    <div class="grid grid-cols-7 gap-1" data-calendar-days role="group" aria-label="Available reschedule dates"></div>
                                                    <p class="mt-2 border-t border-slate-200 pt-2 text-xs text-slate-600">Selected: <span class="font-semibold" data-selected-date-label>{{ $appointment->starts_at->format('F j, Y') }}</span></p>
                                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[0.65rem] text-slate-500">
                                                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-teal-600"></span>Available</span>
                                                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-400"></span>Limit reached</span>
                                                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Holiday</span>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn-small">Save new date</button>
                                            </form>
                                            <p class="hidden mt-2 text-sm font-medium text-slate-600" data-availability-message role="status" aria-live="polite"></p>
                                            <p class="mt-2 text-xs text-slate-500">Appointments are scheduled by date only. Sunday, clinic holidays, and full dates are unavailable.</p>
                                            <p class="hidden mt-2 text-sm font-medium text-rose-700" data-sunday-error role="alert">The Dental Clinic is closed on Sundays. Please select another date.</p>
                                        </details>
                                    @else
                                        @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                                            @if ($appointment->starts_at->toDateString() < today(config('clinic.timezone'))->toDateString())
                                                <p class="text-xs font-medium text-slate-500">Past appointments cannot be rescheduled.</p>
                                            @else
                                                <p class="text-xs font-medium text-slate-500">Past appointments cannot be rescheduled.</p>
                                            @endif
                                        @else
                                            <p class="text-xs font-medium text-slate-500">Appointments must be rescheduled at least one calendar day in advance.</p>
                                        @endif
                                    @endif
                                @endif
                                @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                                    @if ($appointment->status === 'pending' && auth()->user()->hasRole('admin', 'staff'))
                                        @if (
                                            $appointment->payment_method === 'gcash'
                                            && $appointment->payment_proof_status !== 'verified'
                                        )
                                            <p class="max-w-48 text-xs font-medium text-amber-800">Verify the GCash receipt before approving.</p>
                                        @elseif (! in_array($appointment->type, ['online', 'appointment'], true))
                                            <form method="POST" action="{{ route('appointments.transition', [$appointment, 'approve']) }}" data-confirm="Approve this appointment for the selected date?" data-confirm-title="Approve appointment" data-confirm-accept="Approve">@csrf<button class="btn-small">Approve</button></form>
                                        @elseif (
                                            $capacityManagementBranchId !== (int) $appointment->branch_id
                                            || $capacityManagementDate !== $appointment->starts_at->toDateString()
                                        )
                                            <a class="btn-small" href="{{ route($isAppointmentRecordsPage ? 'clinic.appointment-records' : 'appointments.index', [
                                                'branch_id' => $appointment->branch_id ?? 'unassigned',
                                                'date' => $appointment->starts_at->toDateString(),
                                            ]) }}">Go to matching filter</a>
                                        @elseif ($capacityDailyLimit === null || $capacityUsed >= (int) $capacityDailyLimit)
                                            <button class="btn-small cursor-not-allowed opacity-60" type="button" disabled>{{ $capacityDailyLimit === null ? 'Set daily limit first' : 'Fully booked' }}</button>
                                        @else
                                            <form method="POST" action="{{ route('appointments.transition', [$appointment, 'approve']) }}" data-confirm="Approve this appointment for the selected date?" data-confirm-title="Approve appointment" data-confirm-accept="Approve">@csrf<button class="btn-small">Approve</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('appointments.transition', [$appointment, 'reject']) }}" data-confirm="Reject this appointment request? The patient will be notified." data-confirm-title="Reject appointment" data-confirm-accept="Reject" data-confirm-variant="warning">@csrf<button class="btn-danger">Reject</button></form>
                                    @elseif ($appointment->status === 'in_consultation')
                                        @if (auth()->user()->hasRole('doctor') && $isAppointmentRecordsPage && ! $appointment->dentalRecord)
                                            <a class="btn-small" href="{{ route('clinic.records.create', ['appointment' => $appointment->id, ...$appointmentListFilters]) }}">Dental record</a>
                                        @elseif (auth()->user()->hasRole('admin', 'staff') && ! $appointment->dentalRecord)
                                            <a class="btn-small" href="{{ route('clinic.records.create', ['appointment' => $appointment->id, ...$appointmentListFilters]) }}">Dental record</a>
                                        @elseif ($appointment->dentalRecord && ! $appointment->bill && (auth()->user()->hasRole('admin', 'staff') || ($isAppointmentRecordsPage && auth()->user()->hasRole('doctor'))))
                                            <a class="btn-small" href="{{ route('clinic.bills.create', $appointment) }}">Create bill</a>
                                        @endif
                                    @endif
                                    @if ($appointment->bill)
                                        <a class="btn-small" href="{{ route('clinic.billing.index', ['bill' => $appointment->bill->id]) }}#bill-{{ $appointment->bill->id }}">View bill</a>
                                    @endif
                                @endif
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->hasRole('admin', 'staff', 'doctor') ? 8 : 7 }}" class="py-10 text-center text-slate-500">{{ $isAppointmentRecordsPage ? 'No appointments in this section match these filters.' : 'No appointments match these filters.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            @if ($isAppointmentRecordsPage)
                </section>
            @endif
        @endforeach
            <div>{{ $appointments->links() }}</div>
        </div>
    </section>
    @endif
    <script>
        document.querySelectorAll('[data-appointment-schedule-form]').forEach((form) => {
            const dateInput = form.querySelector('[data-appointment-date]');
            const branchSelect = form.querySelector('[data-branch-select]');
            const availabilityMessage = form.querySelector('[data-availability-message]');
            const calendar = form.querySelector('[data-availability-calendar]');
            const dateContainer = form.querySelector('[data-appointment-date-container]');
            const errorMessage = form.querySelector('[data-sunday-error]');
            const sundayMessage = 'The Dental Clinic is closed on Sundays. Please select another date.';
            const patientBooking = form.hasAttribute('data-patient-booking');
            const doctorBooking = form.hasAttribute('data-doctor-booking');
            const paymentMethodSelect = form.querySelector('[data-patient-payment-method]');
            const gcashReceiptPanel = form.querySelector('[data-gcash-receipt-panel]');
            const paymentProofInput = form.querySelector('[data-payment-proof]');
            const selectedDateLabel = form.querySelector('[data-selected-date-label]');
            const bookingModeSelect = form.querySelector('[data-booking-mode]');
            const walkInPatientRegistrationButton = form.querySelector('[data-walk-in-patient-register]');
            const bookingTitle = form.querySelector('[data-booking-title]');
            const bookingDescription = form.querySelector('[data-booking-description]');
            const bookingSubmit = form.querySelector('[data-booking-submit]');
            const excludedAppointmentId = form.dataset.excludeAppointmentId;
            const excludedAppointmentBranchId = form.dataset.excludeAppointmentBranchId;
            const serviceSelects = form.querySelectorAll('[data-service-category]');
            const serviceTreatmentPickers = [];
            const updateGcashDepositPanel = () => {
                const isGcashSelected = patientBooking && paymentMethodSelect?.value === 'gcash';
                gcashReceiptPanel?.classList.toggle('hidden', !isGcashSelected);
                if (paymentProofInput) {
                    paymentProofInput.disabled = !isGcashSelected;
                    paymentProofInput.required = isGcashSelected;
                }
            };
            paymentMethodSelect?.addEventListener('change', updateGcashDepositPanel);
            updateGcashDepositPanel();

            serviceSelects.forEach((select, index) => {
                const picker = document.createElement('div');
                const trigger = document.createElement('button');
                const menu = document.createElement('div');
                const menuId = `appointment-treatment-options-${index}`;
                const options = Array.from(select.options).filter((option) => option.value !== '');
                const items = [];

                picker.className = 'relative';
                trigger.type = 'button';
                trigger.className = 'flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:border-teal-300 focus:border-teal-600 focus:outline-none focus:ring-4 focus:ring-teal-100/80';
                trigger.setAttribute('role', 'combobox');
                trigger.setAttribute('aria-haspopup', 'listbox');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.setAttribute('aria-controls', menuId);
                trigger.setAttribute('aria-label', `Choose a treatment from ${select.getAttribute('aria-label')}`);
                menu.id = menuId;
                menu.className = 'absolute left-0 right-0 top-full z-30 mt-1 hidden max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg';
                menu.setAttribute('role', 'listbox');
                menu.setAttribute('aria-label', `${select.getAttribute('aria-label')} treatments`);

                options.forEach((option) => {
                    const item = document.createElement('button');
                    const name = option.dataset.serviceName || option.textContent.split(' — ')[0].trim();
                    const price = option.dataset.price || option.textContent.split(' — ').slice(1).join(' — ').trim();
                    const nameLabel = document.createElement('span');
                    const priceLabel = document.createElement('span');

                    item.type = 'button';
                    item.className = 'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-teal-50 focus-visible:bg-teal-50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-label', `${name}, ${price}`);
                    item.textContent = '';
                    nameLabel.className = 'min-w-0 flex-1 text-slate-700';
                    nameLabel.textContent = name;
                    priceLabel.className = 'shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-bold text-teal-800 ring-1 ring-inset ring-teal-200';
                    priceLabel.textContent = price || 'Price to confirm';
                    item.append(nameLabel, priceLabel);
                    menu.appendChild(item);
                    items.push({ option, item });
                });

                const closeMenu = (returnFocus = false) => {
                    menu.classList.add('hidden');
                    trigger.setAttribute('aria-expanded', 'false');
                    if (returnFocus) {
                        trigger.focus();
                    }
                };
                const renderTrigger = () => {
                    const selectedOption = select.selectedOptions[0];
                    trigger.replaceChildren();

                    const label = document.createElement('span');
                    label.className = 'min-w-0 flex-1 truncate';
                    label.textContent = selectedOption?.value
                        ? selectedOption.dataset.serviceName || selectedOption.textContent.split(' — ')[0].trim()
                        : 'Choose a treatment...';

                    const chevron = document.createElement('span');
                    chevron.className = 'shrink-0 text-slate-400';
                    chevron.setAttribute('aria-hidden', 'true');
                    chevron.textContent = '▾';
                    trigger.append(label, chevron);
                };
                const openMenu = () => {
                    menu.classList.remove('hidden');
                    trigger.setAttribute('aria-expanded', 'true');
                    menu.querySelector('button:not(:disabled)')?.focus();
                };

                trigger.addEventListener('click', () => {
                    if (menu.classList.contains('hidden')) {
                        openMenu();
                    } else {
                        closeMenu();
                    }
                });
                trigger.addEventListener('keydown', (event) => {
                    if (['ArrowDown', 'Enter', ' '].includes(event.key)) {
                        event.preventDefault();
                        openMenu();
                    }
                });
                items.forEach(({ option, item }) => {
                    item.addEventListener('click', () => {
                        select.value = option.value;
                        closeMenu();
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        renderTrigger();
                    });
                    item.addEventListener('keydown', (event) => {
                        const availableItems = items.filter(({ item: candidate }) => !candidate.hidden && !candidate.disabled);
                        const itemIndex = availableItems.findIndex(({ item: candidate }) => candidate === item);
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            availableItems[itemIndex + (event.key === 'ArrowDown' ? 1 : -1)]?.item.focus();
                        } else if (event.key === 'Home' || event.key === 'End') {
                            event.preventDefault();
                            availableItems[event.key === 'Home' ? 0 : availableItems.length - 1]?.item.focus();
                        } else if (event.key === 'Escape') {
                            event.preventDefault();
                            closeMenu(true);
                        }
                    });
                });

                select.parentNode.insertBefore(picker, select);
                picker.append(trigger, menu, select);
                select.classList.add('hidden');
                select.setAttribute('aria-hidden', 'true');
                select.tabIndex = -1;
                renderTrigger();
                serviceTreatmentPickers.push({ select, picker, trigger, menu, items, closeMenu, renderTrigger });
            });

            form.addEventListener('click', (event) => {
                serviceTreatmentPickers.forEach(({ picker, closeMenu }) => {
                    if (!picker.contains(event.target)) {
                        closeMenu();
                    }
                });
            });

            const refreshServiceTreatmentPickers = () => {
                serviceTreatmentPickers.forEach(({ items, menu, closeMenu, renderTrigger }) => {
                    items.forEach(({ option, item }) => {
                        item.hidden = option.hidden;
                        item.disabled = option.disabled
                            || (patientBooking
                                && treatmentLimit > 0
                                && selectedTreatments.size >= treatmentLimit
                                && !selectedTreatments.has(option.value));
                    });
                    if (menu.contains(document.activeElement)) {
                        closeMenu();
                    }
                    renderTrigger();
                });
            };

            const patientSearch = form.querySelector('[data-patient-search]');
            const patientSelect = form.querySelector('[data-patient-select]');
            const patientSearchPanel = form.querySelector('[data-patient-search-panel]');
            const patientSearchResults = form.querySelector('[data-patient-search-results]');
            const patientSearchEmpty = form.querySelector('[data-patient-search-empty]');

            if (patientSearch && patientSelect && patientSearchPanel && patientSearchResults && patientSearchEmpty) {
                const normalizeSearch = (value) => value
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLocaleLowerCase()
                    .trim();
                const patientOptions = Array.from(patientSelect.options)
                    .filter((option) => option.value !== '')
                    .map((option) => ({
                        option,
                        label: option.dataset.patientLabel || option.textContent.trim(),
                        searchText: normalizeSearch(option.dataset.patientSearch || option.textContent),
                    }));
                const selectedOption = patientSelect.selectedOptions[0];
                if (selectedOption?.value) {
                    patientSearch.value = selectedOption.dataset.patientLabel || selectedOption.textContent.trim();
                }

                const editDistance = (first, second) => {
                    if (Math.abs(first.length - second.length) > 2) {
                        return Infinity;
                    }

                    let previousRow = Array.from({ length: second.length + 1 }, (_, index) => index);
                    for (let firstIndex = 1; firstIndex <= first.length; firstIndex += 1) {
                        const currentRow = [firstIndex];
                        for (let secondIndex = 1; secondIndex <= second.length; secondIndex += 1) {
                            currentRow[secondIndex] = Math.min(
                                currentRow[secondIndex - 1] + 1,
                                previousRow[secondIndex] + 1,
                                previousRow[secondIndex - 1] + Number(first[firstIndex - 1] !== second[secondIndex - 1]),
                            );
                        }
                        previousRow = currentRow;
                    }

                    return previousRow[second.length];
                };
                const matchesPatient = (query, candidate) => {
                    const terms = normalizeSearch(query).split(/\s+/).filter(Boolean);
                    if (terms.length === 0) {
                        return false;
                    }

                    const candidateWords = candidate.searchText.split(/\s+/);
                    return terms.every((term) => {
                        if (candidate.searchText.includes(term)) {
                            return true;
                        }
                        if (term.length < 4) {
                            return false;
                        }

                        const maxDistance = term.length >= 8 ? 2 : 1;
                        return candidateWords.some((word) => (
                            Math.abs(word.length - term.length) <= maxDistance
                            && editDistance(term, word) <= maxDistance
                        ));
                    });
                };
                const closePatientSearch = () => {
                    patientSearchPanel.classList.add('hidden');
                    patientSearch.setAttribute('aria-expanded', 'false');
                };
                const choosePatient = (candidate) => {
                    patientSelect.value = candidate.option.value;
                    patientSearch.value = candidate.label;
                    patientSearch.setCustomValidity('');
                    closePatientSearch();
                };
                const patientRegistrationDialog = document.querySelector('[data-walk-in-patient-dialog]');
                const patientRegistrationForm = patientRegistrationDialog?.querySelector('[data-walk-in-patient-form]');

                if (walkInPatientRegistrationButton && patientRegistrationDialog && patientRegistrationForm) {
                    const patientRegistrationError = patientRegistrationForm.querySelector('[data-patient-registration-error]');
                    const patientRegistrationBranch = patientRegistrationForm.querySelector('[data-patient-registration-branch]');
                    const patientRegistrationBranchHint = form.querySelector('[data-patient-registration-branch-hint]');
                    const patientRegistrationSubmit = patientRegistrationForm.querySelector('[data-patient-registration-submit]');
                    const requiresSelectedBranch = walkInPatientRegistrationButton.dataset.requiresBranch === 'true';
                    const updatePatientRegistrationBranch = () => {
                        if (requiresSelectedBranch && patientRegistrationBranch) {
                            patientRegistrationBranch.value = branchSelect?.value || '';
                            walkInPatientRegistrationButton.disabled = !patientRegistrationBranch.value;
                            patientRegistrationBranchHint?.classList.toggle('hidden', Boolean(patientRegistrationBranch.value));
                            walkInPatientRegistrationButton.title = patientRegistrationBranch.value
                                ? 'Register walk-in patient'
                                : 'Choose a branch before registering a patient';
                        }
                    };
                    const showPatientRegistrationError = (message) => {
                        patientRegistrationError.textContent = message;
                        patientRegistrationError.classList.remove('hidden');
                    };

                    updatePatientRegistrationBranch();
                    branchSelect?.addEventListener('change', updatePatientRegistrationBranch);
                    walkInPatientRegistrationButton.addEventListener('click', () => {
                        if (doctorBooking && bookingModeSelect?.value !== 'walk_in') {
                            return;
                        }

                        updatePatientRegistrationBranch();
                        patientRegistrationError.textContent = '';
                        patientRegistrationError.classList.add('hidden');
                        patientRegistrationDialog.showModal();
                        patientRegistrationForm.querySelector('[name="name"]')?.focus();
                    });
                    patientRegistrationDialog.querySelectorAll('[data-walk-in-patient-close]').forEach((button) => {
                        button.addEventListener('click', () => patientRegistrationDialog.close());
                    });
                    patientRegistrationDialog.addEventListener('click', (event) => {
                        if (event.target === patientRegistrationDialog) {
                            patientRegistrationDialog.close();
                        }
                    });
                    patientRegistrationForm.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        patientRegistrationError.textContent = '';
                        patientRegistrationError.classList.add('hidden');
                        patientRegistrationSubmit.disabled = true;
                        patientRegistrationSubmit.textContent = 'Creating account...';

                        try {
                            const response = await fetch(patientRegistrationForm.action, {
                                method: 'POST',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: new FormData(patientRegistrationForm),
                                credentials: 'same-origin',
                            });
                            const result = await response.json();

                            if (!response.ok) {
                                const validationMessage = Object.values(result.errors || {}).flat()[0];
                                showPatientRegistrationError(validationMessage || result.message || 'The patient account could not be created.');
                                return;
                            }

                            if (!result.patient || result.patient.id === undefined || !result.patient.label) {
                                showPatientRegistrationError('The patient account was created, but could not be added to this appointment. Refresh and search for the patient.');
                                return;
                            }

                            const option = document.createElement('option');
                            option.value = String(result.patient.id);
                            option.textContent = result.patient.label;
                            option.dataset.patientLabel = result.patient.label;
                            option.dataset.patientSearch = result.patient.search || result.patient.label;
                            patientSelect.append(option);
                            choosePatient({ option, label: result.patient.label });
                            patientRegistrationForm.reset();
                            patientRegistrationDialog.close();
                        } catch {
                            showPatientRegistrationError('A network error prevented patient registration. Check your connection and try again.');
                        } finally {
                            patientRegistrationSubmit.disabled = false;
                            patientRegistrationSubmit.textContent = 'Create patient account';
                        }
                    });
                }
                const renderPatientMatches = () => {
                    const query = patientSearch.value.trim();
                    const selectedLabel = patientSelect.selectedOptions[0]?.dataset.patientLabel;
                    if (!query || (selectedLabel && normalizeSearch(selectedLabel) === normalizeSearch(query))) {
                        closePatientSearch();
                        return;
                    }

                    const matches = patientOptions
                        .filter((candidate) => matchesPatient(query, candidate))
                        .sort((first, second) => {
                            const normalizedQuery = normalizeSearch(query);
                            const firstScore = first.searchText.startsWith(normalizedQuery) ? 0
                                : first.searchText.includes(normalizedQuery) ? 1 : 2;
                            const secondScore = second.searchText.startsWith(normalizedQuery) ? 0
                                : second.searchText.includes(normalizedQuery) ? 1 : 2;
                            return firstScore - secondScore || first.label.localeCompare(second.label);
                        })
                        .slice(0, 8);

                    patientSearchResults.replaceChildren();
                    patientSearchEmpty.classList.toggle('hidden', matches.length > 0);
                    matches.forEach((candidate) => {
                        const result = document.createElement('button');
                        result.type = 'button';
                        result.className = 'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-teal-50 focus-visible:bg-teal-50 focus-visible:outline-none';
                        result.setAttribute('role', 'option');
                        result.setAttribute('aria-selected', String(patientSelect.value === candidate.option.value));

                        const label = document.createElement('span');
                        label.className = 'font-medium text-slate-800';
                        label.textContent = candidate.option.dataset.patientLabel || candidate.label;
                        result.appendChild(label);
                        result.addEventListener('click', () => choosePatient(candidate));
                        result.addEventListener('keydown', (event) => {
                            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                                event.preventDefault();
                                const results = Array.from(patientSearchResults.querySelectorAll('[role="option"]'));
                                const index = results.indexOf(result);
                                const nextResult = results[index + (event.key === 'ArrowDown' ? 1 : -1)];
                                (nextResult || (event.key === 'ArrowUp' ? patientSearch : null))?.focus();
                            } else if (event.key === 'Escape') {
                                event.preventDefault();
                                closePatientSearch();
                                patientSearch.focus();
                            }
                        });
                        patientSearchResults.appendChild(result);
                    });

                    patientSearchPanel.classList.remove('hidden');
                    patientSearch.setAttribute('aria-expanded', 'true');
                };

                patientSearch.setCustomValidity(patientSelect.value ? '' : 'Choose a patient from the matching results.');
                patientSearch.addEventListener('input', () => {
                    const selectedLabel = patientSelect.selectedOptions[0]?.dataset.patientLabel;
                    if (!selectedLabel || normalizeSearch(selectedLabel) !== normalizeSearch(patientSearch.value)) {
                        patientSelect.value = '';
                    }
                    patientSearch.setCustomValidity(patientSelect.value ? '' : 'Choose a patient from the matching results.');
                    renderPatientMatches();
                });
                patientSearch.addEventListener('focus', renderPatientMatches);
                patientSearch.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowDown' && !patientSearchPanel.classList.contains('hidden')) {
                        event.preventDefault();
                        patientSearchResults.querySelector('[role="option"]')?.focus();
                    } else if (event.key === 'Enter' && !patientSearchPanel.classList.contains('hidden')) {
                        const firstResult = patientSearchResults.querySelector('[role="option"]');
                        if (firstResult) {
                            event.preventDefault();
                            firstResult.click();
                        }
                    } else if (event.key === 'Escape') {
                        closePatientSearch();
                    }
                });
                document.addEventListener('click', (event) => {
                    if (!patientSearchPanel.parentElement.contains(event.target)) {
                        closePatientSearch();
                    }
                });
            }

            let availability = {};
            let holidays = {};
            let fullDates = {};
            let currentMonth = dateInput?.value
                ? dateInput.value.slice(0, 7)
                : form.dataset.currentMonth;
            let availabilityRequest = 0;
            const isDateOnlyMode = () => patientBooking
                || (doctorBooking && bookingModeSelect?.value === 'appointment')
                || Boolean(excludedAppointmentId);

            const updateBookingMode = () => {
                if (!doctorBooking || !bookingModeSelect) {
                    return;
                }

                const isAppointment = bookingModeSelect.value === 'appointment';
                dateContainer?.classList.toggle('hidden', !isAppointment);
                if (dateInput) {
                    dateInput.disabled = !isAppointment;
                    if (!isAppointment) {
                        dateInput.value = '';
                    }
                }
                walkInPatientRegistrationButton?.classList.toggle('hidden', isAppointment);

                if (bookingTitle) {
                    bookingTitle.textContent = 'Register an appointment';
                }
                if (bookingDescription) {
                    const isCrossBranchAppointment = isAppointment
                        && branchSelect.value !== form.dataset.doctorBranchId;
                    bookingDescription.textContent = isCrossBranchAppointment
                        ? 'Choose a patient, branch, service, and date. The destination branch must approve this request.'
                        : (isAppointment
                            ? 'Choose a patient, branch, service, and date. The front desk will review the request; no time slot is assigned.'
                            : 'Choose a patient and service. Walk-ins join the waiting queue immediately.');
                }
                if (bookingSubmit) {
                    const isCrossBranchAppointment = isAppointment
                        && branchSelect.value !== form.dataset.doctorBranchId;
                    bookingSubmit.textContent = isCrossBranchAppointment
                        ? 'Submit for approval'
                        : (isAppointment ? 'Submit for approval' : 'Add walk-in to queue');
                }

                form.dataset.scheduleConfirm = isAppointment
                    ? (branchSelect.value !== form.dataset.doctorBranchId
                        ? 'Submit this appointment date to the destination branch for approval?'
                        : 'Submit this appointment date for front-desk approval?')
                    : 'Register this walk-in appointment and add it to the queue?';
                form.dataset.scheduleConfirmed = 'false';
            };

            updateBookingMode();

            const formatMonth = (date) => (
                `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
            );

            const updateSundayValidation = () => {
                if (!dateInput) {
                    return;
                }
                const isSunday = dateInput.value !== ''
                    && new Date(`${dateInput.value}T12:00:00`).getDay() === 0;

                if (dateInput.type !== 'hidden') {
                    dateInput.setCustomValidity(isSunday ? sundayMessage : '');
                }
                errorMessage?.classList.toggle('hidden', !isSunday);
            };

            const syncSelectedDateState = () => {
                if (
                    dateInput?.value
                    && form.dataset.availabilityLoaded === 'true'
                    && availability[dateInput.value] !== true
                ) {
                    dateInput.value = '';
                }

                if (selectedDateLabel) {
                    selectedDateLabel.textContent = dateInput?.value
                        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'long' })
                            .format(new Date(`${dateInput.value}T12:00:00`))
                        : 'Choose a date';
                }
            };

            const updateDateMessage = () => {
                if (!availabilityMessage) {
                    return;
                }

                availabilityMessage.classList.remove('hidden');
                if (!isDateOnlyMode()) {
                    availabilityMessage.classList.add('hidden');
                } else if (form.dataset.availabilityLoaded !== 'true') {
                    availabilityMessage.textContent = 'Loading available clinic dates…';
                } else if (!dateInput?.value) {
                    availabilityMessage.textContent = 'Choose an available clinic date.';
                } else if (fullDates[dateInput.value]) {
                    availabilityMessage.textContent = 'The daily approval limit has been reached for this date. Choose another clinic date.';
                } else if (availability[dateInput.value] !== true) {
                    availabilityMessage.textContent = holidays[dateInput.value]
                        ? `The clinic is closed for ${holidays[dateInput.value]}. Choose another date.`
                        : 'This date is unavailable. Choose another clinic date.';
                } else if (excludedAppointmentId) {
                    availabilityMessage.textContent = 'This clinic date is available.';
                } else {
                    availabilityMessage.textContent = 'The front desk will review requests and decide how many appointments to approve for this date.';
                }
            };

            const selectedTreatments = new Map();
            const serviceBrowserToggle = form.querySelector('[data-service-browser-toggle]');
            const serviceBrowser = form.querySelector('[data-service-browser]');
            const serviceBrowserLabel = form.querySelector('[data-service-browser-label]');
            const serviceBrowserChevron = form.querySelector('[data-service-browser-chevron]');
            const serviceBrowserEmpty = form.querySelector('[data-service-browser-empty]');
            const serviceMessage = form.querySelector('[data-service-message]');
            const serviceCategoryPicker = form.querySelector('[data-service-category-picker]');
            const serviceCategoryCards = Array.from(form.querySelectorAll('[data-service-category-card]'));
            const treatmentsList = form.querySelector('[data-selected-treatments-list]');
            const countBadge = form.querySelector('[data-selected-count-badge]');
            const noTreatmentsMsg = form.querySelector('[data-no-treatments-message]');
            const treatmentLimit = Number(form.dataset.maxTreatments || 0);
            const treatmentLimitMessage = form.querySelector('[data-treatment-limit-message]');
            const combinedCostEl = form.querySelector('[data-combined-estimated-cost]');
            const combinedBreakdownEl = form.querySelector('[data-combined-breakdown]');
            const hiddenContainer = form.querySelector('[data-service-ids-container]');
            const formatEstimate = (amount) => `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const setServiceBrowserOpen = (isOpen) => {
                serviceBrowser?.classList.toggle('hidden', !isOpen);
                serviceBrowserToggle?.setAttribute('aria-expanded', String(isOpen));
                serviceBrowserChevron?.classList.toggle('rotate-180', isOpen);
                if (serviceBrowserLabel) {
                    serviceBrowserLabel.textContent = isOpen
                        ? 'Hide services'
                        : (selectedTreatments.size > 0 ? 'Add another service' : 'Browse services');
                }
            };

            serviceBrowserToggle?.addEventListener('click', () => {
                setServiceBrowserOpen(serviceBrowserToggle.getAttribute('aria-expanded') !== 'true');
            });
            const updateVisibleServiceCategory = () => {
                serviceCategoryCards.forEach((categoryCard) => {
                    const isSelectedCategory = categoryCard.dataset.categoryIndex === serviceCategoryPicker?.value
                        && categoryCard.dataset.available === 'true';
                    categoryCard.classList.toggle('hidden', !isSelectedCategory);
                });
            };

            serviceCategoryPicker?.addEventListener('change', updateVisibleServiceCategory);

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
                const hasReachedTreatmentLimit = patientBooking && treatmentLimit > 0 && count >= treatmentLimit;
                treatmentLimitMessage?.classList.toggle('hidden', !hasReachedTreatmentLimit);
                if (serviceBrowserLabel) {
                    serviceBrowserLabel.textContent = serviceBrowserToggle?.getAttribute('aria-expanded') === 'true'
                        ? 'Hide services'
                        : (count > 0 ? 'Add another service' : 'Browse services');
                }
                if (countBadge) {
                    countBadge.textContent = `${count} treatment${count === 1 ? '' : 's'} selected`;
                }

                serviceSelects.forEach((select) => {
                    const categoryCard = select.closest('[data-service-category-card]');
                    const activeBadge = categoryCard?.querySelector('[data-category-active]');
                    const selectedServices = categoryCard?.querySelector('[data-category-selections]');
                    const catOptions = Array.from(select.options).map((o) => o.value).filter(Boolean);
                    const selectedCategoryTreatments = catOptions
                        .map((id) => selectedTreatments.get(String(id)))
                        .filter(Boolean);
                    const catSelectedCount = selectedCategoryTreatments.length;

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
                    serviceTreatmentPickers.find((picker) => picker.select === select)?.renderTrigger();
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

                refreshServiceTreatmentPickers();
            };

            const filterServicesForBranch = (clearSelection = false) => {
                const branchId = branchSelect?.value ?? '';

                if (clearSelection) {
                    selectedTreatments.clear();
                }

                serviceSelects.forEach((select) => {
                    let availableCount = 0;
                    Array.from(select.options).forEach((option) => {
                        if (!option.value) {
                            option.hidden = false;
                            option.disabled = false;
                            return;
                        }

                        const isAvailable = branchId !== ''
                            && (option.dataset.branchIds || '').split(',').includes(branchId);
                        option.hidden = !isAvailable;
                        option.disabled = !isAvailable;
                        availableCount += Number(isAvailable);

                        if (!isAvailable && select.value === option.value) {
                            select.value = '';
                        }
                    });

                    const categoryCard = select.closest('[data-service-category-card]');
                    if (categoryCard) {
                        const categoryAvailable = branchId !== '' && availableCount > 0;
                        categoryCard.dataset.available = String(categoryAvailable);
                        const categoryOption = Array.from(serviceCategoryPicker?.options || [])
                            .find((option) => option.value === categoryCard.dataset.categoryIndex);
                        if (categoryOption) {
                            categoryOption.disabled = !categoryAvailable;
                        }
                    }
                });

                refreshServiceTreatmentPickers();

                if (serviceCategoryPicker) {
                    if (serviceCategoryPicker.selectedOptions[0]?.disabled) {
                        serviceCategoryPicker.value = '';
                    }
                    serviceCategoryPicker.disabled = !Array.from(serviceCategoryPicker.options)
                        .some((option) => option.value !== '' && !option.disabled);
                    updateVisibleServiceCategory();
                }

                if (serviceBrowserEmpty) {
                    const hasAvailableCategory = serviceCategoryCards
                        .some((categoryCard) => categoryCard.dataset.available === 'true');
                    serviceBrowserEmpty.textContent = branchId
                        ? 'No services are available for this branch.'
                        : 'Choose a branch to view its available services.';
                    serviceBrowserEmpty.classList.toggle('hidden', hasAvailableCategory);
                }

                if (clearSelection) {
                    if (serviceCategoryPicker) {
                        serviceCategoryPicker.value = '';
                    }
                    updateVisibleServiceCategory();
                    serviceSelects.forEach((select) => {
                        select.value = '';
                    });
                    renderSelectedTreatments();
                }
            };

            const addTreatment = (serviceId) => {
                if (!serviceId || selectedTreatments.has(String(serviceId))) {
                    return;
                }
                if (patientBooking && treatmentLimit > 0 && selectedTreatments.size >= treatmentLimit) {
                    treatmentLimitMessage?.classList.remove('hidden');
                    setServiceBrowserOpen(true);
                    return;
                }
                const opt = getServiceOption(serviceId);
                if (!opt) {
                    return;
                }

                serviceMessage?.classList.add('hidden');
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
                    const holidayName = holidays[date];
                    const isAvailable = availability[date] === true;
                    const isFull = fullDates[date] === true;
                    const button = document.createElement('button');
                    const isSelected = dateInput.value === date;
                    button.type = 'button';
                    button.disabled = !isAvailable;
                    button.className = isAvailable
                        ? 'calendar-day calendar-day-available'
                        : isFull
                            ? 'calendar-day calendar-day-full'
                            : holidayName
                            ? 'calendar-day calendar-day-holiday'
                            : 'calendar-day calendar-day-unavailable';
                    if (isAvailable && isSelected) {
                        button.classList.add('calendar-day-selected');
                    }
                    button.textContent = day;
                    button.setAttribute('aria-pressed', String(isSelected));
                    button.setAttribute(
                        'aria-label',
                        `${new Intl.DateTimeFormat(undefined, { dateStyle: 'full' }).format(new Date(`${date}T12:00:00`))}: ${isFull ? 'fully booked; daily approval limit reached' : holidayName ? `${holidayName}; clinic closed` : isAvailable ? 'available for appointment requests' : 'unavailable'}`,
                    );
                    if (isFull) {
                        button.title = 'Fully booked — daily approval limit reached';
                    } else if (holidayName) {
                        button.title = `${holidayName} — clinic closed`;
                    }
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
                currentMonth = month;
                availability = {};
                holidays = {};
                fullDates = {};
                form.dataset.availabilityLoaded = 'false';
                if (availabilityMessage) {
                    availabilityMessage.classList.remove('hidden');
                    availabilityMessage.textContent = 'Loading available clinic dates…';
                }
                updateDateMessage();
                renderCalendar();

                const url = new URL(form.dataset.availabilityUrl, window.location.origin);
                url.searchParams.set('month', month);
                if (!branchSelect?.value) {
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Choose a branch to see its available clinic dates.';
                    }
                    return false;
                }
                url.searchParams.set('branch_id', branchSelect.value);
                if (
                    excludedAppointmentId
                    && branchSelect.value === excludedAppointmentBranchId
                ) {
                    url.searchParams.set('exclude_appointment_id', excludedAppointmentId);
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
                    holidays = result.holidays || {};
                    fullDates = result.full_dates || {};
                    currentMonth = result.month;
                    form.dataset.availabilityLoaded = 'true';
                    syncSelectedDateState();
                    renderCalendar();
                    updateDateMessage();
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

            dateInput?.addEventListener('change', () => {
                updateSundayValidation();
                syncSelectedDateState();
                renderCalendar();
                updateDateMessage();
            });
            branchSelect?.addEventListener('change', () => {
                filterServicesForBranch(true);
                updateBookingMode();
                if (isDateOnlyMode()) {
                    loadAvailability(dateInput?.value ? dateInput.value.slice(0, 7) : currentMonth);
                }
            });
            serviceSelects.forEach((select) => select.addEventListener('change', () => {
                if (select.value) {
                    addTreatment(select.value);
                }
            }));
            bookingModeSelect?.addEventListener('change', () => {
                updateBookingMode();
                if (isDateOnlyMode()) {
                    loadAvailability(dateInput?.value ? dateInput.value.slice(0, 7) : currentMonth);
                } else {
                    availabilityMessage?.classList.add('hidden');
                }
            });
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
                    if (serviceMessage) {
                        serviceMessage.textContent = branchSelect?.value
                            ? 'Choose one or more treatments before continuing.'
                            : 'Choose a branch and one or more treatments before continuing.';
                        serviceMessage.classList.remove('hidden');
                    }
                    setServiceBrowserOpen(true);
                    if (serviceCategoryPicker && !serviceCategoryPicker.value) {
                        const firstAvailableCategory = Array.from(serviceCategoryPicker.options)
                            .find((option) => option.value !== '' && !option.disabled);
                        if (firstAvailableCategory) {
                            serviceCategoryPicker.value = firstAvailableCategory.value;
                            updateVisibleServiceCategory();
                        }
                    }
                    const availableSelect = Array.from(serviceSelects).find((select) => (
                        !select.closest('[data-service-category-card]')?.classList.contains('hidden')
                    ));
                    const availablePicker = serviceTreatmentPickers.find((picker) => picker.select === availableSelect);
                    (availablePicker?.trigger || branchSelect)?.focus();
                    return;
                }

                const scheduledDoctorAppointment = doctorBooking && bookingModeSelect?.value === 'appointment';
                const requiresDate = patientBooking || scheduledDoctorAppointment || Boolean(excludedAppointmentId);
                if (requiresDate && !dateInput?.value) {
                    if (availabilityMessage) {
                        availabilityMessage.classList.remove('hidden');
                        availabilityMessage.textContent = 'Choose an available clinic date to continue.';
                    }
                    (calendar?.querySelector('.calendar-day-available') || dateInput)?.focus();
                    return;
                }

                if (!form.reportValidity()) {
                    return;
                }

                if (requiresDate) {
                    if (!await loadAvailability(dateInput.value.slice(0, 7))) {
                        return;
                    }

                    if (availability[dateInput.value] !== true) {
                        if (availabilityMessage) {
                            availabilityMessage.classList.remove('hidden');
                            availabilityMessage.textContent = 'That clinic date is no longer available. Choose another date.';
                        }
                        dateInput.focus();
                        return;
                    }
                }

                if (form.hasAttribute('data-schedule-confirm') && form.dataset.scheduleConfirmed !== 'true') {
                    const confirmed = await window.showAppConfirm(form.dataset.scheduleConfirm, {
                        title: form.dataset.scheduleConfirmTitle,
                        confirmLabel: patientBooking
                            ? 'Submit request'
                            : excludedAppointmentId
                                ? 'Save date'
                                : scheduledDoctorAppointment
                                    ? 'Submit for approval'
                                    : 'Register walk-in',
                        variant: 'warning',
                    });

                    if (!confirmed) {
                        return;
                    }

                    form.dataset.scheduleConfirmed = 'true';
                }

                form.dataset.submittingAfterAvailabilityRefresh = 'true';
                form.requestSubmit(event.submitter);
            });
            updateSundayValidation();
            serviceSelects.forEach((select) => {
                Array.from(select.options).forEach((opt) => {
                    if (opt.selected && opt.value) {
                        const categoryCard = select.closest('[data-service-category-card]');
                        if (serviceCategoryPicker && categoryCard) {
                            serviceCategoryPicker.value = categoryCard.dataset.categoryIndex;
                        }
                        addTreatment(opt.value);
                    }
                });
            });
            filterServicesForBranch();
            renderSelectedTreatments();
            if (isDateOnlyMode()) {
                loadAvailability(currentMonth);
            }
        });
    </script>
@endsection
