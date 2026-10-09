@extends('layouts.app')

@section('title', 'RFID management')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Patient identification</p><h1 class="mt-1 text-3xl font-bold tracking-tight">RFID cards</h1><p class="mt-2 text-slate-500">Assign one unique RFID UID to each patient.</p></div>
        <a class="btn-primary" href="{{ route('clinic.rfid.scan') }}">Open check-in station</a>
    </div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Patient</th><th>Number</th><th>Current UID</th><th>Assign / replace card</th></tr></thead><tbody>
            @forelse ($patients as $patient)
                <tr><td data-label="Patient" class="font-semibold">{{ $patient->user->name }}</td><td data-label="Number">{{ $patient->patient_number }}</td><td data-label="Current UID">{{ $patient->rfidCard?->uid ?? 'Not assigned' }}</td>
                    <td data-label="Assign / replace card"><form method="POST" action="{{ route('clinic.rfid.assign', $patient) }}" class="flex min-w-0 flex-wrap gap-2">@csrf<input class="form-input min-w-0 flex-1" name="uid" maxlength="100" placeholder="Scan or enter UID" required><button class="btn-secondary">Save</button></form></td>
                </tr>
            @empty<tr><td colspan="4" class="py-10 text-center text-slate-500">No patient accounts yet.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $patients->links() }}</div>
    </section>
    <p class="mt-4 text-sm text-slate-500">RFID identifies the patient for staff; it does not grant access to a patient account. Check-in is completed from the Patient Queue tab.</p>
@endsection
