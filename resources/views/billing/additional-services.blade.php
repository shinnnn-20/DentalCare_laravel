@extends('layouts.app')

@section('title', 'Add services to bill')

@section('content')
    @php($paid = (float) $bill->payments->sum('applied_amount'))
    @php($balance = max(0, (float) $bill->total - $paid))
    @php($originalItems = $bill->items->where('is_add_on', false))
    @php($addOnItems = $bill->items->where('is_add_on', true))
    @php($selectedServiceIds = array_map('intval', (array) old('service_ids', [])))

    <section class="max-w-5xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <p class="text-sm font-medium text-teal-700">{{ $bill->patient->patient_number }} · {{ $bill->appointment->starts_at->format('M j, Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Add services to bill {{ $bill->bill_number }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $bill->patient->user->name }} · Patient ID {{ $bill->patient->patient_number }} · Appointment #{{ $bill->appointment_id }} · Bill #{{ $bill->bill_number }}</p>

        <div class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-3">
            <p class="text-slate-600">Current bill total <strong class="mt-1 block text-base text-slate-900">₱{{ number_format((float) $bill->total, 2) }}</strong></p>
            <p class="text-slate-600">Amount paid to date <strong class="mt-1 block text-base text-slate-900">₱{{ number_format($paid, 2) }}</strong></p>
            <p class="text-slate-600">Current remaining balance <strong class="mt-1 block text-base text-slate-900">₱{{ number_format($balance, 2) }}</strong></p>
        </div>

        <section class="mt-6 rounded-xl border border-slate-200 p-4 sm:p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Current bill items</h2>
            @if ($originalItems->isNotEmpty())
                <h3 class="mt-3 text-sm font-semibold text-slate-800">Original Services</h3>
                <ul class="mt-1 divide-y divide-slate-100">
                    @foreach ($originalItems as $item)
                        <li class="flex flex-wrap justify-between gap-2 py-2 text-sm">
                            <span class="min-w-0">{{ $item->service_name }} <span class="text-slate-500">× {{ $item->quantity }}</span></span>
                            <span class="shrink-0 font-medium">₱{{ number_format((float) $item->subtotal, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($addOnItems->isNotEmpty())
                <h3 class="mt-4 text-sm font-semibold text-teal-800">Add-on Services</h3>
                <ul class="mt-1 divide-y divide-slate-100">
                    @foreach ($addOnItems as $item)
                        <li class="flex flex-wrap justify-between gap-2 py-2 text-sm">
                            <span class="min-w-0">{{ $item->service_name }} <span class="text-slate-500">× {{ $item->quantity }}</span></span>
                            <span class="shrink-0 font-medium">₱{{ number_format((float) $item->subtotal, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($services->isEmpty())
            <p class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">There are no additional active services available for this bill.</p>
        @else
            <form method="POST" action="{{ route('clinic.bills.additional-services.store', $bill) }}" class="mt-6 grid gap-6" data-addon-form>
                @csrf
                <fieldset class="grid gap-3">
                    <legend class="mb-1 text-base font-semibold text-slate-900">Choose services by category</legend>
                    <p class="text-sm text-slate-500">Open a category to browse its treatments. Select a service with a reference fee to confirm its final price right there.</p>
                    <div class="grid gap-3">
                        @foreach ($services->groupBy(fn ($service) => $service->category ?: 'Other Services') as $category => $categoryServices)
                            @php($categoryHasOldSelection = $categoryServices->contains(fn ($service) => in_array($service->id, $selectedServiceIds, true)))
                            <details class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-addon-category="{{ $category }}" @if ($loop->first || $categoryHasOldSelection) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-slate-50 px-4 py-3.5 transition-colors hover:bg-teal-50/70 [&::-webkit-details-marker]:hidden">
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-slate-900">{{ $category }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $categoryServices->count() }} {{ \Illuminate\Support\Str::plural('treatment', $categoryServices->count()) }}</span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-2">
                                        <span class="hidden rounded-full bg-teal-700 px-2.5 py-1 text-xs font-semibold text-white" data-addon-category-selected-count></span>
                                        <svg class="h-5 w-5 text-slate-500 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"></path>
                                        </svg>
                                    </span>
                                </summary>
                                <div class="grid gap-3 border-t border-slate-100 p-3 sm:p-4">
                                    @foreach ($categoryServices as $service)
                                        @php($serviceIsSelected = in_array($service->id, $selectedServiceIds, true))
                                        <div class="grid min-w-0 gap-3 rounded-xl border border-slate-200 bg-white p-3 transition-colors sm:grid-cols-[minmax(0,1fr)_minmax(14rem,0.75fr)] sm:items-start" data-addon-card>
                                            <label class="flex min-w-0 cursor-pointer items-start gap-3">
                                                <input
                                                    id="addon-service-{{ $service->id }}"
                                                    class="mt-1 h-4 w-4 shrink-0 accent-teal-700"
                                                    type="checkbox"
                                                    name="service_ids[]"
                                                    value="{{ $service->id }}"
                                                    data-addon-toggle
                                                    data-service-id="{{ $service->id }}"
                                                    data-service-name="{{ $service->name }}"
                                                    data-service-category="{{ $category }}"
                                                    data-price-cents="{{ (int) round((float) $service->price * 100) }}"
                                                    data-requires-price-confirmation="{{ $service->requires_price_confirmation ? 'true' : 'false' }}"
                                                    @checked($serviceIsSelected)
                                                >
                                                <span class="min-w-0">
                                                    <span class="block break-words font-medium text-slate-900">{{ $service->name }}</span>
                                                    <span class="mt-1 block text-sm font-semibold text-slate-700">
                                                        {{ $service->requires_price_confirmation ? 'Reference: '.$service->price_display : '₱'.number_format((float) $service->price, 2) }}
                                                    </span>
                                                </span>
                                            </label>
                                            @if ($service->requires_price_confirmation)
                                                <div class="hidden min-w-0 rounded-lg border border-amber-200 bg-amber-50/70 p-3 sm:col-span-2" data-addon-price-panel="{{ $service->id }}">
                                                    <p class="text-xs leading-5 text-amber-900">Reference fee only. Confirm the final amount using the approved clinic fee schedule or dentist.</p>
                                                    <div class="mt-3 grid gap-3 sm:grid-cols-[minmax(12rem,16rem)_minmax(0,1fr)] sm:items-end">
                                                        <label class="form-label">Final amount (₱)
                                                            <input class="form-input" type="text" inputmode="decimal" name="items[{{ $service->id }}][final_price]" data-money-input data-addon-final-price="{{ $service->id }}" pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('items.'.$service->id.'.final_price') }}" @required($serviceIsSelected) @disabled(! $serviceIsSelected)>
                                                        </label>
                                                        <label class="flex items-start gap-2 pb-2 text-sm leading-5 text-slate-700">
                                                            <input class="mt-1 shrink-0 accent-teal-700" type="checkbox" name="items[{{ $service->id }}][confirm_final_price]" value="1" data-addon-price-confirmation="{{ $service->id }}" @checked(old('items.'.$service->id.'.confirm_final_price')) @required($serviceIsSelected) @disabled(! $serviceIsSelected)>
                                                            <span>I confirmed this price with the approved fee schedule or dentist.</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </fieldset>

                <section class="rounded-xl border border-teal-200 bg-teal-50/60 p-4 sm:p-5" aria-live="polite">
                    <h2 class="font-semibold text-slate-900">Selected add-on services</h2>
                    <p class="mt-1 text-sm text-slate-600" data-addon-empty>Select treatments above to review them before adding.</p>
                    <ul class="mt-3 hidden divide-y divide-teal-100" data-addon-summary-items></ul>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-teal-200 pt-3">
                        <span class="text-sm font-semibold text-slate-700">Total additional amount</span>
                        <strong class="text-lg text-teal-900" data-addon-total>₱0.00</strong>
                    </div>
                </section>
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm leading-5 text-slate-700">
                    <input class="mt-1 h-4 w-4 accent-teal-700" type="checkbox" name="confirm_add_ons" value="1" required @checked(old('confirm_add_ons'))>
                    <span>I confirm these selected services should be added to this appointment’s existing bill. I reviewed the service names, quantities, and additional amount above.</span>
                </label>
                <button class="btn-primary justify-self-start">Confirm and add services</button>
            </form>
        @endif

        <a class="btn-secondary mt-5" href="{{ route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id }}">Back to billing</a>
    </section>
@endsection
