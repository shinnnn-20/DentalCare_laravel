@extends('layouts.app')

@section('title', 'Billing and payments')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print-hidden">
        <div><p class="text-sm font-medium text-teal-700">Clinic operations</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Billing & payments</h1><p class="mt-2 text-slate-500">Record transactions and issue a receipt for each payment.</p></div>
        <button class="btn-secondary gap-2" type="button" data-print-all-bills>
            🖨 Print All
        </button>
    </div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Bill</th><th>Patient</th><th>Services</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th><th>Record payment</th></tr></thead><tbody>
            @forelse ($bills as $bill)
                @php($paid = (float) $bill->payments->sum('applied_amount'))
                @php($balance = max(0, (float) $bill->total - $paid))
                <tr id="bill-{{ $bill->id }}" @class(['selected-bill' => $selectedBillId === $bill->id])>
                    <td data-label="Bill" class="font-semibold">{{ $bill->bill_number }}<div class="text-xs font-normal text-slate-400">{{ $bill->created_at->format('M j, Y') }}</div></td>
                    <td data-label="Patient">{{ $bill->patient->user->name }}<div class="text-xs text-slate-400">{{ $bill->patient->patient_number }}</div></td>
                    <td data-label="Services" class="min-w-56">
                        @forelse ($bill->items as $item)
                            <p class="text-sm font-medium">{{ $item->service_name }}</p>
                            <p class="mb-2 text-xs text-slate-500">{{ $item->quantity }} × ₱{{ number_format((float) $item->unit_price, 2) }} = ₱{{ number_format((float) $item->subtotal, 2) }}</p>
                        @empty
                            <span class="text-sm text-slate-500">No services recorded for this bill.</span>
                        @endforelse
                    </td>
                    <td data-label="Total">₱{{ number_format((float) $bill->total, 2) }}</td><td data-label="Paid">₱{{ number_format($paid, 2) }}</td><td data-label="Balance">₱{{ number_format($balance, 2) }}</td>
                    <td data-label="Status"><span class="status-badge capitalize">{{ str_replace('_', ' ', $bill->payment_status) }}</span></td>
                    <td data-label="Action"><button class="btn-small whitespace-nowrap" type="button" data-print-bill="bill-print-{{ $bill->id }}">🖨 Print Bill</button></td>
                    <td data-label="Record payment" class="min-w-80">
                        @if ($balance > 0)
                            <form method="POST" action="{{ route('clinic.payments.store', $bill) }}" class="grid gap-2">
                                @csrf
                                <select class="form-input" name="method" required><option value="cash">Cash</option><option value="gcash">GCash</option><option value="card">Card (manual)</option><option value="other">Other</option></select>
                                <div class="grid grid-cols-2 gap-2"><label class="form-label text-xs">Amount received<input class="form-input" type="number" name="amount" min="0.01" step="0.01" value="{{ number_format($balance, 2, '.', '') }}" required></label><label class="form-label text-xs">Apply to bill<input class="form-input" type="number" name="applied_amount" min="0.01" max="{{ number_format($balance, 2, '.', '') }}" step="0.01" value="{{ number_format($balance, 2, '.', '') }}" required></label></div>
                                <button class="btn-secondary justify-self-start">Record payment</button>
                            </form>
                        @else
                            @foreach ($bill->payments as $payment)
                                @if ($payment->receipt)<a class="block text-sm font-semibold text-teal-800 hover:underline" href="{{ route('receipts.show', $payment->receipt) }}">{{ $payment->receipt->receipt_number }}</a>@endif
                            @endforeach
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="9" class="py-10 text-center text-slate-500">No bills yet. Create a bill from an appointment during consultation.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $bills->links() }}</div>
    </section>
    <p class="mt-4 text-sm text-slate-500">Card and GCash entries are recorded manually here. No external payment is charged by this application.</p>

    <section class="billing-print-report" data-billing-print-report data-print-all-page aria-label="Printable billing report">
        <header class="billing-print-header">
            <p>{{ config('clinic.name') }}</p>
            <h1>Billing &amp; Payments</h1>
            <p>Generated {{ now()->format('F j, Y g:i A') }}</p>
        </header>
        <table class="billing-print-table">
            <thead>
                <tr>
                    <th>Bill Number</th>
                    <th>Bill Date</th>
                    <th>Patient Name</th>
                    <th>Patient ID</th>
                    <th>Total Amount</th>
                    <th>Amount Paid</th>
                    <th>Remaining Balance</th>
                    <th>Payment Status</th>
                    <th>OR / Receipt Number</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bills as $bill)
                    @php($paid = (float) $bill->payments->sum('applied_amount'))
                    @php($balance = max(0, (float) $bill->total - $paid))
                    @php($receiptNumbers = $bill->payments->pluck('receipt.receipt_number')->filter()->implode(', '))
                    <tr>
                        <td>{{ $bill->bill_number }}</td>
                        <td>{{ $bill->created_at->format('M j, Y') }}</td>
                        <td>{{ $bill->patient->user->name }}</td>
                        <td>{{ $bill->patient->patient_number }}</td>
                        <td>₱{{ number_format((float) $bill->total, 2) }}</td>
                        <td>₱{{ number_format($paid, 2) }}</td>
                        <td>₱{{ number_format($balance, 2) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($bill->payment_status)) }}</td>
                        <td>{{ $receiptNumbers !== '' ? $receiptNumbers : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">No billing records are displayed.</td></tr>
                @endforelse
            </tbody>
        </table>
        <footer class="billing-print-footer">
            {{ $bills->firstItem() ?? 0 }}–{{ $bills->lastItem() ?? 0 }} of {{ $bills->total() }} displayed billing records
        </footer>
    </section>

    @foreach ($bills as $bill)
        @php($paid = (float) $bill->payments->sum('applied_amount'))
        @php($balance = max(0, (float) $bill->total - $paid))
        <article class="billing-print-paper" id="bill-print-{{ $bill->id }}">
            <header class="bill-print-header">
                <h1>{{ config('clinic.name') }}</h1>
                @if (config('clinic.address') !== 'Clinic address not configured')
                    <p>{{ config('clinic.address') }}</p>
                @endif
                @if (config('clinic.phone') !== 'Clinic contact not configured')
                    <p>{{ config('clinic.phone') }}</p>
                @endif
                <h2>Bill / Payment Receipt</h2>
            </header>

            <section class="bill-print-details">
                <div><span>Bill No</span><strong>{{ $bill->bill_number }}</strong></div>
                <div><span>Date</span><strong>{{ $bill->created_at->format('M j, Y') }}</strong></div>
                <div><span>Patient</span><strong>{{ $bill->patient->user->name }}</strong></div>
                <div><span>Patient ID</span><strong>{{ $bill->patient->patient_number }}</strong></div>
            </section>

            @if ($bill->items->isNotEmpty())
                <section class="bill-print-items">
                    <h3>Services / Items</h3>
                    <table>
                        <thead><tr><th>Service / Item</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead>
                        <tbody>
                            @foreach ($bill->items as $item)
                                <tr>
                                    <td>{{ $item->service_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td>{{ config('clinic.currency_symbol') }}{{ number_format((float) $item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            <section class="bill-print-summary">
                <div><span>Total Amount</span><strong>₱{{ number_format((float) $bill->total, 2) }}</strong></div>
                <div><span>Amount Paid</span><strong>₱{{ number_format($paid, 2) }}</strong></div>
                <div><span>Remaining Balance</span><strong>₱{{ number_format($balance, 2) }}</strong></div>
                <div><span>Status</span><strong>{{ strtoupper(str_replace('_', ' ', $bill->payment_status)) }}</strong></div>
                <div>
                    <span>OR / Receipt Number</span>
                    <strong>{{ $bill->payments->pluck('receipt.receipt_number')->filter()->implode(', ') ?: '—' }}</strong>
                </div>
            </section>

            @if ($bill->payments->isNotEmpty())
                <section class="bill-print-payments">
                    <h3>Payment details</h3>
                    <table>
                        <thead><tr><th>Date</th><th>Payment method</th><th>Amount applied</th><th>OR / Receipt No</th></tr></thead>
                        <tbody>
                            @foreach ($bill->payments as $payment)
                                <tr>
                                    <td>{{ $payment->created_at->format('M j, Y') }}</td>
                                    <td>{{ strtoupper($payment->method) }}</td>
                                    <td>₱{{ number_format((float) $payment->applied_amount, 2) }}</td>
                                    <td>{{ $payment->receipt?->receipt_number ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        </article>
    @endforeach

    <script>
        const clearBillPrintSelection = () => {
            document.body.classList.remove('printing-single-bill');
            document.body.classList.remove('printing-all-bills');
            document.querySelectorAll('.billing-print-paper.is-print-target').forEach((paper) => {
                paper.classList.remove('is-print-target');
            });
        };

        let printInProgress = false;
        const beginBillPrint = (mode, papers = []) => {
            if (printInProgress) {
                return false;
            }
            printInProgress = true;
            clearBillPrintSelection();
            papers.forEach((paper) => paper.classList.add('is-print-target'));
            document.body.classList.add(mode);
            document.querySelectorAll('[data-print-bill], [data-print-all-bills]').forEach((button) => {
                button.disabled = true;
            });
            window.print();
            return true;
        };

        document.querySelector('[data-print-all-bills]').addEventListener('click', () => {
            beginBillPrint('printing-all-bills');
        });

        document.querySelectorAll('[data-print-bill]').forEach((button) => {
            button.addEventListener('click', () => {
                const paper = document.getElementById(button.dataset.printBill);

                if (!paper) {
                    throw new Error('The selected bill print layout could not be found.');
                }

                beginBillPrint('printing-single-bill', [paper]);
            });
        });

        window.addEventListener('afterprint', () => {
            clearBillPrintSelection();
            document.body.classList.remove('printing-all-bills');
            printInProgress = false;
            document.querySelectorAll('[data-print-bill], [data-print-all-bills]').forEach((button) => {
                button.disabled = false;
            });
        });
    </script>
@endsection
