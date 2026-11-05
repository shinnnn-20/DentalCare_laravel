@extends('layouts.app')

@section('title', 'Register patient')

@section('content')
    <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
        <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.index') }}">← Patient directory</a>
        <h1 class="mt-5 text-2xl font-bold">Register a patient</h1>
        <p class="mt-2 text-sm text-slate-500">This creates a patient account and patient number. Staff cannot create staff or administrator accounts.</p>
        <form method="POST" action="{{ route('clinic.patients.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2" data-confirm="Create a new patient account with these details?" data-confirm-title="Confirm patient registration" data-confirm-accept="Create account">
            @csrf
            <label class="form-label sm:col-span-2">Full name<input class="form-input" name="name" value="{{ old('name') }}" maxlength="255" pattern="[\p{L}\p{M} .'-]+" title="Use letters, spaces, apostrophes, periods, or hyphens." required></label>
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
            <label class="form-label">Date of birth<input class="form-input" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required></label>
            <label class="form-label">Gender<select class="form-input" name="gender" required><option value="">Select</option>@foreach (['female', 'male', 'other', 'prefer_not_to_say'] as $gender)<option value="{{ $gender }}">{{ str_replace('_', ' ', ucfirst($gender)) }}</option>@endforeach</select></label>
            <label class="form-label">Contact number<input class="form-input" name="phone" value="{{ old('phone') }}" maxlength="11" pattern="[0-9]{1,11}" inputmode="numeric" title="Enter up to 11 digits." required></label>
            <label class="form-label">Email<input class="form-input" type="email" name="email" value="{{ old('email') }}" maxlength="255" required></label>
            <label class="form-label sm:col-span-2">Address<textarea class="form-input" name="address" rows="2" required>{{ old('address') }}</textarea></label>
            <label class="form-label sm:col-span-2">Emergency contact<input class="form-input" name="emergency_contact"></label>
            <label class="form-label self-start">Patient-set password<input class="form-input h-11" type="password" name="password" minlength="8" required><span class="text-xs font-normal text-slate-500">At least 8 characters, one capital letter, and one special character.</span></label>
            <label class="form-label self-start">Confirm password<input class="form-input h-11" type="password" name="password_confirmation" required></label>
            <button class="btn-primary sm:col-span-2 sm:justify-self-start">Create patient account</button>
        </form>
    </section>
@endsection
