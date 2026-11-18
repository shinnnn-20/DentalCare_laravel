document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('#app-message-modal');
    if (modal) {
        const modalTitle = modal.querySelector('[data-modal-title]');
        const modalMessage = modal.querySelector('[data-modal-message]');
        const modalIcon = modal.querySelector('[data-modal-icon]');
        const modalCancel = modal.querySelector('[data-modal-cancel]');
        const modalConfirm = modal.querySelector('[data-modal-confirm]');
        let resolveModal;
        let modalQueue = Promise.resolve();

        modal.addEventListener('close', () => {
            if (resolveModal) {
                resolveModal(modal.returnValue === 'confirm');
                resolveModal = null;
            }
        });

        modalConfirm.addEventListener('click', () => modal.close('confirm'));
        modalCancel.addEventListener('click', () => modal.close('cancel'));

        const showModal = ({
            title = 'Notice',
            message = '',
            variant = 'info',
            confirmation = false,
            confirmLabel = 'OK',
            cancelLabel = 'Cancel',
            content = null,
        }) => {
            const result = modalQueue.then(() => new Promise((resolve) => {
                modal.dataset.variant = variant;
                modal.dataset.confirmation = confirmation ? 'true' : 'false';
                modalTitle.textContent = title;
                modalIcon.textContent = variant === 'success' ? '+' : variant === 'error' ? '!' : confirmation ? '?' : 'i';
                modalMessage.replaceChildren();
                if (content) {
                    modalMessage.append(content.cloneNode(true));
                } else {
                    modalMessage.textContent = message;
                }
                modalCancel.hidden = !confirmation;
                modalCancel.textContent = cancelLabel;
                modalConfirm.textContent = confirmLabel;
                modal.setAttribute('role', confirmation || variant === 'error' ? 'alertdialog' : 'dialog');
                resolveModal = resolve;
                modal.showModal();
                modalConfirm.focus();
            }));

            modalQueue = result.then(() => undefined, () => undefined);

            return result;
        };

        window.showAppAlert = (message, options = {}) => showModal({
            ...options,
            message,
        });
        window.showAppConfirm = (message, options = {}) => showModal({
            ...options,
            message,
            confirmation: true,
            confirmLabel: options.confirmLabel ?? 'Confirm',
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
                return;
            }

            if (form.dataset.confirmSkip === 'true') {
                delete form.dataset.confirmSkip;
                return;
            }

            event.preventDefault();
            if (form.dataset.confirmPending === 'true') {
                return;
            }

            form.dataset.confirmPending = 'true';
            window.showAppConfirm(form.dataset.confirm, {
                title: form.dataset.confirmTitle ?? 'Please confirm',
                confirmLabel: form.dataset.confirmAccept ?? 'Confirm',
                variant: form.dataset.confirmVariant ?? 'warning',
            }).then((confirmed) => {
                delete form.dataset.confirmPending;

                if (confirmed && form.isConnected) {
                    form.dataset.confirmSkip = 'true';
                    if (event.submitter) {
                        form.requestSubmit(event.submitter);
                    } else {
                        form.requestSubmit();
                    }
                }
            });
        });

        const initialModal = document.querySelector('template[data-app-initial-modal]');
        if (initialModal) {
            showModal({
                title: initialModal.dataset.modalTitle,
                variant: initialModal.dataset.modalVariant,
                content: initialModal.content,
            });
        }
    }

    const serviceDialog = document.querySelector('[data-service-dialog]');
    if (serviceDialog) {
        const serviceDialogTitle = serviceDialog.querySelector('[data-service-dialog-title]');
        const serviceDialogDescription = serviceDialog.querySelector('[data-service-dialog-description]');

        document.querySelectorAll('[data-service-dialog-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                serviceDialogTitle.textContent = trigger.dataset.serviceName;
                serviceDialogDescription.textContent = trigger.dataset.serviceDescription;
                serviceDialog.showModal();
            });
        });

        serviceDialog.querySelector('[data-service-dialog-close]').addEventListener('click', () => {
            serviceDialog.close();
        });

        serviceDialog.addEventListener('click', (event) => {
            if (event.target === serviceDialog) {
                serviceDialog.close();
            }
        });
    }

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

    document.querySelectorAll('[data-bill-discount]').forEach((form) => {
        const discountType = form.querySelector('[data-discount-type]');
        const percentageInput = form.querySelector('[data-discount-percentage]');
        const discountCardField = form.querySelector('[data-discount-card-field]');
        const discountCardInput = form.querySelector('[data-discount-card-number]');
        const discountAmount = form.querySelector('[data-discount-amount]');
        const discountedTotal = form.querySelector('[data-discounted-total]');
        const calculationStatus = form.querySelector('[data-bill-calculation-status]');
        let preserveSeniorCardIdFormat = discountType?.value === 'senior'
            && Boolean(discountCardInput?.value.includes('-'));
        const formatCurrency = (cents) => new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(cents / 100);

        const formatCardNumber = () => {
            if (!discountCardInput || !discountType
                || (discountType.value === 'senior' && preserveSeniorCardIdFormat)) {
                return;
            }

            const originalValue = discountCardInput.value;
            const digits = originalValue.replace(/\D/g, '');
            const digitGroups = [];
            let digitOffset = 0;

            for (const groupLength of [2, 4, 3]) {
                if (digitOffset >= digits.length) {
                    break;
                }

                digitGroups.push(digits.slice(digitOffset, digitOffset + groupLength));
                digitOffset += groupLength;
            }

            if (digitOffset < digits.length) {
                digitGroups.push(digits.slice(digitOffset));
            }

            const formattedValue = digitGroups.join('-');
            if (discountType.value === 'senior'
                && originalValue.includes('-')
                && formattedValue !== originalValue) {
                preserveSeniorCardIdFormat = true;
                return;
            }

            if (formattedValue === originalValue) {
                return;
            }

            const countDigits = (value) => (value.match(/\d/g) ?? []).length;
            const positionAfterDigits = (value, digitCount) => {
                if (digitCount === 0) {
                    return 0;
                }

                let digitsSeen = 0;
                for (let index = 0; index < value.length; index += 1) {
                    if (/\d/.test(value[index])) {
                        digitsSeen += 1;
                        if (digitsSeen === digitCount) {
                            return index + 1;
                        }
                    }
                }

                return value.length;
            };
            const selectionStart = discountCardInput.selectionStart ?? originalValue.length;
            const selectionEnd = discountCardInput.selectionEnd ?? selectionStart;
            const startDigitCount = countDigits(originalValue.slice(0, selectionStart));
            const endDigitCount = countDigits(originalValue.slice(0, selectionEnd));

            discountCardInput.value = formattedValue;
            discountCardInput.setSelectionRange(
                positionAfterDigits(formattedValue, startDigitCount),
                positionAfterDigits(formattedValue, endDigitCount),
            );
        };

        const syncDiscountPreset = () => {
            if (!discountType || !percentageInput) {
                return;
            }

            const requiresDiscountCard = discountType.value === 'senior' || discountType.value === 'pwd';
            discountCardField?.classList.toggle('hidden', !requiresDiscountCard);
            if (discountCardInput) {
                discountCardInput.required = requiresDiscountCard;
                discountCardInput.disabled = !requiresDiscountCard;
                discountCardInput.placeholder = discountType.value === 'pwd'
                    ? 'RR-PPMM-BBB-NNNNNNN'
                    : 'SS-PPMM-BBB-NNNNNN';
                if (!requiresDiscountCard) {
                    discountCardInput.value = '';
                }
            }

            if (discountType.value === 'senior' || discountType.value === 'pwd') {
                percentageInput.value = '20';
                percentageInput.disabled = true;
                return;
            }

            if (discountType.value === 'none') {
                percentageInput.value = '0';
                percentageInput.disabled = true;
                return;
            }

            percentageInput.disabled = false;
            if (!percentageInput.value.trim()) {
                percentageInput.value = '0';
            }
        };

        discountType?.addEventListener('change', () => {
            preserveSeniorCardIdFormat = false;
            syncDiscountPreset();
            formatCardNumber();
            updateBillTotals();
        });

        discountCardInput?.addEventListener('input', (event) => {
            if (discountType?.value === 'senior'
                && ((event.inputType === 'insertFromPaste' && discountCardInput.value.includes('-'))
                    || event.data === '-')) {
                preserveSeniorCardIdFormat = true;
            }

            formatCardNumber();
        });

        const updateBillTotals = () => {
            syncDiscountPreset();
            const percentage = Number.parseFloat(percentageInput.value);
            if (!Number.isFinite(percentage) || percentage < 0 || percentage > 100) {
                discountAmount.textContent = 'Enter 0–100%';
                discountedTotal.textContent = '—';
                calculationStatus.textContent = '';
                return;
            }

            let subtotalCents = Number.parseInt(form.dataset.billBaseCents ?? '0', 10);
            for (const priceInput of form.querySelectorAll('[data-bill-final-price]')) {
                const normalizedPrice = priceInput.value.replace(/,/g, '').trim();
                if (!/^\d+(?:\.\d{1,2})?$/.test(normalizedPrice)) {
                    discountAmount.textContent = '—';
                    discountedTotal.textContent = '—';
                    calculationStatus.textContent = 'Enter and confirm all final treatment amounts to calculate the bill.';
                    return;
                }

                const [whole, fraction = ''] = normalizedPrice.split('.');
                subtotalCents += Number.parseInt(whole, 10) * 100
                    + Number.parseInt(`${fraction}00`.slice(0, 2), 10);
            }

            const requiresDiscountCard = discountType.value === 'senior' || discountType.value === 'pwd';
            if (requiresDiscountCard && !discountCardInput?.value.trim()) {
                discountAmount.textContent = `₱${formatCurrency(0)}`;
                discountedTotal.textContent = `₱${formatCurrency(subtotalCents)}`;
                calculationStatus.textContent = 'Enter the Senior/PWD card ID to apply this discount.';
                return;
            }

            const discountBasisPoints = Math.round(percentage * 100);
            const discountCents = Math.floor((subtotalCents * discountBasisPoints + 5000) / 10000);
            discountAmount.textContent = `₱${formatCurrency(discountCents)}`;
            discountedTotal.textContent = `₱${formatCurrency(subtotalCents - discountCents)}`;
            calculationStatus.textContent = '';
        };

        form.addEventListener('input', updateBillTotals);
        form.addEventListener('change', updateBillTotals);
        formatCardNumber();
        updateBillTotals();
    });

    document.querySelectorAll('[data-addon-form]').forEach((form) => {
        const summaryItems = form.querySelector('[data-addon-summary-items]');
        const emptyMessage = form.querySelector('[data-addon-empty]');
        const total = form.querySelector('[data-addon-total]');
        const formatCurrency = (cents) => new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(cents / 100);

        const updateSummary = () => {
            const selectedServices = Array.from(form.querySelectorAll('[data-addon-toggle]'));
            const selected = selectedServices.filter((service) => service.checked);
            let totalCents = 0;
            let hasUnconfirmedPrice = false;

            summaryItems.replaceChildren();
            emptyMessage.hidden = selected.length > 0;
            summaryItems.classList.toggle('hidden', selected.length === 0);

            selectedServices.forEach((service) => {
                const pricePanel = form.querySelector(`[data-addon-price-panel="${service.dataset.serviceId}"]`);
                const priceInput = form.querySelector(`[data-addon-final-price="${service.dataset.serviceId}"]`);
                const priceConfirmation = form.querySelector(`[data-addon-price-confirmation="${service.dataset.serviceId}"]`);
                const dentistInput = form.querySelector(`[data-addon-dentist="${service.dataset.serviceId}"]`);
                service.closest('[data-addon-card]')?.classList.toggle(
                    'border-teal-500',
                    service.checked,
                );
                service.closest('[data-addon-card]')?.classList.toggle(
                    'bg-teal-50/60',
                    service.checked,
                );
                pricePanel?.classList.toggle('hidden', !service.checked);
                if (priceInput) {
                    priceInput.required = service.checked;
                    priceInput.disabled = !service.checked;
                }
                if (priceConfirmation) {
                    priceConfirmation.required = service.checked;
                    priceConfirmation.disabled = !service.checked;
                }
                if (dentistInput) {
                    dentistInput.required = service.checked;
                    dentistInput.disabled = !service.checked;
                }
            });

            form.querySelectorAll('[data-addon-category]').forEach((category) => {
                const selectedCount = category.querySelectorAll('[data-addon-toggle]:checked').length;
                const selectedCountBadge = category.querySelector('[data-addon-category-selected-count]');
                selectedCountBadge.textContent = `${selectedCount} selected`;
                selectedCountBadge.classList.toggle('hidden', selectedCount === 0);
            });

            selected.forEach((service) => {
                let priceCents = Number.parseInt(service.dataset.priceCents ?? '0', 10);
                let priceLabel = `₱${formatCurrency(priceCents)}`;
                let canAddToTotal = true;

                if (service.dataset.requiresPriceConfirmation === 'true') {
                    const priceInput = form.querySelector(`[data-addon-final-price="${service.dataset.serviceId}"]`);
                    const priceConfirmation = form.querySelector(`[data-addon-price-confirmation="${service.dataset.serviceId}"]`);
                    const normalizedPrice = priceInput?.value.replace(/,/g, '').trim() ?? '';

                    if (!/^\d+(?:\.\d{1,2})?$/.test(normalizedPrice)) {
                        hasUnconfirmedPrice = true;
                        canAddToTotal = false;
                        priceLabel = 'Enter final price';
                    } else {
                        const [whole, fraction = ''] = normalizedPrice.split('.');
                        priceCents = Number.parseInt(whole, 10) * 100
                            + Number.parseInt(`${fraction}00`.slice(0, 2), 10);
                        priceLabel = `₱${formatCurrency(priceCents)}`;
                        if (!priceConfirmation?.checked) {
                            hasUnconfirmedPrice = true;
                            canAddToTotal = false;
                        }
                    }
                }

                if (canAddToTotal) {
                    totalCents += priceCents;
                }
                const item = document.createElement('li');
                item.className = 'flex flex-wrap justify-between gap-2 py-2 text-sm';
                const name = document.createElement('span');
                name.className = 'min-w-0 break-words text-slate-800';
                name.textContent = `${service.dataset.serviceName} × 1`;
                const price = document.createElement('span');
                price.className = 'shrink-0 font-semibold text-slate-900';
                price.textContent = priceLabel;
                item.append(name, price);
                summaryItems.append(item);
            });

            total.textContent = hasUnconfirmedPrice
                ? 'Enter and confirm prices in the selected services above'
                : `₱${formatCurrency(totalCents)}`;
        };

        form.addEventListener('change', updateSummary);
        form.addEventListener('input', (event) => {
            if (event.target.matches('[data-addon-final-price]')) {
                updateSummary();
            }
        });
        updateSummary();
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-money-input]').forEach((input) => {
                input.value = input.value.replace(/,/g, '');
            });
        });
    });

    document.querySelectorAll('[data-follow-up-time-picker]').forEach((picker) => {
        const trigger = picker.querySelector('[data-follow-up-time-trigger]');
        const triggerLabel = picker.querySelector('[data-follow-up-time-label]');
        const timeInput = picker.querySelector('[data-follow-up-time-value]');
        const dateInput = picker.closest('form').querySelector('[name="follow_up_date"]');
        const status = picker.querySelector('[data-follow-up-time-status]');
        const timeMenu = document.createElement('div');
        const timeOptionsId = `follow-up-time-options-${Math.random().toString(36).slice(2)}`;
        const branchId = picker.dataset.branchId;
        const appointmentId = picker.dataset.excludeAppointmentId;
        let selectedTime = timeInput.value;
        let availableTimes = [];
        let availabilityLoaded = false;
        let availabilityRequest = 0;
        let lastDate = dateInput.value;

        timeMenu.id = timeOptionsId;
        timeMenu.className = 'time-select-menu hidden';
        timeMenu.setAttribute('role', 'listbox');
        timeMenu.setAttribute('aria-label', 'Available follow-up times');
        trigger.setAttribute('aria-controls', timeOptionsId);
        document.body.append(timeMenu);

        const formatTime = (time) => new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(`2000-01-01T${time}:00`));

        const updatePicker = () => {
            triggerLabel.textContent = selectedTime ? formatTime(selectedTime) : 'Select time';
            timeInput.value = selectedTime;
            dateInput.required = selectedTime !== '';
            dateInput.setCustomValidity(selectedTime && !dateInput.value
                ? 'Choose a follow-up date when setting a time.'
                : '');
            trigger.disabled = !dateInput.value || !availabilityLoaded || availableTimes.length === 0;
            trigger.setAttribute('aria-disabled', String(trigger.disabled));
        };

        const closeTimeMenu = (returnFocus = false) => {
            timeMenu.classList.add('hidden');
            timeMenu.classList.remove('time-select-menu-fixed');
            timeMenu.style.removeProperty('left');
            timeMenu.style.removeProperty('right');
            timeMenu.style.removeProperty('width');
            timeMenu.style.removeProperty('top');
            timeMenu.style.removeProperty('bottom');
            trigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) {
                trigger.focus();
            }
        };

        const renderTimeOptions = () => {
            const items = availableTimes.map((time) => {
                const item = document.createElement('div');
                item.className = 'time-select-option';
                item.textContent = formatTime(time);
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', String(time === selectedTime));
                item.tabIndex = -1;
                item.addEventListener('click', () => {
                    selectedTime = time;
                    updatePicker();
                    renderTimeOptions();
                    closeTimeMenu(true);
                });
                item.addEventListener('keydown', (event) => {
                    const options = Array.from(timeMenu.querySelectorAll('[role="option"]'));
                    const index = options.indexOf(item);
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        options[(index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length]?.focus();
                    } else if (event.key === 'Home' || event.key === 'End') {
                        event.preventDefault();
                        options[event.key === 'Home' ? 0 : options.length - 1]?.focus();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        closeTimeMenu(true);
                    } else if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        item.click();
                    }
                });

                return item;
            });

            timeMenu.replaceChildren(...items);
        };

        const setStatus = (message) => {
            status.textContent = message;
            status.hidden = message === '';
        };

        const loadAvailability = async () => {
            const requestedDate = dateInput.value;
            const requestId = ++availabilityRequest;
            availableTimes = [];
            availabilityLoaded = false;
            closeTimeMenu();
            renderTimeOptions();
            updatePicker();

            if (!requestedDate) {
                setStatus('Choose a follow-up date to see available times.');
                return;
            }

            if (!branchId) {
                selectedTime = '';
                updatePicker();
                setStatus('This appointment has no assigned branch, so times cannot be loaded.');
                return;
            }

            setStatus('Loading times...');

            const url = new URL(picker.dataset.availabilityUrl, window.location.origin);
            url.searchParams.set('month', requestedDate.slice(0, 7));
            url.searchParams.set('branch_id', branchId);
            if (appointmentId) {
                url.searchParams.set('exclude_appointment_id', appointmentId);
            }

            try {
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    throw new Error(`Availability request failed with status ${response.status}.`);
                }

                const result = await response.json();
                if (requestId !== availabilityRequest) {
                    return;
                }

                availableTimes = Array.isArray(result.days?.[requestedDate])
                    ? result.days[requestedDate]
                    : [];
                availabilityLoaded = true;
                if (selectedTime && !availableTimes.includes(selectedTime)) {
                    selectedTime = '';
                    setStatus('The selected time is no longer available. Choose another time.');
                } else {
                    setStatus(availableTimes.length === 0 ? 'No times are available on this date.' : '');
                }
                renderTimeOptions();
                updatePicker();
            } catch (error) {
                if (requestId !== availabilityRequest) {
                    return;
                }

                console.error(error);
                selectedTime = '';
                availableTimes = [];
                availabilityLoaded = false;
                renderTimeOptions();
                updatePicker();
                setStatus('Unable to load times. Change the date and try again.');
            }
        };

        trigger.addEventListener('click', () => {
            if (trigger.disabled) {
                return;
            }

            const isOpening = timeMenu.classList.contains('hidden');
            timeMenu.classList.toggle('hidden', !isOpening);
            trigger.setAttribute('aria-expanded', String(isOpening));
            if (isOpening) {
                const triggerBounds = trigger.getBoundingClientRect();
                const viewportPadding = 8;
                const menuHeight = Math.min(timeMenu.scrollHeight, window.innerHeight * 0.4, 192);
                const spaceBelow = window.innerHeight - triggerBounds.bottom;
                const opensAbove = spaceBelow < menuHeight + viewportPadding && triggerBounds.top > spaceBelow;
                const menuWidth = Math.min(triggerBounds.width, window.innerWidth - (viewportPadding * 2));
                const menuLeft = Math.max(
                    viewportPadding,
                    Math.min(triggerBounds.left, window.innerWidth - menuWidth - viewportPadding),
                );
                timeMenu.classList.add('time-select-menu-fixed');
                timeMenu.style.left = `${menuLeft}px`;
                timeMenu.style.right = 'auto';
                timeMenu.style.width = `${menuWidth}px`;
                timeMenu.style.top = opensAbove ? 'auto' : `${triggerBounds.bottom + 4}px`;
                timeMenu.style.bottom = opensAbove
                    ? `${window.innerHeight - triggerBounds.top + 4}px`
                    : 'auto';
                (timeMenu.querySelector('[aria-selected="true"]')
                    || timeMenu.querySelector('[role="option"]'))?.focus();
            }
        });
        trigger.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'Enter', ' '].includes(event.key) && !trigger.disabled) {
                event.preventDefault();
                trigger.click();
            }
        });
        document.addEventListener('click', (event) => {
            if (!picker.contains(event.target) && !timeMenu.contains(event.target)) {
                closeTimeMenu();
            }
        });
        window.addEventListener('scroll', (event) => {
            if (!timeMenu.contains(event.target)) {
                closeTimeMenu();
            }
        }, true);
        window.addEventListener('resize', () => closeTimeMenu());
        dateInput.addEventListener('change', () => {
            const preserveOldTime = lastDate === '' && selectedTime !== '';
            if (!preserveOldTime) {
                selectedTime = '';
            }
            lastDate = dateInput.value;
            loadAvailability();
        });

        updatePicker();
        if (dateInput.value) {
            loadAvailability();
        } else {
            setStatus('Choose a follow-up date to see available times.');
        }
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
