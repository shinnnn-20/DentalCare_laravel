@extends('layouts.app')

@section('title', 'Dental services')

@section('content')
    <div class="mb-8"><p class="text-sm font-medium text-teal-700">Clinic setup</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Dental services</h1><p class="mt-2 text-slate-500">Active services appear in the appointment booking form.</p></div>
    @if (auth()->user()->role === 'admin')
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Add a service</h2>
            <form method="POST" action="{{ route('admin.services.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <label class="form-label">Service name<input class="form-input" name="name" required maxlength="255"></label>
                <label class="form-label">Description<input class="form-input" name="description" maxlength="2000"></label>
                <label class="form-label">Price (₱)<input class="form-input" type="number" name="price" min="0" step="0.01" required></label>
                <button class="btn-primary self-end">Add service</button>
            </form>
        </section>
    @endif
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="data-table"><thead><tr><th>Service</th><th>Description</th><th>Price</th><th>Status</th>@if (auth()->user()->role === 'admin')<th>Manage</th>@endif</tr></thead>
                <tbody>
                @forelse ($services as $service)
                    <tr>
                        <td data-label="Service" class="font-semibold">{{ $service->name }}</td><td data-label="Description">{{ $service->description ?? '—' }}</td><td data-label="Price">₱{{ number_format((float) $service->price, 2) }}</td><td data-label="Status"><span class="status-badge">{{ $service->is_active ? 'Active' : 'Inactive' }}</span></td>
                        @if (auth()->user()->role === 'admin')
                            <td data-label="Manage">
                                <form method="POST" action="{{ route('admin.services.update', $service) }}" class="grid min-w-72 gap-2">
                                    @csrf @method('PUT')
                                    <input class="form-input" name="name" value="{{ $service->name }}" required>
                                    <input class="form-input" name="description" value="{{ $service->description }}">
                                    <div class="flex gap-2"><input class="form-input" type="number" name="price" min="0" step="0.01" value="{{ $service->price }}" required><select class="form-input" name="is_active"><option value="1" @selected($service->is_active)>Active</option><option value="0" @selected(! $service->is_active)>Inactive</option></select></div>
                                    <button class="btn-secondary">Save changes</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty<tr><td colspan="5" class="py-10 text-center text-slate-500">No services configured yet.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $services->links() }}</div>
    </section>
@endsection
