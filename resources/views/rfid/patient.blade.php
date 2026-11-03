@extends('layouts.app')

@section('title', 'My RFID card')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">Patient portal</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">My RFID card</h1>
        <p class="mt-2 text-slate-500">Register your card UID so clinic staff can identify you at check-in.</p>
    </div>

    <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6">
        @if ($card)
            <div class="mb-6 rounded-xl border border-teal-200 bg-teal-50 p-4">
                @php($cardStatus = $card->validityStatus())
                <p class="text-sm font-semibold text-teal-900">Your registered card</p>
                <p class="mt-2 text-sm text-slate-700">UID: <strong>{{ $card->uid }}</strong></p>
                <p class="mt-1 text-sm capitalize text-slate-600">Status: {{ str_replace('_', ' ', $cardStatus) }}</p>
                @if ($card->valid_until)
                    <p class="mt-1 text-sm text-slate-600">Valid until: {{ $card->valid_until->format('M j, Y g:i A') }}</p>
                @endif
            </div>
            <p class="mb-4 text-sm text-slate-600">Registering a replacement UID immediately changes which card is linked to your account. The clinic manages card validity and activation.</p>
        @else
            <p class="mb-4 text-sm text-slate-600">No RFID card is linked to your account yet. Scan or enter the UID printed on your card.</p>
        @endif

        <form method="POST" action="{{ route('patient.rfid.store') }}" class="grid gap-4">
            @csrf
            <label class="form-label">RFID UID
                <input class="form-input" name="uid" value="{{ old('uid', $card?->uid) }}" maxlength="100" placeholder="Scan or enter UID" autocomplete="off" required>
            </label>
            <button class="btn-primary w-full sm:w-auto">{{ $card ? 'Update registered card' : 'Register RFID card' }}</button>
        </form>
    </section>
@endsection
