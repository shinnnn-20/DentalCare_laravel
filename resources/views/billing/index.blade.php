@extends('layouts.app')

@section('title', 'Billing and payments')

@section('content')
    @if ($errors->has('checkout'))
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">{{ $errors->first('checkout') }}</div>
    @endif
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
                @php($unreconciledPaymongoCheckout = $bill->payMongoBillCheckouts->contains(fn ($checkout) => $checkout->status === 'paid' && (! $checkout->paid_payment_id || ! $bill->payments->contains(fn ($payment) => $payment->provider === 'paymongo' && $payment->is_test && $payment->provider_transaction_id === $checkout->paid_payment_id))))
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
                        @if ($bill->payMongoBillCheckouts->contains('status', 'review'))
                            <p class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900">
                                A PayMongo test payment was confirmed, but could not be safely applied. Review this payment before starting another checkout.
                            </p>
                        @elseif ($unreconciledPaymongoCheckout)
                            <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                A PayMongo test payment was confirmed but is not yet in this bill’s payment history. Reconcile it before starting another checkout.
                            </p>
                        @endif
                        @foreach ($bill->payMongoBillCheckouts as $checkout)
                            @if ($paymongoTestCheckoutAvailable && $checkout->status === 'pending' && $checkout->checkout_session_id)
                                <form method="POST" action="{{ route('clinic.billing.paymongo.reconcile', $checkout) }}" class="mb-3">
                                    @csrf
                                    <button class="btn-secondary" type="submit">Check existing payment with PayMongo</button>
                                    <p class="mt-1 text-xs text-slate-500">Checks this checkout’s status; it does not start a new charge.</p>
                                </form>
                            @endif
                        @endforeach
                        @if ($balance > 0)
                            @if ($paymongoTestCheckoutAvailable)
                                @if (! $unreconciledPaymongoCheckout && ! $bill->payMongoBillCheckouts->contains('status', 'review'))
                                    <form method="POST" action="{{ route('clinic.billing.paymongo.store', $bill) }}" class="mb-3">
                                        @csrf
                                        <button class="btn-primary" type="submit">Start GCash test checkout</button>
                                        <p class="mt-1 text-xs text-amber-700">Test mode only; a confirmed checkout is recorded as a clearly labeled test payment and applied to this bill.</p>
                                    </form>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('clinic.payments.store', $bill) }}" class="grid gap-2">
                                @csrf
                                <select class="form-input" name="method" required><option value="cash">Cash</option><option value="gcash">GCash</option><option value="other">Other</option></select>
                                <div class="grid grid-cols-2 gap-2"><label class="form-label text-xs">Amount received<input class="form-input" type="text" inputmode="decimal" name="amount" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ number_format($balance, 2, '.', '') }}" required></label><label class="form-label text-xs">Apply to bill<input class="form-input" type="text" inputmode="decimal" name="applied_amount" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ number_format($balance, 2, '.', '') }}" required></label></div>
                                <button class="btn-secondary justify-self-start">Record payment</button>
                            </form>
                        @endif
                        @if ($bill->payments->isNotEmpty())
                            <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment history</p>
                            @foreach ($bill->payments as $payment)
                                    <div class="rounded-lg bg-slate-50 p-2 text-xs">
                                        <p class="font-semibold uppercase text-slate-700">
                                            {{ $payment->method }} · ₱{{ number_format((float) $payment->applied_amount, 2) }}
                                            @if ($payment->provider === 'paymongo' && $payment->is_test)
                                                <span class="text-amber-700">· PayMongo TEST MODE</span>
                                            @endif
                                        </p>
                                        <p class="mt-1 text-slate-500">{{ ($payment->paid_at ?? $payment->created_at)->format('M j, Y g:i A') }}</p>
                                        @if ($payment->provider_transaction_id)
                                            <p class="mt-1 break-all text-slate-500">PayMongo payment: {{ $payment->provider_transaction_id }}</p>
                                            <p class="break-all text-slate-500">Checkout session: {{ $payment->provider_checkout_session_id }}</p>
                                        @endif
                                        @if ($payment->receipt)
                                            <a class="mt-1 inline-block font-semibold text-teal-800 hover:underline" href="{{ route('receipts.show', $payment->receipt) }}">
                                                Receipt {{ $payment->receipt->receipt_number }}
                                            </a>
                                        @endif
                                    </div>
                            @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="9" class="py-10 text-center text-slate-500">No bills yet. Create a bill from an appointment during consultation.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $bills->links() }}</div>
    </section>
    <p class="mt-4 text-sm text-slate-500">Manual cash, GCash, and other entries do not charge an external payment. PayMongo test checkouts are available to staff and admins in local test mode only; confirmed test payments are included in bill balances and reports, and are labeled TEST MODE because no real money is transferred.</p>

    <section class="billing-print-report" data-billing-print-report data-print-all-page aria-label="Printable billing report">
        <header class="billing-print-header">
            <p>{{ config('clinic.name') }}</p>
            <p>{{ config('clinic.address') }}</p>
            <p>{{ config('clinic.phone') }}</p>
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
                    <th>Payment Details</th>
                    <th>OR / Receipt Number</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bills as $bill)
                    @php($paid = (float) $bill->payments->sum('applied_amount'))
                    @php($balance = max(0, (float) $bill->total - $paid))
                    @php($receiptNumbers = $bill->payments->map(fn ($payment) => $payment->receipt?->receipt_number ? $payment->receipt->receipt_number.($payment->is_test ? ' (TEST MODE)' : '') : null)->filter()->implode(', '))
                    <tr>
                        <td>{{ $bill->bill_number }}</td>
                        <td>{{ $bill->created_at->format('M j, Y') }}</td>
                        <td>{{ $bill->patient->user->name }}</td>
                        <td>{{ $bill->patient->patient_number }}</td>
                        <td>₱{{ number_format((float) $bill->total, 2) }}</td>
                        <td>₱{{ number_format($paid, 2) }}</td>
                        <td>₱{{ number_format($balance, 2) }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($bill->payment_status)) }}</td>
                        <td>
                            @forelse ($bill->payments as $payment)
                                <div>
                                    {{ strtoupper($payment->method) }}
                                    @if ($payment->provider === 'paymongo' && $payment->is_test)
                                        · PayMongo TEST MODE
                                    @endif
                                    @if ($payment->provider_transaction_id)
                                        · {{ $payment->provider_transaction_id }}
                                    @endif
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{{ $receiptNumbers !== '' ? $receiptNumbers : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10">No billing records are displayed.</td></tr>
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
                    <strong>{{ $bill->payments->map(fn ($payment) => $payment->receipt?->receipt_number ? $payment->receipt->receipt_number.($payment->is_test ? ' (TEST MODE)' : '') : null)->filter()->implode(', ') ?: '—' }}</strong>
                </div>
            </section>

            @if ($bill->payments->isNotEmpty())
                <section class="bill-print-payments">
                    <h3>Payment details</h3>
                    <table>
                        <thead><tr><th>Date</th><th>Payment method / provider</th><th>Amount applied</th><th>Provider reference</th><th>OR / Receipt No</th></tr></thead>
                        <tbody>
                            @foreach ($bill->payments as $payment)
                                <tr>
                                    <td>{{ ($payment->paid_at ?? $payment->created_at)->format('M j, Y g:i A') }}</td>
                                    <td>
                                        {{ strtoupper($payment->method) }}
                                        @if ($payment->provider === 'paymongo' && $payment->is_test)
                                            · PayMongo TEST MODE
                                        @endif
                                    </td>
                                    <td>₱{{ number_format((float) $payment->applied_amount, 2) }}</td>
                                    <td>
                                        @if ($payment->provider_transaction_id)
                                            Payment: {{ $payment->provider_transaction_id }}<br>
                                            Session: {{ $payment->provider_checkout_session_id }}
                                        @else
                                            —
                                        @endif
                                    </td>
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
