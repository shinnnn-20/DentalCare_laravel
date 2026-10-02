@extends('layouts.app')

@section('title', 'Patient queue')

@push('head')
    <meta http-equiv="refresh" content="20">
@endpush

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">{{ today()->format('l, F j, Y') }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Patient queue</h1><p class="mt-2 text-slate-500">Call patients and move visits into consultation.</p></div>
        <a class="btn-secondary" href="{{ route('clinic.rfid.scan') }}">RFID check-in</a>
    </div>
    <section class="grid gap-4">
        @forelse ($queueEntries as $entry)
            <article class="flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    <div class="grid h-14 w-16 place-items-center rounded-xl bg-teal-50 text-lg font-bold text-teal-800">{{ $entry->queue_number }}</div>
                    <div class="min-w-0"><h2 class="font-semibold">{{ $entry->appointment->patient->user->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $entry->appointment->starts_at->format('g:i A') }} · {{ $entry->appointment->service->name }} · {{ $entry->appointment->patient->patient_number }}</p><p class="mt-1 text-xs font-semibold uppercase tracking-wide text-teal-700">{{ $entry->appointment->type === 'walk_in' ? 'Walk-in' : 'Online' }} appointment</p></div>
                </div>
                <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:gap-3"><span class="status-badge capitalize">{{ str_replace('_', ' ', $entry->status) }}</span>
                    @if ($entry->status === 'waiting')
                        <form method="POST" action="{{ route('appointments.transition', [$entry->appointment, 'call']) }}">@csrf<button class="btn-primary">Call patient</button></form>
                    @elseif ($entry->status === 'called')
                        <form method="POST" action="{{ route('appointments.transition', [$entry->appointment, 'consult']) }}">@csrf<button class="btn-primary">Start visit</button></form>
                    @endif
                    @if (in_array($entry->status, ['waiting', 'called'], true))
                        <form method="POST" action="{{ route('appointments.transition', [$entry->appointment, 'no-show']) }}">@csrf<button class="btn-small text-rose-700">No show</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><h2 class="font-semibold">The queue is clear</h2><p class="mt-2 text-sm text-slate-500">Checked-in patients will appear here.</p></div>
        @endforelse
    </section>
@endsection
