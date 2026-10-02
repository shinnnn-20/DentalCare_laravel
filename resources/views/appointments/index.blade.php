@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">{{ auth()->user()->role === 'patient' ? 'Patient portal' : 'Clinic operations' }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Appointments</h1>
        <p class="mt-2 text-slate-500">{{ auth()->user()->role === 'patient' ? 'Request an appointment and track its approval status.' : 'Review online bookings, add walk-ins, and manage the clinic schedule.' }}</p>
    </div>

    @if (auth()->user()->role === 'patient' || auth()->user()->hasRole('admin', 'staff'))
        <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <h2 class="text-lg font-semibold">{{ auth()->user()->role === 'patient' ? 'Request an appointment' : 'Register a walk-in' }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->role === 'patient' ? 'Requests start as pending and require clinic approval.' : 'Walk-ins are placed in the waiting queue immediately.' }}</p>
            <form method="POST" action="{{ route('appointments.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-appointment-schedule-form>
                @csrf
                @if (auth()->user()->hasRole('admin', 'staff'))
                    <label class="form-label">Patient
                        <select name="patient_id" class="form-input" required><option value="">Select patient</option>@foreach ($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->patient_number }} · {{ $patient->user->name }}</option>@endforeach</select>
                    </label>
                @endif
                <label class="form-label">Service
                    <select name="service_id" class="form-input" required><option value="">Select service</option>@foreach ($services as $service)<option value="{{ $service->id }}">{{ $service->name }} · ₱{{ number_format((float) $service->price, 2) }}</option>@endforeach</select>
                </label>
                <label class="form-label">Date
                    <input class="form-input" type="date" name="appointment_date" min="{{ today()->toDateString() }}" value="{{ old('appointment_date') }}" data-appointment-date @required(auth()->user()->role === 'patient')>
                </label>
                <label class="form-label">Time
                    <select class="form-input" name="appointment_time" @required(auth()->user()->role === 'patient')>
                        <option value="">Select time</option>
                        @foreach ($appointmentTimes as $timeOption)
                            <option value="{{ $timeOption }}" @selected(old('appointment_time') === $timeOption)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $timeOption)->format('g:i A') }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="hidden text-sm font-medium text-rose-700 sm:col-span-2 lg:col-span-4" data-sunday-error role="alert">The Dental Clinic is closed on Sundays. Please select another date.</p>
                <label class="form-label">Notes <span class="font-normal text-slate-400">(optional)</span><input class="form-input" name="notes" maxlength="2000" placeholder="Reason for visit"></label>
                <button class="btn-primary sm:col-span-2 lg:col-span-4">{{ auth()->user()->role === 'patient' ? 'Submit request' : 'Add walk-in to queue' }}</button>
            </form>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white">
        <form method="GET" class="flex flex-wrap gap-3 border-b border-slate-100 p-4">
            @if (auth()->user()->hasRole('admin', 'staff'))
                <label class="form-label">Date<input class="form-input" type="date" name="date" value="{{ request('date') }}"></label>
            @endif
            <label class="form-label">Status
                <select class="form-input" name="status"><option value="">All statuses</option>@foreach (['pending', 'approved', 'checked_in', 'waiting', 'in_consultation', 'billed', 'completed', 'cancelled', 'no_show'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>
            </label>
            <button class="btn-secondary self-end">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Date & time</th>@if (auth()->user()->hasRole('admin', 'staff'))<th>Patient</th>@endif<th>Service</th><th>Type</th><th>Status</th><th>Queue</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td data-label="Date & time" class="whitespace-nowrap">{{ $appointment->starts_at->format('M j, Y · g:i A') }}</td>
                        @if (auth()->user()->hasRole('admin', 'staff'))<td data-label="Patient"><a class="font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $appointment->patient) }}">{{ $appointment->patient->user->name }}</a><div class="text-xs text-slate-400">{{ $appointment->patient->patient_number }}</div></td>@endif
                        <td data-label="Service">{{ $appointment->service->name }}</td><td data-label="Type" class="capitalize">{{ str_replace('_', ' ', $appointment->type) }}</td>
                        <td data-label="Status"><span class="status-badge capitalize">{{ str_replace('_', ' ', $appointment->status) }}</span></td>
                        <td data-label="Queue">{{ $appointment->queueEntry?->queue_number ?? '—' }}</td>
                        <td data-label="Actions"><div class="flex flex-wrap gap-2">
                            @if (auth()->user()->role === 'patient' && in_array($appointment->status, ['pending', 'approved'], true))
                                <form method="POST" action="{{ route('appointments.transition', [$appointment, 'cancel']) }}">@csrf<button class="btn-small text-rose-700">Cancel</button></form>
                            @elseif (auth()->user()->hasRole('admin', 'staff'))
                                @if (in_array($appointment->status, ['pending', 'approved'], true))
                                    <form method="POST" action="{{ route('clinic.appointments.reschedule', $appointment) }}" class="flex min-w-0 flex-wrap gap-1" data-appointment-schedule-form>
                                        @csrf @method('PATCH')
                                        <input class="form-input w-full min-w-0 sm:w-auto sm:min-w-40" type="date" name="appointment_date" min="{{ today()->toDateString() }}" value="{{ old('appointment_date', $appointment->starts_at->format('Y-m-d')) }}" data-appointment-date required>
                                        <select class="form-input w-full min-w-0 sm:w-auto sm:min-w-40" name="appointment_time" required>
                                            @php($currentTime = $appointment->starts_at->format('H:i'))
                                            <option value="">Select time</option>
                                            @foreach ($appointmentTimes as $timeOption)
                                                <option value="{{ $timeOption }}" @selected(old('appointment_time', $currentTime) === $timeOption)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $timeOption)->format('g:i A') }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn-small text-teal-800">Reschedule</button>
                                    </form>
                                    <p class="hidden text-sm font-medium text-rose-700" data-sunday-error role="alert">The Dental Clinic is closed on Sundays. Please select another date.</p>
                                @endif
                                @if ($appointment->status === 'pending')
                                    <form method="POST" action="{{ route('appointments.transition', [$appointment, 'approve']) }}">@csrf<button class="btn-small text-teal-800">Approve</button></form>
                                    <form method="POST" action="{{ route('appointments.transition', [$appointment, 'reject']) }}">@csrf<button class="btn-small text-rose-700">Reject</button></form>
                                @elseif ($appointment->status === 'approved')
                                    <form method="POST" action="{{ route('appointments.transition', [$appointment, 'check-in']) }}">@csrf<button class="btn-small text-teal-800">Check in</button></form>
                                @elseif ($appointment->status === 'in_consultation')
                                    @if (auth()->user()->role === 'admin' && ! $appointment->dentalRecord)
                                        <a class="btn-small text-teal-800" href="{{ route('clinic.records.create', $appointment) }}">Dental record</a>
                                    @elseif ($appointment->dentalRecord && ! $appointment->bill)
                                        <a class="btn-small text-teal-800" href="{{ route('clinic.bills.create', $appointment) }}">Create bill</a>
                                    @endif
                                @endif
                                @if ($appointment->status === 'billed' && $appointment->bill && auth()->user()->hasRole('admin', 'staff') && $appointment->bill->payment_status === 'paid')
                                    <form method="POST" action="{{ route('appointments.transition', [$appointment, 'complete']) }}">@csrf<button class="btn-small text-teal-800">Complete</button></form>
                                @endif
                                @if ($appointment->bill)
                                    <a class="btn-small text-teal-800" href="{{ route('clinic.billing.index', ['bill' => $appointment->bill->id]) }}#bill-{{ $appointment->bill->id }}">View bill</a>
                                @endif
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">No appointments match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $appointments->links() }}</div>
    </section>
    <script>
        document.querySelectorAll('[data-appointment-schedule-form]').forEach((form) => {
            const dateInput = form.querySelector('[data-appointment-date]');
            const errorMessage = form.nextElementSibling?.matches('[data-sunday-error]')
                ? form.nextElementSibling
                : form.querySelector('[data-sunday-error]');
            const sundayMessage = 'The Dental Clinic is closed on Sundays. Please select another date.';

            const updateSundayValidation = () => {
                const isSunday = dateInput.value !== ''
                    && new Date(`${dateInput.value}T12:00:00`).getDay() === 0;

                dateInput.setCustomValidity(isSunday ? sundayMessage : '');
                errorMessage?.classList.toggle('hidden', !isSunday);
            };

            dateInput.addEventListener('change', updateSundayValidation);
            form.addEventListener('submit', (event) => {
                updateSundayValidation();

                if (!form.reportValidity()) {
                    event.preventDefault();
                }
            });
            updateSundayValidation();
        });
    </script>
@endsection
