@extends('layouts.app')

@section('title', 'Audit logs')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Administration</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Audit logs</h1><p class="mt-2 text-slate-500">Important activity performed by clinic accounts.</p></div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <form method="GET" class="grid gap-3 border-b border-slate-100 bg-slate-50/60 p-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="form-label">Search activity<input class="form-input" type="search" name="search" value="{{ request('search') }}" placeholder="User, action, record or details"></label>
            <label class="form-label">Module<select class="form-input" name="module"><option value="">All modules</option>@foreach ($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ str_replace('_', ' ', ucfirst($module)) }}</option>@endforeach</select></label>
            <label class="form-label">Action<select class="form-input" name="action"><option value="">All actions</option>@foreach ($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ str_replace('_', ' ', ucfirst($action)) }}</option>@endforeach</select></label>
            <label class="form-label">From<input class="form-input" type="date" name="from" value="{{ request('from') }}"></label>
            <label class="form-label">To<input class="form-input" type="date" name="to" value="{{ request('to') }}"></label>
            <div class="flex items-end gap-3">
                <label class="form-label flex-1">Order<select class="form-input" name="sort"><option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option></select></label>
                <button class="btn-secondary">Apply</button>
            </div>
        </form>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date & time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th></tr></thead><tbody>
            @forelse ($logs as $log)
                <tr><td data-label="Date & time" class="whitespace-nowrap">{{ $log->created_at->timezone(config('clinic.timezone'))->format('M j, Y g:i A') }}</td><td data-label="User">{{ $log->user?->name ?? 'System' }}</td><td data-label="Role" class="capitalize">{{ $log->role ?? '—' }}</td><td data-label="Action">{{ str_replace('_', ' ', $log->action) }}</td><td data-label="Module">{{ str_replace('_', ' ', $log->module) }}</td><td data-label="Record">{{ $log->record_type ? $log->record_type.' #'.$log->record_id : '—' }}</td><td data-label="Description">{{ $log->description ?? '—' }}</td></tr>
            @empty<tr><td colspan="7" class="py-10 text-center text-slate-500">No activity recorded.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $logs->links() }}</div>
    </section>
@endsection
