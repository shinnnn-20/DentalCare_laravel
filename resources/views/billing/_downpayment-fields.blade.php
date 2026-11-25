<section class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
    <h2 class="font-semibold text-slate-900">Downpayment</h2>
    <p class="text-sm text-slate-600">Optional. Enter only a payment already received and verified; it will be applied to the bill balance.</p>
    <label class="form-label">Downpayment amount (₱)
        <input class="form-input max-w-xs" type="text" inputmode="decimal" name="downpayment_amount" data-money-input pattern="(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?" title="Enter an amount with up to two decimal places." value="{{ old('downpayment_amount') }}">
    </label>
    <label class="form-label">Downpayment method
        <select class="form-input max-w-xs" name="downpayment_method">
            <option value="">Select a method</option>
            <option value="cash" @selected(old('downpayment_method') === 'cash')>Cash</option>
            <option value="gcash" @selected(old('downpayment_method') === 'gcash')>GCash</option>
            <option value="other" @selected(old('downpayment_method') === 'other')>Other</option>
        </select>
    </label>
</section>
