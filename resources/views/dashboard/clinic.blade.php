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
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Appointments · last seven days</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-7">
                @foreach ($dailyAppointments as $day)
                    <div class="grid gap-2 text-center">
                        <div class="flex h-28 items-end justify-center rounded-lg bg-slate-50 p-2">
                            <div class="w-full rounded-t-md bg-teal-600" style="height: {{ min(100, max(8, $day->total * 10)) }}%"></div>
                        </div>
                        <span class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($day->appointment_day)->format('D j') }}</span>
                        <span class="text-xs font-semibold">{{ $day->total }}</span>
                    </div>
                @endforeach
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
                        <td data-label="Time" class="whitespace-nowrap font-medium">{{ $appointment->starts_at->format('g:i A') }}</td>
                        <td data-label="Patient"><a class="font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $appointment->patient) }}">{{ $appointment->patient->user->name }}</a><div class="text-xs text-slate-400">{{ $appointment->patient->patient_number }}</div></td>
                        <td data-label="Service">{{ $appointment->service->name }}</td>
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
