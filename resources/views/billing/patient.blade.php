@extends('layouts.app')

@section('title', 'My billing')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Patient portal</p><h1 class="mt-1 text-3xl font-bold tracking-tight">My billing</h1><p class="mt-2 text-slate-500">Your bills, payment history, and receipts.</p></div>
    <section class="grid gap-4">
        @forelse ($bills as $bill)
            @php($paid = (float) $bill->payments->sum('applied_amount'))
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><p class="text-sm text-slate-500">{{ $bill->bill_number }} · {{ $bill->created_at->format('M j, Y') }}</p><h2 class="mt-1 text-lg font-semibold">{{ $bill->appointment->service->name ?? 'Dental services' }}</h2></div>
                    <span class="status-badge capitalize">{{ str_replace('_', ' ', $bill->payment_status) }}</span>
                </div>
                <div class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div><p class="text-slate-400">Total</p><p class="mt-1 font-semibold">₱{{ number_format((float) $bill->total, 2) }}</p></div>
                    <div><p class="text-slate-400">Paid</p><p class="mt-1 font-semibold">₱{{ number_format($paid, 2) }}</p></div>
                    <div><p class="text-slate-400">Balance</p><p class="mt-1 font-semibold">₱{{ number_format(max(0, (float) $bill->total - $paid), 2) }}</p></div>
                </div>
                @if ($bill->payments->isNotEmpty())
                    <div class="mt-4 border-t border-slate-100 pt-4"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Receipts</p>
                        @foreach ($bill->payments as $payment)
                            @if ($payment->receipt)<a class="mr-4 inline-flex text-sm font-semibold text-teal-800 hover:underline" href="{{ route('receipts.show', $payment->receipt) }}">{{ $payment->receipt->receipt_number }} →</a>@endif
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No bills are available.</div>
        @endforelse
        <div>{{ $bills->links() }}</div>
    </section>
@endsection
