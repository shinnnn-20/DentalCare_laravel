@extends('layouts.app')

@section('title', 'Add services to bill')

@section('content')
    @php($paid = (float) $bill->payments->sum('applied_amount'))
    @php($balance = max(0, (float) $bill->total - $paid))

    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <p class="text-sm font-medium text-teal-700">{{ $bill->patient->patient_number }} · {{ $bill->appointment->starts_at->format('M j, Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold">Add services to bill {{ $bill->bill_number }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $bill->patient->user->name }} · Appointment #{{ $bill->appointment_id }}</p>

        <div class="mt-5 grid gap-2 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-3">
            <p>Current total: <strong>₱{{ number_format((float) $bill->total, 2) }}</strong></p>
            <p>Paid to date: <strong>₱{{ number_format($paid, 2) }}</strong></p>
            <p>Current balance: <strong>₱{{ number_format($balance, 2) }}</strong></p>
        </div>

        <div class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Current bill items</h2>
            <ul class="mt-2 divide-y divide-slate-100">
                @foreach ($bill->items as $item)
                    <li class="flex justify-between gap-4 py-2 text-sm">
                        <span>{{ $item->service_name }} <span class="text-slate-500">× {{ $item->quantity }}</span></span>
                        <span class="font-medium">₱{{ number_format((float) $item->subtotal, 2) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($services->isEmpty())
            <p class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">There are no additional active services available for this bill.</p>
        @else
            <form method="POST" action="{{ route('clinic.bills.additional-services.store', $bill) }}" class="mt-6 grid gap-5">
                @csrf
                <fieldset class="grid gap-3">
                    <legend class="form-label">Select additional services</legend>
                    @foreach ($services as $service)
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                            <input class="mt-1" type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, (array) old('service_ids', [])))>
                            <span class="flex-1">
                                <span class="block font-medium text-slate-800">{{ $service->name }}</span>
                                @if ($service->category)
                                    <span class="block text-xs text-slate-500">{{ $service->category }}</span>
                                @endif
                            </span>
                            <span class="text-sm font-semibold">
                                {{ $service->requires_price_confirmation ? 'Reference: '.$service->price_display : '₱'.number_format((float) $service->price, 2) }}
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                @foreach ($services->where('requires_price_confirmation', true) as $service)
                    <div class="grid gap-2 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                        <p class="font-semibold">{{ $service->name }} · Reference: {{ $service->price_display }}</p>
                        <label class="form-label">Staff-confirmed final amount (₱)
                            <input class="form-input max-w-xs" type="text" inputmode="decimal" name="items[{{ $service->id }}][final_price]" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('items.'.$service->id.'.final_price') }}">
                        </label>
                        <label class="flex items-start gap-2 text-sm leading-5 text-slate-700">
                            <input class="mt-1" type="checkbox" name="items[{{ $service->id }}][confirm_final_price]" value="1" @checked(old('items.'.$service->id.'.confirm_final_price'))>
                            <span>I confirmed this amount with the approved clinic fee schedule or dentist.</span>
                        </label>
                    </div>
                @endforeach

                <button class="btn-primary justify-self-start">Add selected services</button>
            </form>
        @endif

        <a class="btn-secondary mt-5" href="{{ route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id }}">Back to billing</a>
    </section>
@endsection
