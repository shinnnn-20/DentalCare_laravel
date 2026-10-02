@extends('layouts.app')

@section('title', 'Create bill')

@section('content')
    <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-sm font-medium text-teal-700">{{ $appointment->patient->patient_number }} · {{ $appointment->starts_at->format('M j, Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold">Create bill</h1>
        <div class="mt-6 rounded-xl bg-slate-50 p-4">
            <div class="flex justify-between gap-4"><span>{{ $appointment->service->name }}</span><span class="font-semibold">₱{{ number_format((float) $appointment->service->price, 2) }}</span></div>
            <p class="mt-2 text-sm text-slate-500">{{ $appointment->patient->user->name }} · Appointment #{{ $appointment->id }}</p>
        </div>
        <form method="POST" action="{{ route('clinic.bills.store', $appointment) }}" class="mt-5 grid gap-4">
            @csrf
            <label class="form-label">Discount (₱)<input class="form-input max-w-xs" type="number" name="discount" min="0" max="{{ $appointment->service->price }}" step="0.01" value="{{ old('discount', '0.00') }}" required></label>
            <p class="text-xs text-slate-500">The service price is saved as a bill-item snapshot and won't change if the service price is updated later. The appointment can be completed after this bill is fully paid.</p>
            <button class="btn-primary justify-self-start">Create bill</button>
        </form>
    </section>
@endsection
