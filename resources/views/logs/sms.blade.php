@extends('layouts.app')

@section('title', 'SMS logs')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Administration</p><h1 class="mt-1 text-3xl font-bold tracking-tight">SMS logs</h1><p class="mt-2 text-slate-500">Delivery attempts and errors from the configured SMS gateway.</p></div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date</th><th>Patient</th><th>Phone</th><th>Type</th><th>Message</th><th>Status</th><th>Error</th></tr></thead><tbody>
            @forelse ($logs as $log)
                <tr><td data-label="Date" class="whitespace-nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</td><td data-label="Patient">{{ $log->patient?->user?->name ?? '—' }}</td><td data-label="Phone">{{ $log->phone }}</td><td data-label="Type">{{ str_replace('_', ' ', $log->type) }}</td><td data-label="Message" class="max-w-sm">{{ $log->message }}</td><td data-label="Status"><span class="status-badge capitalize">{{ $log->status }}</span></td><td data-label="Error" class="text-rose-700">{{ $log->error ?? '—' }}</td></tr>
            @empty<tr><td colspan="7" class="py-10 text-center text-slate-500">No SMS attempts recorded.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $logs->links() }}</div>
    </section>
@endsection
