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
                    </div>
                </article>
            @empty<p class="p-8 text-sm text-slate-500">No dental records available.</p>@endforelse
        </div>
        <div class="p-4">{{ $records->links() }}</div>
    </section>
@endsection
