<label class="form-label">Date of birth<input class="form-input" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required></label>
<label class="form-label">Gender
    <select class="form-input" name="gender" required>
        <option value="">Select</option>
        @foreach (['female', 'male', 'other', 'prefer_not_to_say'] as $gender)
            <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ str_replace('_', ' ', ucfirst($gender)) }}</option>
        @endforeach
    </select>
</label>
<label class="form-label">Contact number<input class="form-input" name="phone" value="{{ old('phone') }}" maxlength="11" pattern="[0-9]{1,11}" inputmode="numeric" title="Enter up to 11 digits." required></label>
<label class="form-label">Email<input class="form-input" type="email" name="email" value="{{ old('email') }}" maxlength="255" required></label>
<label class="form-label sm:col-span-2">Address<textarea class="form-input" name="address" rows="2" required>{{ old('address') }}</textarea></label>
<label class="form-label sm:col-span-2">Emergency contact<input class="form-input" name="emergency_contact" value="{{ old('emergency_contact') }}" maxlength="100"></label>
<label class="form-label self-start">Patient-set password
    <div class="password-input-wrap">
        <input class="form-input h-11" type="password" name="password" minlength="8" required>
        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        </button>
    </div>
    <span class="text-xs font-normal text-slate-500">At least 8 characters, one capital letter, and one special character.</span>
</label>
<label class="form-label self-start">Confirm password
    <div class="password-input-wrap">
        <input class="form-input h-11" type="password" name="password_confirmation" minlength="8" required>
        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        </button>
    </div>
</label>
