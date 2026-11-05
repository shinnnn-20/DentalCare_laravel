@extends('layouts.app')

@section('title', $isPreview ? 'Payment Receipt Preview' : 'Acknowledgement Receipt '.$receipt->receipt_number)

@section('content')
    <div class="receipt-page mx-auto max-w-3xl">
        <div class="mb-4 flex justify-end gap-2 print:hidden">
            @if (auth()->user()->role !== 'patient')
                <button class="btn-primary" type="button" data-print-receipt>Print</button>
            @endif
            <a class="btn-secondary" href="{{ auth()->user()->role === 'patient' ? route('patient.billing') : route('clinic.billing.index') }}">Back to billing</a>
        </div>
        <article class="receipt-paper billing-print-paper rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12">
            <header class="bill-print-header mb-6 flex items-center justify-center gap-4 border-b border-slate-200 pb-5 text-left">
                <img class="bill-print-logo h-16 w-16 object-contain" src="{{ asset('images/landing/doc-brigs-logo.png') }}" alt="Doc Brigs Dental Care logo">
                <div class="bill-print-brand text-center">
                    <h1 class="text-2xl font-bold">{{ config('clinic.name') }}</h1>
                    @if (config('clinic.address') !== 'Clinic address not configured')
                        <p class="mt-1 text-sm text-slate-500">{{ config('clinic.address') }}</p>
                    @endif
                    @if (config('clinic.phone') !== 'Clinic contact not configured')
                        <p class="mt-1 text-sm text-slate-500">{{ config('clinic.phone') }}</p>
                    @endif
                    <h2 class="mt-3 text-lg font-bold uppercase tracking-wide text-teal-800">ACKNOWLEDGEMENT RECEIPT (AR) ONLY</h2>
                </div>
            </header>
            <p class="bill-print-disclaimer mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-center text-sm font-semibold leading-6 text-amber-950">ACKNOWLEDGEMENT RECEIPT ONLY – This document acknowledges receipt of payment and is not an Official Receipt or a BIR-compliant Sales Invoice.</p>
            @if ($isPreview)
                <p class="receipt-preview-label mb-4 text-sm font-semibold text-slate-700">Payment Receipt Preview</p>
            @endif
            @if ($receipt->payment->provider === 'paymongo' && $receipt->payment->is_test)
                <p class="receipt-test-mode mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm font-bold uppercase tracking-wide text-amber-900">PayMongo test mode — no real money transferred</p>
            @endif
            <section class="bill-print-details grid gap-3 border-b border-slate-200 py-5 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3"><span class="text-slate-500">Bill No</span><strong class="text-right font-semibold">{{ $receipt->payment->bill->bill_number }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Date</span><strong class="text-right font-semibold">{{ $receipt->payment->bill->created_at->timezone(config('clinic.timezone'))->format('M j, Y') }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Patient</span><strong class="text-right font-semibold">{{ $receipt->payment->bill->patient->user->name }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Patient ID</span><strong class="text-right font-semibold">{{ $receipt->payment->bill->patient->patient_number }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Processed by</span><strong class="text-right font-semibold">{{ $receipt->payment->processor?->name ?? 'Clinic system' }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Payment date &amp; time</span><strong class="text-right font-semibold">{{ ($receipt->payment->paid_at ?? $receipt->created_at)->timezone(config('clinic.timezone'))->format('M j, Y g:i A') }}</strong></div>
            </section>
            @php($originalItems = $receipt->payment->bill->items->where('is_add_on', false))
            @php($addOnItems = $receipt->payment->bill->items->where('is_add_on', true))
            @php($billAmountPaid = (float) $receipt->payment->bill->payments->sum('applied_amount'))
            @php($billBalance = max(0, (float) $receipt->payment->bill->total - $billAmountPaid))
            <section class="bill-print-items mt-6">
                <h3 class="mb-2 font-semibold">Services / Items</h3>
                @if ($originalItems->isNotEmpty())
                    <h4 class="mb-2 mt-4 font-medium">Original Services</h4>
                    <div class="overflow-x-auto">
                        <table class="data-table !px-0"><thead><tr><th>Service / Item</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead><tbody>
                            @foreach ($originalItems as $item)
                                <tr><td data-label="Service">{{ $item->service_name }}</td><td data-label="Qty">{{ $item->quantity }}</td><td data-label="Unit price">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }}</td><td data-label="Subtotal">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                @endif
                @if ($addOnItems->isNotEmpty())
                    <h4 class="mb-2 mt-4 font-medium">Add-on Services</h4>
                    <div class="overflow-x-auto">
                        <table class="data-table !px-0"><thead><tr><th>Service / Item</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead><tbody>
                            @foreach ($addOnItems as $item)
                                <tr><td data-label="Service / Item"><strong>Add-on Service</strong><br>{{ $item->service_name }}</td><td data-label="Qty">{{ $item->quantity }}</td><td data-label="Unit price">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }}</td><td data-label="Amount">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                @endif
                @if ($receipt->payment->bill->items->isEmpty())
                    <p class="text-sm text-slate-500">No services recorded for this bill.</p>
                @endif
            </section>
            <section class="bill-print-summary grid w-full gap-2 border-y border-slate-300 py-4 text-sm">
                <div class="flex justify-between gap-3"><span class="text-slate-500">{{ $receipt->payment->bill->discountLabel() }}</span><strong>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->discount, 2) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Total Amount</span><strong>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->total, 2) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Amount Paid</span><strong>{{ config('clinic.currency_symbol') }}{{ number_format($billAmountPaid, 2) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Remaining Balance</span><strong>{{ config('clinic.currency_symbol') }}{{ number_format($billBalance, 2) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Amount Applied</span><strong>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->applied_amount, 2) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Amount Received</span><strong>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->amount, 2) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Change</span><strong>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->change_amount, 2) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">Status</span><strong>{{ strtoupper(str_replace('_', ' ', $receipt->payment->bill->payment_status)) }}</strong></div>
                <div class="flex justify-between gap-3"><span class="text-slate-500">AR / Receipt Number</span><strong>{{ $receipt->receipt_number }}</strong></div>
            </section>
            <section class="bill-print-payments mt-6">
                <h3 class="mb-2 font-semibold">Payment details</h3>
                <table>
                    <thead><tr><th>Date</th><th>Payment method / provider</th><th>Amount applied</th><th>Provider reference</th><th>AR / Receipt No</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>{{ ($receipt->payment->paid_at ?? $receipt->created_at)->timezone(config('clinic.timezone'))->format('M j, Y g:i A') }}</td>
                            <td>{{ strtoupper($receipt->payment->method) }}@if ($receipt->payment->provider === 'paymongo') · PayMongo{{ $receipt->payment->is_test ? ' TEST MODE' : '' }}@endif</td>
                            <td>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->applied_amount, 2) }}</td>
                            <td>
                                @if (auth()->user()->role !== 'patient' && $receipt->payment->provider_transaction_id)
                                    Payment: {{ $receipt->payment->provider_transaction_id }}<br>
                                    Session: {{ $receipt->payment->provider_checkout_session_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $receipt->receipt_number }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <footer class="receipt-print-footer mt-8 border-t border-slate-200 pt-5 text-sm">
                <p class="mt-8 text-center text-xs text-slate-400">Thank you for choosing {{ config('clinic.name') }}.</p>
            </footer>
        </article>
    </div>
    @if (auth()->user()->role !== 'patient')
        <script>
            const receiptPrintButton = document.querySelector('[data-print-receipt]');
            const receiptPrintPaper = document.querySelector('.receipt-paper');
            let receiptPrintInProgress = false;

            const prepareReceiptPrint = async () => {
                const images = Array.from(receiptPrintPaper.querySelectorAll('img'));

                await Promise.all(images.map(async (image) => {
                    if (!image.complete) {
                        await new Promise((resolve, reject) => {
                            image.addEventListener('load', resolve, { once: true });
                            image.addEventListener('error', () => reject(new Error('The clinic logo could not be loaded.')), { once: true });
                        });
                    }

                    if (image.naturalWidth === 0) {
                        throw new Error('The clinic logo could not be loaded.');
                    }

                    await image.decode();
                }));
            };

            receiptPrintButton.addEventListener('click', async () => {
                if (receiptPrintInProgress) {
                    return;
                }

                receiptPrintInProgress = true;
                receiptPrintButton.disabled = true;

                try {
                    await prepareReceiptPrint();
                    document.body.classList.add('printing-receipt');
                    receiptPrintPaper.classList.add('is-print-target');
                    window.print();
                } catch (error) {
                    console.error(error);
                    receiptPrintInProgress = false;
                    receiptPrintButton.disabled = false;
                    window.showAppAlert('The clinic logo could not be loaded, so the receipt was not printed. Please try again.', {
                        title: 'Unable to print',
                        variant: 'error',
                    });
                }
            });

            window.addEventListener('afterprint', () => {
                receiptPrintInProgress = false;
                receiptPrintButton.disabled = false;
                document.body.classList.remove('printing-receipt');
                receiptPrintPaper.classList.remove('is-print-target');
            });
        </script>
    @endif
@endsection
