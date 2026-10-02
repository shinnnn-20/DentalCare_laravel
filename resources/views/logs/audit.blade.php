@extends('layouts.app')

@section('title', 'Audit logs')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Administration</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Audit logs</h1><p class="mt-2 text-slate-500">Important activity performed by clinic accounts.</p></div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date & time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th></tr></thead><tbody>
            @forelse ($logs as $log)
                <tr><td data-label="Date & time" class="whitespace-nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</td><td data-label="User">{{ $log->user?->name ?? 'System' }}</td><td data-label="Role" class="capitalize">{{ $log->role ?? '—' }}</td><td data-label="Action">{{ str_replace('_', ' ', $log->action) }}</td><td data-label="Module">{{ str_replace('_', ' ', $log->module) }}</td><td data-label="Record">{{ $log->record_type ? $log->record_type.' #'.$log->record_id : '—' }}</td><td data-label="Description">{{ $log->description ?? '—' }}</td></tr>
            @empty<tr><td colspan="7" class="py-10 text-center text-slate-500">No activity recorded.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $logs->links() }}</div>
    </section>
@endsection
