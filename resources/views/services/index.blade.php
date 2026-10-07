@extends('layouts.app')

@section('title', 'Dental services')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">Clinic setup</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Dental services</h1>
        <p class="mt-2 max-w-2xl text-slate-500">Review clinic treatments and pricing. Active services are available when patients request an appointment.</p>
    </div>

    @if (auth()->user()->role === 'admin')
        <section class="mb-7 overflow-hidden rounded-3xl border border-teal-100 bg-white shadow-sm">
            <div class="flex items-start gap-3 border-b border-teal-100 bg-teal-50/70 px-5 py-4 sm:px-6">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-700 text-lg font-bold text-white" aria-hidden="true">+</span>
                <div>
                    <h2 class="font-semibold text-slate-900">Add a service</h2>
                    <p class="mt-1 text-sm text-slate-500">Create a treatment option for the appointment booking form.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.services.store') }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-[1fr_1.4fr_12rem_auto]">
                @csrf
                <label class="form-label">Service name
                    <input class="form-input" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. Dental cleaning">
                </label>
                <label class="form-label">Description
                    <input class="form-input" name="description" value="{{ old('description') }}" maxlength="2000" placeholder="A short description for patients">
                </label>
                <label class="form-label">Price
                    <span class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-500">₱</span>
                        <input class="form-input pl-8" type="number" name="price" value="{{ old('price') }}" min="0" max="1000000" step="0.01" required placeholder="0.00">
                    </span>
                </label>
                <button class="btn-primary w-full self-end sm:w-auto">Add service</button>
            </form>
        </section>
    @endif

    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Service catalog</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $services->total() }} {{ \Illuminate\Support\Str::plural('service', $services->total()) }} configured</p>
            </div>
            <p class="text-xs font-medium text-slate-500">Prices shown in clinic currency (₱)</p>
        </div>
        @if ($services->isEmpty())
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                <p class="font-semibold text-slate-800">No services configured yet</p>
                <p class="mt-1 text-sm text-slate-500">Add a service above to make it available for appointments.</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                @foreach ($services as $service)
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 p-5">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Dental treatment</p>
                                <h3 class="mt-1 break-words text-lg font-semibold text-slate-900">{{ $service->name }}</h3>
                            </div>
                            <span class="shrink-0 rounded-xl bg-teal-50 px-3 py-2 text-sm font-bold text-teal-900">₱{{ number_format((float) $service->price, 2) }}</span>
                        </div>
                        <div class="grid gap-4 p-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm leading-6 text-slate-600">{{ $service->description ?: 'No description provided.' }}</p>
                                <span @class([
                                    'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-50 text-emerald-800' => $service->is_active,
                                    'bg-slate-100 text-slate-600' => ! $service->is_active,
                                ])>{{ $service->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>

                            @if (auth()->user()->role === 'admin')
                                <details class="border-t border-slate-100 pt-4">
                                    <summary class="cursor-pointer text-sm font-semibold text-teal-800 hover:text-teal-950">Edit service details</summary>
                                    <form method="POST" action="{{ route('admin.services.update', $service) }}" class="mt-4 grid gap-3">
                                        @csrf @method('PUT')
                                        <label class="form-label">Service name
                                            <input class="form-input" name="name" value="{{ $service->name }}" required maxlength="255">
                                        </label>
                                        <label class="form-label">Description
                                            <textarea class="form-input" name="description" rows="2" maxlength="2000">{{ $service->description }}</textarea>
                                        </label>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <label class="form-label">Price
                                                <span class="relative">
                                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-slate-500">₱</span>
                                                    <input class="form-input pl-8" type="number" name="price" min="0" max="1000000" step="0.01" value="{{ $service->price }}" required>
                                                </span>
                                            </label>
                                            <label class="form-label">Availability
                                                <select class="form-input" name="is_active" required>
                                                    <option value="1" @selected($service->is_active)>Active</option>
                                                    <option value="0" @selected(! $service->is_active)>Inactive</option>
                                                </select>
                                            </label>
                                        </div>
                                        <button class="btn-secondary w-full sm:w-auto">Save changes</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-5">{{ $services->links() }}</div>
        @endif
    </section>
@endsection
