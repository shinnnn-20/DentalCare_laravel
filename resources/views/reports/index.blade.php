@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="clinic-report-page">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print-hidden">
            <div><p class="text-sm font-medium text-teal-700">Administration</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Clinic reports</h1><p class="mt-2 text-slate-500">Summary for {{ $from }} through {{ $to }}.</p></div>
            <button class="btn-secondary" onclick="window.print()">Print / save PDF</button>
        </div>
        <form method="GET" class="mb-6 flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5 print-hidden">
            <label class="form-label">From<input class="form-input" type="date" name="from" value="{{ $from }}" required></label>
            <label class="form-label">To<input class="form-input" type="date" name="to" value="{{ $to }}" required></label>
            <button class="btn-primary">Apply range</button>
        </form>
        <header class="report-print-header">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-800">{{ config('clinic.name') }}</p>
            <h1 class="mt-2 text-2xl font-bold">Clinic Activity Report</h1>
            <p class="mt-2 text-sm">Reporting period: <strong>{{ \Illuminate\Support\Carbon::parse($from)->format('F j, Y') }}</strong> through <strong>{{ \Illuminate\Support\Carbon::parse($to)->format('F j, Y') }}</strong></p>
            <p class="mt-1 text-xs text-slate-500">{{ config('clinic.address') }} · {{ config('clinic.phone') }}</p>
        </header>
        <div class="report-cards grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['Appointments', $appointments],
            ['Walk-in appointments', $walkIns],
            ['Completed appointments', $completed],
            ['Cancelled appointments', $cancelled],
            ['New patient registrations', $newPatients],
            ['Revenue collected', '₱'.number_format((float) $revenue, 2)],
        ] as [$label, $value])
            <article class="stat-card"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><p class="mt-3 text-3xl font-bold">{{ $value }}</p></article>
        @endforeach
        </div>
        <section class="print-hidden mt-8 grid gap-4 xl:grid-cols-2" data-report-statistics>
            <article class="rounded-2xl border border-slate-200 bg-white p-5" data-report-appointments>
                <h2 class="font-semibold text-slate-900">Appointments over time</h2>
                <p class="mt-1 text-sm text-slate-500">Appointments by {{ count($timeline) === 1 ? 'selected period' : 'date range bucket' }}.</p>
                <div class="mt-5 flex h-48 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Appointments by date for the selected report range">
                    @foreach ($timeline as $point)
                        <div class="flex h-full min-w-10 flex-1 flex-col items-center justify-end gap-1">
                            <span class="text-xs font-semibold text-slate-700">{{ $point['appointments'] }}</span>
                            <div class="flex h-32 w-full items-end rounded-t-md bg-slate-50">
                                <div class="w-full rounded-t-md bg-teal-600" style="height: {{ $point['appointments'] / $appointmentMaximum * 100 }}%"></div>
                            </div>
                            <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5" data-report-revenue>
                <h2 class="font-semibold text-slate-900">Revenue collected</h2>
                <p class="mt-1 text-sm text-slate-500">Applied payments recorded within this report range.</p>
                <div class="mt-5 flex h-48 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Applied payment totals by date for the selected report range">
                    @foreach ($timeline as $point)
                        <div class="flex h-full min-w-10 flex-1 flex-col items-center justify-end gap-1">
                            <span class="whitespace-nowrap text-[0.65rem] font-semibold text-slate-700">₱{{ number_format($point['revenue'], 0) }}</span>
                            <div class="flex h-32 w-full items-end rounded-t-md bg-slate-50">
                                <div class="w-full rounded-t-md bg-cyan-600" style="height: {{ $point['revenue'] / $revenueMaximum * 100 }}%"></div>
                            </div>
                            <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 xl:col-span-2" data-report-statuses>
                <h2 class="font-semibold text-slate-900">Appointment status distribution</h2>
                <p class="mt-1 text-sm text-slate-500">Current system statuses for appointments scheduled within this report range.</p>
                @forelse ($statuses as $status)
                    <div class="mt-4 grid grid-cols-[minmax(7rem,1fr)_minmax(0,3fr)_auto] items-center gap-3">
                        <span class="text-sm font-medium capitalize text-slate-700">{{ $status['label'] }}</span>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-teal-600" style="width: {{ $status['percentage'] }}%"></div>
                        </div>
                        <span class="min-w-16 text-right text-sm font-semibold text-slate-900">{{ $status['count'] }}</span>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-slate-500">No appointments in this report range.</p>
                @endforelse
            </article>
        </section>
        <div class="report-print-table-wrapper">
            <table class="report-print-table">
                <thead><tr><th>Measure</th><th>Result</th></tr></thead>
                <tbody>
                    <tr><td>Appointments</td><td>{{ $appointments }}</td></tr>
                    <tr><td>Walk-in appointments</td><td>{{ $walkIns }}</td></tr>
                    <tr><td>Completed appointments</td><td>{{ $completed }}</td></tr>
                    <tr><td>Cancelled appointments</td><td>{{ $cancelled }}</td></tr>
                    <tr><td>New patient registrations</td><td>{{ $newPatients }}</td></tr>
                    <tr><td>Revenue collected</td><td>₱{{ number_format((float) $revenue, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
        <p class="report-note mt-5 text-sm text-slate-500">Revenue uses applied payment amounts; partial payments are included only for their paid portion. Revenue includes PayMongo TEST MODE payments when present; they simulate payment and do not transfer real money.</p>
        <footer class="report-print-footer">Generated {{ now()->format('F j, Y · g:i A') }} · {{ config('clinic.name') }}</footer>
    </div>
@endsection
