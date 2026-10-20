@extends('layouts.app')

@section('title', 'GCash test checkout')

@section('content')
    <section class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Local development only</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">GCash bill checkout — test mode</h1>
        @if ($result === 'success')
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
                You returned from PayMongo. The return page does not confirm payment; the signed webhook or a staff status check must verify it. A confirmed test payment is recorded on this bill but does not transfer real money.
            </div>
        @else
            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700" role="status">
                You returned without completing the hosted checkout. The return URL does not verify payment, and no payment was applied to this bill.
            </div>
        @endif
        <a class="mt-6 inline-flex text-sm font-semibold text-teal-800 hover:underline" href="{{ route('patient.billing') }}">Return to My billing</a>
    </section>
@endsection
