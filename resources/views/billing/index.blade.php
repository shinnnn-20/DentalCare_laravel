@extends('layouts.app')

@section('title', 'Billing and payments')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Clinic operations</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Billing & payments</h1><p class="mt-2 text-slate-500">Record transactions and issue a receipt for each payment.</p></div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Bill</th><th>Patient</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Record payment</th></tr></thead><tbody>
            @forelse ($bills as $bill)
                @php($paid = (float) $bill->payments->sum('applied_amount'))
                @php($balance = max(0, (float) $bill->total - $paid))
                <tr id="bill-{{ $bill->id }}" @class(['selected-bill' => $selectedBillId === $bill->id])>
                    <td data-label="Bill" class="font-semibold">{{ $bill->bill_number }}<div class="text-xs font-normal text-slate-400">{{ $bill->created_at->format('M j, Y') }}</div></td>
                    <td data-label="Patient">{{ $bill->patient->user->name }}<div class="text-xs text-slate-400">{{ $bill->patient->patient_number }}</div></td>
                    <td data-label="Total">₱{{ number_format((float) $bill->total, 2) }}</td><td data-label="Paid">₱{{ number_format($paid, 2) }}</td><td data-label="Balance">₱{{ number_format($balance, 2) }}</td>
                    <td data-label="Status"><span class="status-badge capitalize">{{ str_replace('_', ' ', $bill->payment_status) }}</span></td>
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
            @empty<tr><td colspan="7" class="py-10 text-center text-slate-500">No bills yet. Create a bill from an appointment during consultation.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $bills->links() }}</div>
    </section>
    <p class="mt-4 text-sm text-slate-500">Card and GCash entries are recorded manually here. No external payment is charged by this application.</p>
@endsection
