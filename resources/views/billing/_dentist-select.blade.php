<label class="form-label">Dentist Who Performed the Treatment
    <select class="form-input" name="service_dentists[{{ $service->id }}]" required>
        <option value="">Select a dentist</option>
        @foreach ($treatingDentists as $dentist)
            <option value="{{ $dentist->id }}" @selected((string) old('service_dentists.'.$service->id, $appointment->dentalRecord?->treating_dentist_id) === (string) $dentist->id)>{{ $dentist->name }}</option>
        @endforeach
    </select>
</label>
