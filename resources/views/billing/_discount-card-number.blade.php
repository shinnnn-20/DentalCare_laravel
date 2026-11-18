@php($discountType = old('discount_type'))
@php($discountCardNumber = old('discount_card_number'))
@php($requiresDiscountCardNumber = is_string($discountType) && in_array($discountType, ['senior', 'pwd'], true))
@php($discountCardPlaceholder = $discountType === 'pwd' ? 'RR-PPMM-BBB-NNNNNNN' : 'SS-PPMM-BBB-NNNNNN')
<div @class(['hidden' => ! $requiresDiscountCardNumber]) data-discount-card-field>
    <label class="form-label">Senior/PWD Card ID
        <input
            class="form-input max-w-xs"
            type="text"
            name="discount_card_number"
            value="{{ is_string($discountCardNumber) ? $discountCardNumber : '' }}"
            placeholder="{{ $discountCardPlaceholder }}"
            maxlength="100"
            inputmode="numeric"
            autocomplete="off"
            spellcheck="false"
            data-discount-card-number
            @required($requiresDiscountCardNumber)
            @disabled(! $requiresDiscountCardNumber)
        >
    </label>
    <p class="mt-1 text-xs text-slate-500">Required before the 20% discount applies. PWD example: RR-PPMM-BBB-NNNNNNN. Senior example: SS-PPMM-BBB-NNNNNN; formats may vary by LGU. The number is encrypted and saved with this bill.</p>
</div>
