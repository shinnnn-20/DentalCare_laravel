@extends('layouts.app')

@section('title', 'Clinic overview')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-teal-700">{{ now()->format('l, F j, Y') }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Clinic overview</h1>
            <p class="mt-2 text-slate-500">A quick view of today's clinic activity.</p>
        </div>
        <a href="{{ route('appointments.index') }}" class="btn-primary">View appointments</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($metrics as [$label, $value, $hint])
        <article class="stat-card">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold text-slate-900">{{ $value }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $hint }}</p>
            </article>
        @endforeach
    </div>

    @if ($isAdmin)
        <section class="mt-8" data-admin-statistics>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Clinic statistics</h2>
                    <p class="mt-1 text-sm text-slate-500">Appointments and recorded payments · {{ $statistics['rangeLabel'] }}</p>
                </div>
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2">
                    <label class="form-label">Statistics period
                        <select class="form-input min-w-40" name="statistics_period">
                            <option value="week" @selected($statistics['period'] === 'week')>Last 7 days</option>
                            <option value="month" @selected($statistics['period'] === 'month')>Last 30 days</option>
                            <option value="year" @selected($statistics['period'] === 'year')>Last 12 months</option>
                        </select>
                    </label>
                    <button class="btn-primary" type="submit">Update graphs</button>
                </form>
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5" data-appointments-trend>
                    <h3 class="font-semibold text-slate-900">Appointments over time</h3>
                    <p class="mt-1 text-sm text-slate-500">Scheduled appointments by day or month.</p>
                    <div class="mt-5 flex h-44 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Appointment counts by date">
                        @foreach ($statistics['timeline'] as $point)
                            <div class="flex h-full min-w-9 flex-1 flex-col items-center justify-end gap-1">
                                <span class="text-xs font-semibold text-slate-700">{{ $point['appointments'] }}</span>
                                <div class="flex h-28 w-full items-end rounded-t-md bg-slate-50">
                                    <div class="w-full rounded-t-md bg-teal-600" style="height: {{ $point['appointments'] / $statistics['appointmentMaximum'] * 100 }}%"></div>
                                </div>
                                <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5" data-revenue-trend>
                    <h3 class="font-semibold text-slate-900">Revenue collected</h3>
                    <p class="mt-1 text-sm text-slate-500">Applied payment amounts recorded during the selected period.</p>
                    <div class="mt-5 flex h-44 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Applied payments by date">
                        @foreach ($statistics['timeline'] as $point)
                            <div class="flex h-full min-w-9 flex-1 flex-col items-center justify-end gap-1">
                                <span class="whitespace-nowrap text-[0.65rem] font-semibold text-slate-700">₱{{ number_format($point['revenue'], 0) }}</span>
                                <div class="flex h-28 w-full items-end rounded-t-md bg-slate-50">
                                    <div class="w-full rounded-t-md bg-cyan-600" style="height: {{ $point['revenue'] / $statistics['revenueMaximum'] * 100 }}%"></div>
                                </div>
                                <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 xl:col-span-2" data-appointment-statuses>
                    <h3 class="font-semibold text-slate-900">Appointment status distribution</h3>
                    <p class="mt-1 text-sm text-slate-500">Statuses currently recorded for appointments in the selected period.</p>
                    @forelse ($statistics['statuses'] as $status)
                        <div class="mt-4 grid grid-cols-[minmax(7rem,1fr)_minmax(0,3fr)_auto] items-center gap-3">
                            <span class="text-sm font-medium capitalize text-slate-700">{{ $status['label'] }}</span>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-teal-600" style="width: {{ $status['percentage'] }}%"></div>
                            </div>
                            <span class="min-w-16 text-right text-sm font-semibold text-slate-900">{{ $status['count'] }} {{ $status['count'] === 1 ? 'appointment' : 'appointments' }}</span>
                        </div>
                    @empty
                        <p class="mt-4 text-sm text-slate-500">No appointments in this period.</p>
                    @endforelse
                </article>
            </div>
        </section>
    @endif

    <section class="mt-8 rounded-2xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div><h2 class="font-semibold text-slate-900">Today's schedule</h2><p class="mt-1 text-sm text-slate-500">Upcoming and active appointments</p></div>
            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('appointments.index') }}">Full schedule</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Time</th><th>Patient</th><th>Service</th><th>Type</th><th>Status</th><th>Queue</th></tr></thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td data-label="Time"><span class="inline-flex flex-col gap-1"><span class="font-semibold text-slate-800">{{ $appointment->starts_at->format('g:i A') }}</span><span class="text-xs text-slate-500">{{ $appointment->starts_at->format('M j, Y') }}</span></span></td>
                        <td data-label="Patient"><a class="font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $appointment->patient) }}">{{ $appointment->patient->user->name }}</a><div class="text-xs text-slate-400">{{ $appointment->patient->patient_number }}</div></td>
                        <td data-label="Service"><span class="inline-flex rounded-lg bg-teal-50 px-2.5 py-1.5 font-semibold text-teal-900">{{ $appointment->treatmentNames() }}</span></td>
                        <td data-label="Type" class="capitalize">{{ str_replace('_', ' ', $appointment->type) }}</td>
                        <td data-label="Status"><span class="status-badge">{{ str_replace('_', ' ', $appointment->status) }}</span></td>
                        <td data-label="Queue">{{ $appointment->queueEntry?->queue_number ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">No appointments scheduled for today.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
