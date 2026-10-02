@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Patient portal</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Notifications</h1><p class="mt-2 text-slate-500">Appointment and payment messages sent to your registered contact number.</p></div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="divide-y divide-slate-100">
            @forelse ($logs as $log)
                <article class="flex flex-wrap items-start justify-between gap-4 p-5">
                    <div><p class="font-semibold capitalize">{{ str_replace('_', ' ', $log->type) }}</p><p class="mt-2 max-w-3xl text-sm text-slate-600">{{ $log->message }}</p><p class="mt-2 text-xs text-slate-400">{{ $log->created_at->format('M j, Y · g:i A') }} · {{ $log->phone }}</p></div>
                    <span class="status-badge capitalize">{{ $log->status }}</span>
                </article>
            @empty<p class="p-8 text-sm text-slate-500">No notifications recorded.</p>@endforelse
        </div>
        <div class="p-4">{{ $logs->links() }}</div>
    </section>
@endsection
