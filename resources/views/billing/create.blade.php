@extends('layouts.app')

@section('title', 'Create bill')

@section('content')
    @php($services = $appointment->services->isNotEmpty() ? $appointment->services : ($appointment->service ? collect([$appointment->service]) : collect()))
    @php($fixedSubtotalCents = $services->sum(fn ($service) => $service->requires_price_confirmation ? 0 : (int) round((float) $service->price * 100)))
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
            <form method="POST" action="{{ route('clinic.bills.store', $appointment) }}" class="mt-5 grid gap-4" data-bill-discount data-bill-base-cents="{{ $fixedSubtotalCents }}" data-confirm="Create this bill with the displayed services, final amounts, and discount?" data-confirm-title="Confirm bill" data-confirm-accept="Create bill">
                @csrf
                @include('billing._dentist-select', ['service' => $services->first()])
                @if ($appointment->service->requires_price_confirmation)
                    <div class="grid gap-2">
                        <p class="text-sm leading-6 text-amber-800">This chart fee is an unverified reference only. Confirm the final amount with the clinic's approved fee schedule and dentist's assessment; the reference amount is not automatically billed.</p>
                        <label class="form-label">Staff-confirmed final amount (₱)
                            <input class="form-input max-w-xs" type="text" inputmode="decimal" name="final_price" data-money-input data-bill-final-price pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('final_price') }}" required>
                        </label>
                        <label class="flex items-start gap-2 text-sm leading-5 text-slate-700">
                            <input class="mt-1" type="checkbox" name="confirm_final_price" value="1" @checked(old('confirm_final_price')) required>
                            <span>I confirmed this final amount with the approved clinic fee schedule or dentist.</span>
                        </label>
                    </div>
                @endif
                <div class="grid gap-3">
                    <label class="form-label">Discount type
                        <select class="form-input max-w-xs" name="discount_type" data-discount-type>
                            <option value="none" @selected(old('discount_type', 'none') === 'none')>None</option>
                            <option value="senior" @selected(old('discount_type') === 'senior')>Senior (20%)</option>
                            <option value="pwd" @selected(old('discount_type') === 'pwd')>PWD (20%)</option>
                            <option value="percentage" @selected(old('discount_type', 'none') === 'percentage')>Custom percentage</option>
                        </select>
                    </label>
                    <label class="form-label">Discount (%)
                        <input class="form-input max-w-xs" type="number" name="discount_value" min="0" max="100" step="0.01" value="{{ old('discount_value', '0') }}" data-discount-percentage required @disabled(old('discount_type', 'none') !== 'percentage')>
                    </label>
                </div>
                <div class="grid gap-1 rounded-xl border border-teal-100 bg-teal-50/60 p-4 text-sm">
                    <p>Calculated discount amount: <strong data-discount-amount>{{ config('clinic.currency_symbol') }}0.00</strong></p>
                    <p>Final bill total: <strong data-discounted-total>{{ config('clinic.currency_symbol') }}0.00</strong></p>
                    <p class="text-xs text-slate-600" data-bill-calculation-status></p>
                </div>
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

            <form method="POST" action="{{ route('clinic.bills.store', $appointment) }}" class="mt-5 grid gap-4" data-bill-discount data-bill-base-cents="{{ $fixedSubtotalCents }}" data-confirm="Create this bill with the displayed services, final amounts, and discount?" data-confirm-title="Confirm bill" data-confirm-accept="Create bill">
                @csrf
                @foreach ($services as $service)
                    @include('billing._dentist-select', ['service' => $service])
                @endforeach
                @php($hasVariable = $services->contains('requires_price_confirmation', true))
                @if ($hasVariable)
                    <div class="grid gap-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                        <p class="text-sm leading-6 text-amber-900 font-medium">Some selected treatments have variable or reference fees. Confirm each final amount before billing:</p>
                        @foreach ($services as $service)
                            @if ($service->requires_price_confirmation)
                                <div class="grid gap-2 border-t border-amber-200 pt-3">
                                    <p class="text-sm font-semibold text-slate-800">{{ $service->name }} (Reference: {{ $service->price_display }})</p>
                                    <label class="form-label">Staff-confirmed final amount for {{ $service->name }} (₱)
                                        <input class="form-input max-w-xs" type="text" inputmode="decimal" name="items[{{ $service->id }}][final_price]" data-money-input data-bill-final-price pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('items.'.$service->id.'.final_price') }}" required>
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
                <div class="grid gap-3">
                    <label class="form-label">Discount type
                        <select class="form-input max-w-xs" name="discount_type" data-discount-type>
                            <option value="none" @selected(old('discount_type', 'none') === 'none')>None</option>
                            <option value="senior" @selected(old('discount_type') === 'senior')>Senior (20%)</option>
                            <option value="pwd" @selected(old('discount_type') === 'pwd')>PWD (20%)</option>
                            <option value="percentage" @selected(old('discount_type', 'none') === 'percentage')>Custom percentage</option>
                        </select>
                    </label>
                    <label class="form-label">Discount (%)
                        <input class="form-input max-w-xs" type="number" name="discount_value" min="0" max="100" step="0.01" value="{{ old('discount_value', '0') }}" data-discount-percentage required @disabled(old('discount_type', 'none') !== 'percentage')>
                    </label>
                </div>
                <div class="grid gap-1 rounded-xl border border-teal-100 bg-teal-50/60 p-4 text-sm">
                    <p>Calculated discount amount: <strong data-discount-amount>{{ config('clinic.currency_symbol') }}0.00</strong></p>
                    <p>Final bill total: <strong data-discounted-total>{{ config('clinic.currency_symbol') }}0.00</strong></p>
                    <p class="text-xs text-slate-600" data-bill-calculation-status></p>
                </div>
                <p class="text-xs text-slate-500">The confirmed service amounts are saved as bill-item snapshots and won't change if the catalog is updated later. The appointment can be completed after the bill is fully paid.</p>
                <button class="btn-primary justify-self-start">Create bill</button>
            </form>
        @endif
    </section>
@endsection
