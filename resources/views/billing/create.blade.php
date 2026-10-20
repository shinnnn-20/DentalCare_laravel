@extends('layouts.app')

@section('title', 'Create bill')

@section('content')
    <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-sm font-medium text-teal-700">{{ $appointment->patient->patient_number }} · {{ $appointment->starts_at->format('M j, Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold">Create bill</h1>
        <div class="mt-6 rounded-xl bg-slate-50 p-4">
            <div class="flex justify-between gap-4">
                <span>{{ $appointment->service->name }}</span>
                <span class="font-semibold">{{ $appointment->service->requires_price_confirmation ? 'Reference: '.$appointment->service->price_display : config('clinic.currency_symbol').number_format((float) $appointment->service->price, 2) }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-500">{{ $appointment->patient->user->name }} · Appointment #{{ $appointment->id }}</p>
        </div>
        <form method="POST" action="{{ route('clinic.bills.store', $appointment) }}" class="mt-5 grid gap-4">
            @csrf
            @if ($appointment->service->requires_price_confirmation)
                <div class="grid gap-2">
                    <p class="text-sm leading-6 text-amber-800">This chart fee is an unverified reference only. Confirm the final amount with the clinic's approved fee schedule and dentist's assessment; the reference amount is not automatically billed.</p>
                    <label class="form-label">Staff-confirmed final amount (₱)
                        <input class="form-input max-w-xs" type="text" inputmode="decimal" name="final_price" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('final_price') }}" required>
                    </label>
                    <label class="flex items-start gap-2 text-sm leading-5 text-slate-700">
                        <input class="mt-1" type="checkbox" name="confirm_final_price" value="1" @checked(old('confirm_final_price')) required>
                        <span>I confirmed this final amount with the approved clinic fee schedule or dentist.</span>
                    </label>
                </div>
            @endif
            <label class="form-label">Discount (₱)
                <input class="form-input max-w-xs" type="text" inputmode="decimal" name="discount" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('discount', '0.00') }}" required>
            </label>
            <p class="text-xs text-slate-500">The confirmed service amount is saved as a bill-item snapshot and won't change if the catalog is updated later. The appointment can be completed after the bill is fully paid.</p>
            <button class="btn-primary justify-self-start">Create bill</button>
        </form>
    </section>
@endsection
