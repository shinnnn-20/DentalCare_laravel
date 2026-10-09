@extends('layouts.app')

@section('title', 'Receipt '.$receipt->receipt_number)

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-4 flex justify-end gap-2 print:hidden"><button class="btn-primary" type="button" data-print-receipt>Print / save PDF</button><a class="btn-secondary" href="{{ auth()->user()->role === 'patient' ? route('patient.billing') : route('clinic.billing.index') }}">Back to billing</a></div>
        <article class="receipt-paper rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12">
            <header class="border-b border-slate-200 pb-6 text-center">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-teal-700 text-lg font-bold text-white print:hidden">D</div>
                <h1 class="mt-3 text-2xl font-bold">{{ config('clinic.name') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ config('clinic.address') }} · {{ config('clinic.phone') }}</p>
                <p class="mt-5 text-sm font-semibold uppercase tracking-[0.2em] text-teal-800">Official receipt</p>
            </header>
            <div class="grid gap-5 border-b border-slate-200 py-6 text-sm sm:grid-cols-2">
                <div><p class="text-slate-400">Receipt number</p><p class="mt-1 font-semibold">{{ $receipt->receipt_number }}</p></div>
                <div><p class="text-slate-400">Transaction ID</p><p class="mt-1 font-semibold">{{ $receipt->payment->transaction_id }}</p></div>
                <div><p class="text-slate-400">Patient</p><p class="mt-1 font-semibold">{{ $receipt->payment->bill->patient->user->name }} · {{ $receipt->payment->bill->patient->patient_number }}</p></div>
                <div><p class="text-slate-400">Date & time</p><p class="mt-1 font-semibold">{{ $receipt->created_at->format('F j, Y · g:i A') }}</p></div>
            </div>
            <div class="overflow-x-auto py-6">
                <table class="data-table !px-0"><thead><tr><th>Service</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr></thead><tbody>
                    @foreach ($receipt->payment->bill->items as $item)
                        <tr><td data-label="Service">{{ $item->service_name }}</td><td data-label="Qty">{{ $item->quantity }}</td><td data-label="Unit price">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }}</td><td data-label="Subtotal">{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
            <div class="ml-auto grid max-w-sm gap-2 border-t border-slate-200 pt-5 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->subtotal, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Discount</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->discount, 2) }}</span></div>
                <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->bill->total, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Amount applied</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->applied_amount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Amount received</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->amount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Change</span><span>{{ config('clinic.currency_symbol') }}{{ number_format((float) $receipt->payment->change_amount, 2) }}</span></div>
            </div>
            <footer class="mt-8 border-t border-slate-200 pt-5 text-sm">
                <div class="flex flex-wrap justify-between gap-3"><p><span class="text-slate-500">Payment method:</span> <span class="font-semibold uppercase">{{ $receipt->payment->method }}</span></p><p><span class="text-slate-500">Bill status:</span> <span class="font-semibold uppercase">{{ str_replace('_', ' ', $receipt->payment->bill->payment_status) }}</span></p></div>
                <p class="mt-2"><span class="text-slate-500">Processed by:</span> {{ $receipt->payment->processor?->name ?? 'Clinic staff' }}</p>
                <p class="mt-8 text-center text-xs text-slate-400">Thank you for choosing DentalCare Clinic.</p>
            </footer>
        </article>
    </div>
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
@endsection
