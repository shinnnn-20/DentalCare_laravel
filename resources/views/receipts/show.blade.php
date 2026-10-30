@extends('layouts.app')

@section('title', $isPreview ? 'Payment Receipt Preview' : 'Acknowledgement Receipt '.$receipt->receipt_number)

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-4 flex justify-end gap-2 print:hidden">
            @if (auth()->user()->role !== 'patient')
                <button class="btn-primary" type="button" data-print-receipt>Print / save PDF</button>
            @endif
            <a class="btn-secondary" href="{{ auth()->user()->role === 'patient' ? route('patient.billing') : route('clinic.billing.index') }}">Back to billing</a>
        </div>
        <article class="receipt-paper rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12">
            <header class="border-b border-slate-200 pb-6 text-center">
                <img class="mx-auto h-16 w-16 object-contain" src="{{ asset('images/landing/doc-brigs-logo.png') }}" alt="Doc Brigs Dental Care logo">
                <h1 class="mt-3 text-2xl font-bold">{{ config('clinic.name') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ config('clinic.address') }} · {{ config('clinic.phone') }}</p>
                @if (auth()->user()->role === 'patient')
                    <h2 class="mt-5 text-lg font-semibold text-slate-800">Payment Receipt Preview</h2>
                @endif
                @if ($receipt->payment->provider === 'paymongo' && $receipt->payment->is_test)
                    <p class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm font-bold uppercase tracking-wide text-amber-900">
                        PayMongo test mode — no real money transferred
                    </p>
                @endif
                <p class="mt-5 text-lg font-bold uppercase tracking-wide text-teal-800">ACKNOWLEDGEMENT RECEIPT (AR) ONLY</p>
                <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-950">
                    ACKNOWLEDGEMENT RECEIPT ONLY – This document acknowledges receipt of payment and is not an Official Receipt or a BIR-compliant Sales Invoice.
                </p>
            </header>
            <div class="grid gap-5 border-b border-slate-200 py-6 text-sm sm:grid-cols-2">
                @if (auth()->user()->role === 'patient')
                    <div>
                        <p class="text-slate-500">Payment date</p>
                        <p class="mt-1 font-semibold">{{ ($receipt->payment->paid_at ?? $receipt->created_at)->format('F j, Y · g:i A') }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Patient</p>
                        <p class="mt-1 font-semibold">{{ $receipt->payment->bill->patient->user->name }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs text-slate-400">Receipt reference</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $receipt->receipt_number }}</p>
                    </div>
                @else
                    <div><p class="text-slate-400">Receipt number</p><p class="mt-1 font-semibold">{{ $receipt->receipt_number }}</p></div>
                    <div><p class="text-slate-400">Bill number</p><p class="mt-1 font-semibold">{{ $receipt->payment->bill->bill_number }}</p></div>
                    <div><p class="text-slate-400">Transaction ID</p><p class="mt-1 font-semibold">{{ $receipt->payment->transaction_id }}</p></div>
                    @if ($receipt->payment->provider_transaction_id)
                        <div><p class="text-slate-400">PayMongo payment ID</p><p class="mt-1 break-all font-semibold">{{ $receipt->payment->provider_transaction_id }}</p></div>
                        <div><p class="text-slate-400">Checkout session ID</p><p class="mt-1 break-all font-semibold">{{ $receipt->payment->provider_checkout_session_id }}</p></div>
                    @endif
                    <div><p class="text-slate-400">Patient</p><p class="mt-1 font-semibold">{{ $receipt->payment->bill->patient->user->name }} · {{ $receipt->payment->bill->patient->patient_number }}</p></div>
                    <div><p class="text-slate-400">Date & time</p><p class="mt-1 font-semibold">{{ ($receipt->payment->paid_at ?? $receipt->created_at)->format('F j, Y · g:i A') }}</p></div>
                @endif
            </div>
            @php($originalItems = $receipt->payment->bill->items->where('is_add_on', false))
            @php($addOnItems = $receipt->payment->bill->items->where('is_add_on', true))
            @php($billAmountPaid = (float) $receipt->payment->bill->payments->sum('applied_amount'))
            @php($billBalance = max(0, (float) $receipt->payment->bill->total - $billAmountPaid))
            <section class="py-6">
                @if ($originalItems->isNotEmpty())
                    <h2 class="mb-2 font-semibold">Original Services</h2>
                    <div class="overflow-x-auto">
                        <table class="data-table !px-0"><thead><tr><th>Service</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr></thead><tbody>
                            @foreach ($originalItems as $item)
                                <tr><td data-label="Service">{{ $item->service_name }}</td><td data-label="Qty">{{ $item->quantity }}</td><td data-label="Unit price">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }}</td><td data-label="Subtotal">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                @endif
                @if ($addOnItems->isNotEmpty())
                    <h2 class="mb-2 mt-5 font-semibold">Add-on Services</h2>
                    <div class="overflow-x-auto">
                        <table class="data-table !px-0"><thead><tr><th>Service</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr></thead><tbody>
                            @foreach ($addOnItems as $item)
                                <tr><td data-label="Service"><span class="mb-1 inline-flex rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-800">Add-on Service</span><br>{{ $item->service_name }}</td><td data-label="Qty">{{ $item->quantity }}</td><td data-label="Unit price">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }}</td><td data-label="Subtotal">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                @endif
                @if ($receipt->payment->bill->items->isEmpty())
                    <p class="text-sm text-slate-500">No services recorded for this bill.</p>
                @endif
            </section>
            <div class="ml-auto grid max-w-sm gap-2 border-t border-slate-200 pt-5 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->subtotal, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Discount</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->discount, 2) }}</span></div>
                <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->total, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Amount paid to date</span><span>{{ config('clinic.currency_symbol') }}{{ number_format($billAmountPaid, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Remaining balance</span><span>{{ config('clinic.currency_symbol') }}{{ number_format($billBalance, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Amount applied</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->applied_amount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Amount received</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->amount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Change</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->change_amount, 2) }}</span></div>
            </div>
            <footer class="mt-8 border-t border-slate-200 pt-5 text-sm">
                <div class="flex flex-wrap justify-between gap-3"><p><span class="text-slate-500">Payment method:</span> <span class="font-semibold uppercase">{{ $receipt->payment->method }}</span></p><p><span class="text-slate-500">Bill status:</span> <span class="font-semibold uppercase">{{ str_replace('_', ' ', $receipt->payment->bill->payment_status) }}</span></p></div>
                <p class="mt-2"><span class="text-slate-500">Payment provider:</span> {{ $receipt->payment->provider === 'paymongo' ? 'PayMongo'.($receipt->payment->is_test ? ' (TEST MODE)' : '') : 'Clinic' }}</p>
                <p class="mt-2"><span class="text-slate-500">Processed by:</span> {{ $receipt->payment->processor?->name ?? 'Clinic system' }}</p>
                <p class="mt-8 text-center text-xs text-slate-400">Thank you for choosing {{ config('clinic.name') }}.</p>
            </footer>
        </article>
    </div>
    @if (auth()->user()->role !== 'patient')
        <style>
            @media print {
                body { background: white !important; }
                body * { visibility: hidden; }
                .receipt-paper, .receipt-paper * { visibility: visible; }
                .receipt-paper { position: absolute; inset: 0; width: 100%; border: 0; padding: 0; box-shadow: none; }
            }
        </style>
        <script>
            const receiptPrintButton = document.querySelector('[data-print-receipt]');
            let receiptPrintInProgress = false;

            receiptPrintButton.addEventListener('click', () => {
                if (receiptPrintInProgress) {
                    return;
                }

                receiptPrintInProgress = true;
                receiptPrintButton.disabled = true;
                window.print();
            });

            window.addEventListener('afterprint', () => {
                receiptPrintInProgress = false;
                receiptPrintButton.disabled = false;
            });
        </script>
    @endif
@endsection
