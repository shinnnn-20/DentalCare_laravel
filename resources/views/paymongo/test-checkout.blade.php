@extends('layouts.app')

@section('title', 'PayMongo test checkout')

@section('content')
    <section class="mx-auto grid max-w-2xl gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Local development only</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">PayMongo GCash test checkout</h1>
            <p class="mt-3 text-slate-600">Create a fixed ₱100 test-mode checkout. This transaction is not connected to a patient, appointment, bill, or payment record.</p>
        </div>

        @if (session('checkout_diagnostic'))
            <p class="text-sm text-slate-500">Local connection diagnostic: <code>{{ session('checkout_diagnostic') }}</code></p>
        @endif

        @if ($result === 'success')
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
                You returned from the hosted checkout. This return page does not prove that a payment succeeded. No {{ config('clinic.name') }} bill or payment record was changed.
            </div>
        @elseif ($result === 'cancelled')
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700" role="status">
                You returned from the hosted checkout without completing it. The return URL does not verify payment, and no {{ config('clinic.name') }} bill or payment record was changed.
            </div>
        @elseif (! $isConfigured)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
                Configure <code>PAYMONGO_SECRET_KEY</code> with a test-only <code>sk_test_</code> key in the ignored local <code>.env</code> file, then run <code>php artisan config:clear</code>. No payment credentials are displayed on this page.
            </div>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                This will request a GCash Checkout Session from PayMongo using test credentials and redirect to PayMongo only after a valid checkout URL is returned.
            </div>
            <form method="POST" action="{{ route('paymongo.test.store') }}">
                @csrf
                <button class="btn-primary" type="submit">Start ₱100 test checkout</button>
            </form>
        @endif
    </section>
@endsection
