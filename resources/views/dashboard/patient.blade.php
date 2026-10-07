@extends('layouts.app')

@section('title', 'Patient dashboard')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">Patient portal</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Hello, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-slate-500">Your appointments, treatment history, and billing in one place.</p>
    </div>
    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-2xl bg-teal-800 p-6 text-white xl:col-span-2">
            <p class="text-sm font-medium text-teal-100">Next appointment</p>
            @if ($nextAppointment)
                <span class="mt-4 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide">Next visit</span>
                <h2 class="mt-3 text-2xl font-bold">{{ $nextAppointment->service->name }}</h2>
                <p class="sr-only">{{ $nextAppointment->starts_at->format('l, F j, Y · g:i A') }}</p>
                <div class="mt-3 grid gap-2 text-sm text-teal-50 sm:grid-cols-2" aria-hidden="true">
                    <p class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10">▦</span>{{ $nextAppointment->starts_at->format('l, F j, Y') }}</p>
                    <p class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10">◷</span>{{ $nextAppointment->starts_at->format('g:i A') }}</p>
                </div>
                <span class="mt-4 inline-flex rounded-full bg-white/15 px-3 py-1 text-sm capitalize">{{ str_replace('_', ' ', $nextAppointment->status) }}</span>
            @else
                <h2 class="mt-3 text-2xl font-bold">No upcoming appointments</h2>
                <p class="mt-2 text-teal-100">Book a visit and the clinic will confirm your requested time.</p>
            @endif
            <a href="{{ route('appointments.index') }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-white px-4 py-2.5 text-center text-sm font-semibold text-teal-900 hover:bg-teal-50 sm:w-auto">Book or manage appointments</a>
        </section>
        <section class="stat-card">
            <p class="text-sm font-medium text-slate-500">Outstanding bills</p>
            <p class="mt-3 text-3xl font-bold text-slate-900">{{ $unpaidBills->count() }}</p>
            <a class="mt-4 inline-flex text-sm font-semibold text-teal-800 hover:underline" href="{{ route('patient.billing') }}">View billing history →</a>
        </section>
    </div>
    <section class="mt-8 rounded-2xl border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div><h2 class="font-semibold text-slate-900">Recent dental records</h2><p class="mt-1 text-sm text-slate-500">Your private clinical history</p></div>
            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('patient.records') }}">View all</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($recentRecords as $record)
                <article class="grid gap-2 px-5 py-4 sm:grid-cols-[150px_1fr]">
                    <p class="text-sm text-slate-500">{{ $record->created_at->format('M j, Y') }}</p>
                    <div><h3 class="font-semibold">{{ $record->treatment }}</h3><p class="mt-1 text-sm text-slate-600">{{ $record->diagnosis }}</p></div>
                </article>
            @empty
                <p class="px-5 py-8 text-sm text-slate-500">No dental records are available yet.</p>
            @endforelse
        </div>
    </section>
@endsection
