@extends('layouts.app')

@section('title', 'Register patient')

@section('content')
    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.index') }}">← Patient directory</a>
        <h1 class="mt-5 text-2xl font-bold">Register a patient</h1>
        <p class="mt-2 text-sm text-slate-500">This creates a patient account and patient number. Staff cannot create staff or administrator accounts.</p>
        <form method="POST" action="{{ route('clinic.patients.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2" data-confirm="Create a new patient account with these details?" data-confirm-title="Confirm patient registration" data-confirm-accept="Create account">
            @csrf
            <label class="form-label sm:col-span-2">Full name<input class="form-input" name="name" value="{{ old('name') }}" maxlength="255" pattern="[\p{L}\p{M} .'\-]+" title="Use letters, spaces, apostrophes, periods, or hyphens." required></label>
            @if ($isAdmin)
                <label class="form-label sm:col-span-2">Branch
                    <select class="form-input" name="branch_id" required>
                        <option value="">Select a branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif ($isDoctor)
                <label class="form-label sm:col-span-2">Branch
                    <select class="form-input" disabled>
                        <option>{{ $assignedBranch->name }}</option>
                    </select>
                    <input type="hidden" name="branch_id" value="{{ $assignedBranch->id }}">
                </label>
            @endif
            @include('patients._registration-fields')
            <button class="btn-primary sm:col-span-2 sm:justify-self-start">Create patient account</button>
        </form>
    </section>
@endsection
