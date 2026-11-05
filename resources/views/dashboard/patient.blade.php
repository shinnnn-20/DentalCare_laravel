@extends('layouts.app')

@section('title', 'Patient dashboard')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">Patient portal</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Hello, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-slate-500">Your appointments, treatment history, and billing in one place.</p>
    </div>
    @if ($showRfidExpirationNotice)
        @php($rfidCardExpired = $rfidCard->validityStatus() === 'expired')
        <section aria-labelledby="rfid-expiration-heading" class="mb-6 flex flex-col gap-4 rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-amber-800">RFID card expiration reminder</p>
                <h2 id="rfid-expiration-heading" class="mt-1 text-xl font-bold text-slate-900">
                    {{ $rfidCardExpired ? 'Your RFID card has expired' : 'Your RFID card expires soon' }}
                </h2>
                <p class="mt-1 text-sm text-slate-700">
                    {{ $rfidCardExpired ? 'Your card expired on' : 'Your card expires on' }}
                    <strong>{{ $rfidCard->valid_until->format('M j, Y') }}</strong>.
                    Contact the clinic to renew or update your card.
                </p>
            </div>
            <a class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 ring-1 ring-amber-300 transition hover:bg-amber-100" href="{{ route('patient.rfid.index') }}">
                View RFID card
            </a>
        </section>
    @endif
    @if ($nextFollowUp)
        <section aria-labelledby="follow-up-heading" class="mb-6 flex flex-col gap-4 rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="flex items-start gap-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-amber-100 text-xl text-amber-800" aria-hidden="true">▦</span>
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-amber-800">Upcoming follow-up</p>
                    <h2 id="follow-up-heading" class="mt-1 text-xl font-bold text-slate-900">
                        {{ $nextFollowUp->follow_up_date->format('l, F j, Y') }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-700">Scheduled after: {{ $nextFollowUp->treatment }}</p>
                </div>
            </div>
            <a class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 ring-1 ring-amber-300 transition hover:bg-amber-100" href="{{ route('patient.records') }}">
                View dental records
            </a>
        </section>
    @endif
    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-2xl bg-teal-800 p-6 text-white xl:col-span-2">
            <p class="text-sm font-medium text-teal-100">Next appointment</p>
            @if ($nextAppointment)
                <span class="mt-4 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide">Next visit</span>
                <h2 class="mt-3 text-2xl font-bold">{{ $nextAppointment->treatmentNames() }}</h2>
                <p class="sr-only">{{ $nextAppointment->starts_at->format('l, F j, Y · g:i A') }}</p>
                <div class="mt-3 grid gap-2 text-sm text-teal-50 sm:grid-cols-2" aria-hidden="true">
                    <p class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10">▦</span>{{ $nextAppointment->starts_at->format('l, F j, Y') }}</p>
                    <p class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10">◷</span>{{ $nextAppointment->starts_at->format('g:i A') }}</p>
                </div>
                <span class="mt-4 inline-flex rounded-full bg-white/15 px-3 py-1 text-sm capitalize">{{ str_replace('_', ' ', $nextAppointment->status) }}</span>
            @else
                <h2 class="mt-3 text-2xl font-bold">No upcoming appointments</h2>
                <p class="mt-2 text-teal-100">Book a visit and the clinic will confirm your requested time.</p>
            @endif
            <a href="{{ route('appointments.index') }}" class="btn-primary mt-6 w-full sm:w-auto">Book or manage appointments</a>
        </section>
        <section class="stat-card">
            <p class="text-sm font-medium text-slate-500">Outstanding bills</p>
            <p class="mt-3 text-3xl font-bold text-slate-900">{{ $unpaidBills->count() }}</p>
            <a class="mt-4 inline-flex text-sm font-semibold text-teal-800 hover:underline" href="{{ route('patient.billing') }}">View billing history →</a>
        </section>
    </div>
    <section class="mt-8 rounded-2xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div><h2 class="font-semibold text-slate-900">Recent dental records</h2><p class="mt-1 text-sm text-slate-500">Your private clinical history</p></div>
            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('patient.records') }}">View all</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($recentRecords as $record)
                <article class="grid gap-2 px-5 py-4 sm:grid-cols-[150px_1fr]">
                    <p class="text-sm text-slate-500">{{ $record->created_at->format('M j, Y') }}</p>
                    <div><h3 class="font-semibold">{{ $record->treatment }}</h3><p class="mt-1 text-sm text-slate-600">{{ $record->diagnosis }}</p></div>
                </article>
            @empty
                <p class="px-5 py-8 text-sm text-slate-500">No dental records are available yet.</p>
            @endforelse
        </div>
    </section>
@endsection
