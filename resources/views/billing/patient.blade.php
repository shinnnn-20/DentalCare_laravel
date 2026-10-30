@extends('layouts.app')

@section('title', 'My billing')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">Patient portal</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">My billing</h1>
        <p class="mt-2 text-slate-500">Review your bills, payment history, and payment receipts.</p>
    </div>
    <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
        <strong class="block">ACKNOWLEDGEMENT RECEIPT (AR) ONLY</strong>
        ACKNOWLEDGEMENT RECEIPT ONLY – This document acknowledges receipt of payment and is not an Official Receipt or a BIR-compliant Sales Invoice.
    </p>
    <section class="grid gap-4">
        @forelse ($bills as $bill)
            @php($paid = (float) $bill->payments->sum('applied_amount'))
            @php($balance = max(0, (float) $bill->total - $paid))
            @php($paymentStatus = ['paid' => 'Paid', 'partially_paid' => 'Partially Paid', 'unpaid' => 'Unpaid'][$bill->payment_status] ?? str_replace('_', ' ', ucfirst($bill->payment_status)))
            <article class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-slate-500">Bill date · {{ $bill->created_at->format('M j, Y') }}</p>
                        <h2 class="mt-1 text-lg font-semibold">Bill Details</h2>
                        <p class="mt-1 text-slate-700">{{ $bill->appointment?->treatmentNames() ?? 'Dental services' }}</p>
                    </div>
                    <span class="status-badge">{{ $paymentStatus }}</span>
                </div>
                <section class="mt-4 border-t border-slate-100 pt-4">
                    @php($originalItems = $bill->items->where('is_add_on', false))
                    @php($addOnItems = $bill->items->where('is_add_on', true))
                    @if ($originalItems->isNotEmpty())
                        <h3 class="text-sm font-semibold">Original Services</h3>
                        @foreach ($originalItems as $item)
                            <div class="mt-2 flex flex-wrap justify-between gap-2 text-sm">
                                <span>{{ $item->service_name }} × {{ $item->quantity }} <span class="text-slate-500">({{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }} each)</span></span>
                                <span class="font-medium">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    @endif
                    @if ($addOnItems->isNotEmpty())
                        <h3 class="mt-4 text-sm font-semibold">Add-on Services</h3>
                        @foreach ($addOnItems as $item)
                            <div class="mt-2 flex flex-wrap justify-between gap-2 text-sm">
                                <span><span class="mr-1 inline-flex rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-800">Add-on Service</span>{{ $item->service_name }} × {{ $item->quantity }} <span class="text-slate-500">({{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }} each)</span></span>
                                <span class="font-medium">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                    @endif
                    @if ($bill->items->isEmpty())
                        <p class="mt-2 text-sm text-slate-500">No services recorded for this bill.</p>
                    @endif
                </section>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">Total amount</dt><dd class="mt-1 font-semibold">{{ config('clinic.currency_symbol') }}{{ number_format((float) $bill->total, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Amount paid</dt><dd class="mt-1 font-semibold">{{ config('clinic.currency_symbol') }}{{ number_format($paid, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Remaining balance</dt><dd class="mt-1 font-semibold">{{ config('clinic.currency_symbol') }}{{ number_format($balance, 2) }}</dd></div>
                </dl>
                @if ($bill->payments->isNotEmpty())
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <h3 class="mb-2 text-sm font-semibold">Payment history</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($bill->payments as $payment)
                                <div class="rounded-xl bg-slate-50 p-3 text-sm">
                                    <p class="font-semibold">{{ ucfirst($payment->method) }} payment</p>
                                    <p class="mt-1 text-slate-600">Payment amount · {{ config('clinic.currency_symbol') }}{{ number_format((float) $payment->applied_amount, 2) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Payment date · {{ ($payment->paid_at ?? $payment->created_at)->format('M j, Y g:i A') }}</p>
                                    @if ($payment->provider === 'paymongo' && $payment->is_test)
                                        <p class="mt-2 text-xs font-medium text-amber-700">PayMongo test mode — no real money transferred</p>
                                    @endif
                                    @if ($payment->receipt)
                                        <a class="mt-2 inline-flex font-semibold text-teal-800 hover:underline" href="{{ route('patient.billing.receipts.preview', ['bill' => $bill, 'receipt' => $payment->receipt]) }}">Preview Payment Receipt →</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No bills are available.</div>
        @endforelse
        <div>{{ $bills->links() }}</div>
    </section>
@endsection
