document.addEventListener('DOMContentLoaded', () => {
    const moneyInputs = Array.from(document.querySelectorAll('[data-money-input]'));

    const formatMoneyInput = (input) => {
        const currentValue = input.value;
        const selectionStart = input.selectionStart ?? currentValue.length;
        const selectionEnd = input.selectionEnd ?? selectionStart;
        const countMeaningfulCharacters = (value) => value.replace(/[^\d.]/g, '').length;
        const meaningfulCharactersBeforeStart = countMeaningfulCharacters(currentValue.slice(0, selectionStart));
        const meaningfulCharactersBeforeEnd = countMeaningfulCharacters(currentValue.slice(0, selectionEnd));
        let normalizedValue = currentValue.replace(/,/g, '').replace(/[^\d.]/g, '');
        const decimalIndex = normalizedValue.indexOf('.');

        if (decimalIndex !== -1) {
            normalizedValue = `${normalizedValue.slice(0, decimalIndex + 1)}${normalizedValue.slice(decimalIndex + 1).replace(/\./g, '').slice(0, 2)}`;
        }

        const [integerPart, decimalPart] = normalizedValue.split('.');
        const groupedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        const formattedValue = decimalPart === undefined
            ? groupedInteger
            : `${groupedInteger}.${decimalPart}`;
        const positionForMeaningfulCharacterCount = (characterCount) => {
            if (characterCount === 0) {
                return 0;
            }

            let meaningfulCharacters = 0;
            for (let index = 0; index < formattedValue.length; index += 1) {
                if (formattedValue[index] !== ',') {
                    meaningfulCharacters += 1;
                }
                if (meaningfulCharacters === characterCount) {
                    return index + 1;
                }
            }

            return formattedValue.length;
        };

        input.value = formattedValue;
        input.setSelectionRange(
            positionForMeaningfulCharacterCount(meaningfulCharactersBeforeStart),
            positionForMeaningfulCharacterCount(meaningfulCharactersBeforeEnd),
        );
    };

    moneyInputs.forEach((input) => {
        formatMoneyInput(input);
        input.addEventListener('input', () => formatMoneyInput(input));
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-money-input]').forEach((input) => {
                input.value = input.value.replace(/,/g, '');
            });
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = button.closest('.password-input-wrap')?.querySelector('input');

        if (!input) {
            return;
        }

        const setVisibility = (isVisible) => {
            button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', String(isVisible));
            button.innerHTML = isVisible ? `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 3l18 18"/>
                    <path d="M10.5 10.5A2.5 2.5 0 0 0 13.5 13.5"/>
                    <path d="M9.88 5.08A10.7 10.7 0 0 1 12 5c4.97 0 9 3.5 9 7a12.3 12.3 0 0 1-3.12 4.57"/>
                    <path d="M6.61 6.61A12.2 12.2 0 0 0 3 12c0 3.5 4.03 7 9 7a11.9 11.9 0 0 0 5.37-1.3"/>
                </svg>
            ` : `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            `;
        };

        setVisibility(input.type === 'text');

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const selectionStart = input.selectionStart;
            const selectionEnd = input.selectionEnd;
            const shouldShow = input.type === 'password';

            input.type = shouldShow ? 'text' : 'password';
            setVisibility(shouldShow);

            requestAnimationFrame(() => {
                input.focus();
                if (typeof selectionStart === 'number' && typeof selectionEnd === 'number') {
                    input.setSelectionRange(selectionStart, selectionEnd);
                }
            });
        });
    });
});
