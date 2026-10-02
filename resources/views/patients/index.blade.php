@extends('layouts.app')

@section('title', 'Patients')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Clinic directory</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Patients</h1><p class="mt-2 text-slate-500">Search patient profiles, appointments, and clinical history.</p></div>
        <div class="flex flex-wrap gap-2">
        <a class="btn-primary" href="{{ route('clinic.patients.create') }}">Register patient</a>
        <form method="GET" class="flex gap-2">
            <input class="form-input min-w-56" name="search" value="{{ request('search') }}" placeholder="Name, number, email or phone">
            <button class="btn-secondary">Search</button>
        </form>
        </div>
    </div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Patient</th><th>Patient number</th><th>Contact</th><th>RFID</th><th>Registered</th><th></th></tr></thead>
                <tbody>
                @forelse ($patients as $patient)
                    <tr>
                        <td data-label="Patient" class="font-semibold">{{ $patient->user->name }}<div class="text-xs font-normal text-slate-400">{{ $patient->user->email }}</div></td>
                        <td data-label="Patient number">{{ $patient->patient_number }}</td><td data-label="Contact">{{ $patient->user->phone ?? '—' }}</td>
                        <td data-label="RFID">{{ $patient->rfidCard?->uid ?? 'Not assigned' }}</td><td data-label="Registered">{{ $patient->created_at->format('M j, Y') }}</td>
                        <td data-label="Profile"><a class="font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $patient) }}">View profile →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">No patients found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $patients->links() }}</div>
    </section>
@endsection
