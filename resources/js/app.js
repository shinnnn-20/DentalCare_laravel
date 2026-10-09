document.addEventListener('DOMContentLoaded', () => {
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
