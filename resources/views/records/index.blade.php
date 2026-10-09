@extends('layouts.app')

@section('title', 'Dental records')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Patient portal</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Dental records</h1><p class="mt-2 text-slate-500">Your treatment history, shown in chronological order.</p></div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="divide-y divide-slate-100">
            @forelse ($records as $record)
                <article class="grid gap-3 p-5 sm:grid-cols-[150px_1fr]">
                    <div class="text-sm text-slate-500">{{ $record->created_at->format('F j, Y') }}@if ($record->follow_up_date)<p class="mt-2 text-xs text-teal-700">Follow-up: {{ $record->follow_up_date->format('M j, Y') }}</p>@endif</div>
                    <div>
                        <h2 class="font-semibold">{{ $record->treatment }}</h2>
                        <p class="mt-2 text-sm"><span class="font-medium">Diagnosis:</span> {{ $record->diagnosis }}</p>
                        @if ($record->prescription)
                            <p class="mt-2 text-sm"><span class="font-medium">Prescription:</span> {{ $record->prescription }}</p>
                        @endif
                        @if ($record->notes)
                            <p class="mt-2 text-sm text-slate-500">{{ $record->notes }}</p>
                        @endif
                        <section class="mt-4 rounded-xl bg-slate-50 p-4">
                            <h3 class="text-sm font-semibold text-slate-800">Services Performed</h3>
                            @if ($record->appointment?->patient_id === $record->patient_id && $record->appointment?->service)
                                <div class="mt-2 flex flex-wrap items-start justify-between gap-3 text-sm">
                                    <div>
                                        <p class="font-medium">{{ $record->appointment->service->name }}</p>
                                        <p class="mt-1 text-slate-500">Performed {{ $record->appointment->starts_at->format('F j, Y') }} · {{ $record->appointment->service->duration_minutes }} minutes</p>
                                        @if ($record->creator)
                                            <p class="mt-1 text-slate-500">Provider: {{ $record->creator->name }}</p>
                                        @endif
                                        @if ($record->notes)
                                            <p class="mt-1 text-slate-500">Clinical details: {{ $record->notes }}</p>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <p class="mt-2 text-sm text-slate-500">No services recorded for this dental record.</p>
                            @endif
                        </section>
                    </div>
                </article>
            @empty<p class="p-8 text-sm text-slate-500">No dental records available.</p>@endforelse
        </div>
        <div class="p-4">{{ $records->links() }}</div>
    </section>
@endsection
