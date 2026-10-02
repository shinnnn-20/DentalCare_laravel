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
        <p class="report-note mt-5 text-sm text-slate-500">Revenue uses applied payment amounts; partial payments are included only for their paid portion.</p>
        <footer class="report-print-footer">Generated {{ now()->format('F j, Y · g:i A') }} · {{ config('clinic.name') }}</footer>
    </div>
@endsection
