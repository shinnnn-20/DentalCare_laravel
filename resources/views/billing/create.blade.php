@extends('layouts.app')

@section('title', 'Create bill')

@section('content')
    @php($services = $appointment->services->isNotEmpty() ? $appointment->services : ($appointment->service ? collect([$appointment->service]) : collect()))
    <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-sm font-medium text-teal-700">{{ $appointment->patient->patient_number }} · {{ $appointment->starts_at->format('M j, Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold">Create bill</h1>

        @if ($services->count() === 1)
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
        @else
            <div class="mt-6 rounded-xl bg-slate-50 p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2">Selected Treatments ({{ $services->count() }})</h2>
                <div class="divide-y divide-slate-200">
                    @foreach ($services as $service)
                        <div class="flex justify-between items-center gap-4 py-2">
                            <div>
                                <span class="font-medium text-slate-800">{{ $service->name }}</span>
                                @if ($service->category)
                                    <span class="block text-xs text-slate-500">{{ $service->category }}</span>
                                @endif
                            </div>
                            <span class="font-semibold text-slate-900">
                                {{ $service->requires_price_confirmation ? 'Reference: '.$service->price_display : config('clinic.currency_symbol').number_format((float) $service->price, 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-sm text-slate-500 border-t border-slate-200 pt-2">{{ $appointment->patient->user->name }} · Appointment #{{ $appointment->id }}</p>
            </div>

            <form method="POST" action="{{ route('clinic.bills.store', $appointment) }}" class="mt-5 grid gap-4">
                @csrf
                @php($hasVariable = $services->contains('requires_price_confirmation', true))
                @if ($hasVariable)
                    <div class="grid gap-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                        <p class="text-sm leading-6 text-amber-900 font-medium">Some selected treatments have variable or reference fees. Confirm each final amount before billing:</p>
                        @foreach ($services as $service)
                            @if ($service->requires_price_confirmation)
                                <div class="grid gap-2 border-t border-amber-200 pt-3">
                                    <p class="text-sm font-semibold text-slate-800">{{ $service->name }} (Reference: {{ $service->price_display }})</p>
                                    <label class="form-label">Staff-confirmed final amount for {{ $service->name }} (₱)
                                        <input class="form-input max-w-xs" type="text" inputmode="decimal" name="items[{{ $service->id }}][final_price]" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('items.'.$service->id.'.final_price') }}" required>
                                    </label>
                                    <label class="flex items-start gap-2 text-sm leading-5 text-slate-700">
                                        <input class="mt-1" type="checkbox" name="items[{{ $service->id }}][confirm_final_price]" value="1" @checked(old('items.'.$service->id.'.confirm_final_price')) required>
                                        <span>I confirmed this amount for {{ $service->name }} with the clinic fee schedule or dentist.</span>
                                    </label>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
                <label class="form-label">Discount (₱)
                    <input class="form-input max-w-xs" type="text" inputmode="decimal" name="discount" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('discount', '0.00') }}" required>
                </label>
                <p class="text-xs text-slate-500">The confirmed service amounts are saved as bill-item snapshots and won't change if the catalog is updated later. The appointment can be completed after the bill is fully paid.</p>
                <button class="btn-primary justify-self-start">Create bill</button>
            </form>
        @endif
    </section>
@endsection
