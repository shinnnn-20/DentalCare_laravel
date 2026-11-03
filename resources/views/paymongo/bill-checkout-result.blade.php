@extends('layouts.app')

@section('title', 'GCash checkout status')

@section('content')
    @php($returnedFromCheckout = $result === 'success')
    <section class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
            <div class="grid md:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="p-6 sm:p-10">
                    <p class="inline-flex items-center gap-2 rounded-full bg-teal-50 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em] text-teal-800">
                        <span class="h-2 w-2 rounded-full bg-teal-600"></span>
                        GCash checkout · Test mode
                    </p>

                    @if ($returnedFromCheckout)
                        <div class="mt-8 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-800" aria-hidden="true">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"></path>
                            </svg>
                        </div>
                        <p class="mt-6 text-sm font-semibold text-amber-800" role="status">Payment returned · verification pending</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">We’re verifying your payment</h1>
                        <p class="mt-4 max-w-xl text-base leading-7 text-slate-600">
                            You returned from PayMongo. This page does not confirm that a payment succeeded. Your bill will update only after PayMongo’s signed confirmation or a staff verification is recorded.
                        </p>
                    @else
                        <div class="mt-8 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-600" aria-hidden="true">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8 8 8 8M16 8l-8 8"></path>
                                <circle cx="12" cy="12" r="9"></circle>
                            </svg>
                        </div>
                        <p class="mt-6 text-sm font-semibold text-slate-600" role="status">Checkout not completed</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Your bill is still available</h1>
                        <p class="mt-4 max-w-xl text-base leading-7 text-slate-600">
                            The checkout was not completed. This return page cannot determine or confirm a payment; check your bill for its latest verified status before trying again.
                        </p>
                    @endif

                    <a class="mt-8 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 sm:w-auto"
                        href="{{ $returnUrl }}">
                        {{ $returnLabel }}
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"></path>
                        </svg>
                    </a>
                </div>

                <aside class="flex flex-col justify-center gap-4 border-t border-slate-200 bg-slate-50 p-6 sm:p-8 md:border-l md:border-t-0">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-teal-800 shadow-sm ring-1 ring-slate-200" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.5c0 4.4 3.2 7.8 7.5 9.5 4.3-1.7 7.5-5.1 7.5-9.5V6L12 3Z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-900">Payment safety</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Only a confirmed payment notification or staff verification changes your billing record. Returning to this page alone never marks a bill as paid.
                        </p>
                    </div>
                    <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
                        This is a local development test checkout. Confirmed test payments are recorded for testing only and do not transfer real money.
                    </p>
                </aside>
            </div>
        </div>
    </section>
@endsection
