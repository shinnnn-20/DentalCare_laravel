@extends('layouts.app')

@section('title', 'RFID check-in')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Front desk</p><h1 class="mt-1 text-3xl font-bold tracking-tight">RFID check-in</h1><p class="mt-2 text-slate-500">Tap a card or enter its UID to identify the patient and today's approved appointments.</p></div>
    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('clinic.rfid.scan.submit') }}" class="flex flex-col gap-3 sm:flex-row">
            @csrf
            <input class="form-input flex-1 text-lg" name="uid" value="{{ old('uid', $uid ?? '') }}" placeholder="RFID UID" autocomplete="off" required autofocus>
            <button class="btn-primary">Identify patient</button>
        </form>
        @isset($patient)
            @if ($patient)
                <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Patient identified</p>
                    <h2 class="mt-2 text-xl font-bold">{{ $patient->user->name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $patient->patient_number }} · {{ $patient->user->phone }}</p>
                    <h3 class="mt-5 text-sm font-semibold">Today's appointments</h3>
                    <div class="mt-2 grid gap-2">
                        @forelse ($appointments as $appointment)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-3">
                                <div><p class="font-medium">{{ $appointment->starts_at->format('g:i A') }} · {{ $appointment->service->name }}</p><p class="text-xs capitalize text-slate-500">{{ str_replace('_', ' ', $appointment->status) }}</p></div>
                                @if ($appointment->status === 'approved')
                                    <form method="POST" action="{{ route('clinic.rfid.check-in', $appointment) }}">@csrf<input type="hidden" name="uid" value="{{ $uid }}"><button class="btn-secondary">Check in</button></form>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-xl bg-white p-3 text-sm text-slate-600">No approved appointment found for today. Register a walk-in from the Appointments page if needed.</p>
                        @endforelse
                    </div>
                    <a class="mt-4 inline-flex text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $patient) }}">Open patient profile →</a>
                </div>
            @else
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">No active patient card matches UID <strong>{{ $uid }}</strong>. Verify the UID or assign the card to a patient.</div>
            @endif
        @endisset
    </section>
@endsection
