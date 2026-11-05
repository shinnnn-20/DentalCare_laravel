@extends('layouts.app')

@section('title', 'RFID management')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Patient identification</p><h1 class="mt-1 text-3xl font-bold tracking-tight">RFID cards</h1><p class="mt-2 text-slate-500">{{ $isReadOnly ? 'View RFID card details for patients at your assigned branch.' : 'Assign a unique RFID UID and validity period to each patient.' }}</p></div>
        <a class="btn-primary" href="{{ route('clinic.rfid.scan') }}">Open check-in station</a>
    </div>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Patient</th><th>Number</th><th>Current UID</th><th>Valid from</th><th>Valid until</th><th>Card status</th>@unless ($isReadOnly)<th>Assign / update card</th>@endunless</tr></thead><tbody>
            @forelse ($patients as $patient)
                @php($card = $patient->rfidCard)
                @php($cardStatus = $card?->validityStatus())
                <tr>
                    <td data-label="Patient" class="font-semibold">{{ $patient->user->name }}</td>
                    <td data-label="Number">{{ $patient->patient_number }}</td>
                    <td data-label="Current UID">{{ $card?->uid ?? 'Not assigned' }}</td>
                    <td data-label="Valid from">{{ $card?->valid_from?->format('M j, Y') ?? 'No start limit' }}</td>
                    <td data-label="Valid until">{{ $card?->valid_until?->format('M j, Y g:i A') ?? 'No expiry' }}</td>
                    <td data-label="Card status">
                        <span @class([
                            'status-badge capitalize',
                            'bg-teal-50 text-teal-800' => $cardStatus === 'active',
                            'bg-amber-50 text-amber-800' => $cardStatus === 'not_yet_valid',
                            'bg-rose-50 text-rose-800' => in_array($cardStatus, ['expired', 'disabled'], true),
                            'bg-slate-100 text-slate-600' => $cardStatus === null,
                        ])>{{ $cardStatus ? str_replace('_', ' ', $cardStatus) : 'Not assigned' }}</span>
                    </td>
                    @unless ($isReadOnly)
                    <td data-label="Assign / update card">
                        <form method="POST" action="{{ route('clinic.rfid.assign', $patient) }}" class="grid min-w-72 gap-2 sm:min-w-96 sm:grid-cols-2" data-confirm="Save the RFID card UID, validity dates, and status for {{ $patient->user->name }}?" data-confirm-title="Confirm RFID changes" data-confirm-accept="Save card settings">@csrf
                            <label class="form-label sm:col-span-2">RFID UID
                                <input class="form-input" name="uid" value="{{ $card?->uid }}" maxlength="100" placeholder="Scan or enter UID" required>
                            </label>
                            <label class="form-label">Valid from <span class="font-normal text-slate-400">(optional)</span>
                                <input class="form-input date-time-input" type="date" name="valid_from" value="{{ $card?->valid_from?->format('Y-m-d') }}" data-rfid-validity-picker data-clinic-today="{{ now(config('clinic.timezone'))->toDateString() }}" aria-label="Valid from date">
                            </label>
                            <label class="form-label">Valid until <span class="font-normal text-slate-400">(optional)</span>
                                <input class="form-input date-time-input" type="date" name="valid_until" value="{{ $card?->valid_until?->format('Y-m-d') }}" data-rfid-validity-picker data-clinic-today="{{ now(config('clinic.timezone'))->toDateString() }}" aria-label="Valid until date">
                            </label>
                            <label class="form-label">Status
                                <select class="form-input" name="is_active">
                                    <option value="1" @selected($card?->is_active ?? true)>Active</option>
                                    <option value="0" @selected($card !== null && ! $card->is_active)>Disabled</option>
                                </select>
                            </label>
                            <div class="flex items-end">
                                <button class="btn-secondary w-full">Save card settings</button>
                            </div>
                        </form>
                    </td>
                    @endunless
                </tr>
            @empty<tr><td colspan="{{ $isReadOnly ? 6 : 7 }}" class="py-10 text-center text-slate-500">No patient accounts yet.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $patients->links() }}</div>
    </section>
    <p class="mt-4 text-sm text-slate-500">RFID identifies the patient for staff; it does not grant access to a patient account. Check-in is completed from the Patient Queue tab.</p>
    @unless ($isReadOnly)
    <script>
        document.querySelectorAll('[data-rfid-validity-picker]').forEach((input, index) => {
            const picker = document.createElement('div');
            const trigger = document.createElement('button');
            const panel = document.createElement('section');
            const pickerId = `rfid-validity-picker-${index}`;
            const today = input.dataset.clinicToday;
            let selectedDate = input.value;
            let visibleMonth = (selectedDate || today).slice(0, 7);
            const weekdayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

            picker.className = 'rfid-validity-picker';
            trigger.type = 'button';
            trigger.className = 'rfid-validity-trigger';
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.setAttribute('aria-controls', pickerId);
            trigger.setAttribute('aria-label', input.getAttribute('aria-label'));
            panel.id = pickerId;
            panel.className = 'rfid-validity-panel hidden';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-label', `${input.getAttribute('aria-label')} picker`);
            panel.addEventListener('click', (event) => event.stopPropagation());
            input.classList.add('rfid-validity-native');
            input.setAttribute('aria-hidden', 'true');
            input.tabIndex = -1;
            input.parentNode.insertBefore(picker, input);
            picker.append(trigger, input);
            document.body.append(panel);

            const formatDate = (date) => {
                if (!date) {
                    return '';
                }

                const [year, month, day] = date.split('-').map(Number);

                return new Intl.DateTimeFormat('en', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                }).format(new Date(year, month - 1, day, 12));
            };

            const formatTrigger = () => {
                const content = document.createElement('span');
                const icon = document.createElement('span');
                const labels = document.createElement('span');
                const dateLabel = document.createElement('span');
                const chevron = document.createElement('span');

                content.className = 'rfid-validity-trigger-content';
                icon.className = 'rfid-validity-calendar-icon';
                icon.setAttribute('aria-hidden', 'true');
                icon.textContent = '▦';
                labels.className = 'grid min-w-0 gap-0.5';
                dateLabel.className = 'truncate';
                dateLabel.textContent = selectedDate ? formatDate(selectedDate) : 'Choose date';
                chevron.className = 'availability-time-chevron';
                chevron.setAttribute('aria-hidden', 'true');
                labels.append(dateLabel);
                content.append(icon, labels);
                trigger.replaceChildren(content, chevron);
            };

            const updateInput = () => {
                input.value = selectedDate;

                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                formatTrigger();
            };

            const renderPanel = () => {
                panel.replaceChildren();
                const [year, month] = visibleMonth.split('-').map(Number);
                const firstDay = new Date(year, month - 1, 1).getDay();
                const daysInMonth = new Date(year, month, 0).getDate();
                const header = document.createElement('div');
                const previousMonth = document.createElement('button');
                const monthTitle = document.createElement('h3');
                const nextMonth = document.createElement('button');
                const weekdays = document.createElement('div');
                const days = document.createElement('div');
                const footer = document.createElement('div');
                const clearButton = document.createElement('button');
                const doneButton = document.createElement('button');

                header.className = 'rfid-validity-panel-header';
                previousMonth.type = 'button';
                previousMonth.className = 'rfid-validity-nav';
                previousMonth.setAttribute('aria-label', 'Previous month');
                previousMonth.textContent = '‹';
                monthTitle.className = 'text-sm font-bold text-slate-900';
                monthTitle.textContent = new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' })
                    .format(new Date(year, month - 1, 1, 12));
                nextMonth.type = 'button';
                nextMonth.className = 'rfid-validity-nav';
                nextMonth.setAttribute('aria-label', 'Next month');
                nextMonth.textContent = '›';
                previousMonth.addEventListener('click', () => {
                    const previous = new Date(year, month - 2, 1, 12);
                    visibleMonth = `${previous.getFullYear()}-${String(previous.getMonth() + 1).padStart(2, '0')}`;
                    renderPanel();
                });
                nextMonth.addEventListener('click', () => {
                    const next = new Date(year, month, 1, 12);
                    visibleMonth = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
                    renderPanel();
                });
                header.append(previousMonth, monthTitle, nextMonth);

                weekdays.className = 'rfid-validity-weekdays mt-3 text-[0.65rem] font-semibold uppercase text-slate-400';
                weekdayNames.forEach((name) => {
                    const weekday = document.createElement('span');
                    weekday.textContent = name;
                    weekdays.append(weekday);
                });
                days.className = 'rfid-validity-days mt-1';
                for (let blank = 0; blank < firstDay; blank += 1) {
                    days.append(document.createElement('span'));
                }
                for (let day = 1; day <= daysInMonth; day += 1) {
                    const dayButton = document.createElement('button');
                    const dayValue = `${visibleMonth}-${String(day).padStart(2, '0')}`;
                    dayButton.type = 'button';
                    dayButton.className = `rfid-validity-day${dayValue === selectedDate ? ' rfid-validity-day-selected' : ''}`;
                    dayButton.textContent = String(day);
                    dayButton.setAttribute('aria-label', formatDate(dayValue));
                    dayButton.setAttribute('aria-pressed', String(dayValue === selectedDate));
                    dayButton.addEventListener('click', () => {
                        selectedDate = dayValue;
                        updateInput();
                        renderPanel();
                        positionPanel();
                    });
                    days.append(dayButton);
                }

                footer.className = 'rfid-validity-panel-footer mt-3 border-t border-slate-100 pt-3';
                clearButton.type = 'button';
                clearButton.className = 'text-sm font-semibold text-slate-500 hover:text-rose-700';
                clearButton.textContent = 'Clear limit';
                clearButton.addEventListener('click', () => {
                    selectedDate = '';
                    visibleMonth = today.slice(0, 7);
                    updateInput();
                    closePanel(true);
                });
                const todayButton = document.createElement('button');
                todayButton.type = 'button';
                todayButton.className = 'text-sm font-semibold text-teal-800 hover:underline';
                todayButton.textContent = 'Today';
                todayButton.addEventListener('click', () => {
                    selectedDate = today;
                    visibleMonth = today.slice(0, 7);
                    updateInput();
                    renderPanel();
                    positionPanel();
                });
                doneButton.type = 'button';
                doneButton.className = 'btn-primary px-4 py-1.5 text-sm';
                doneButton.textContent = 'Done';
                doneButton.addEventListener('click', () => closePanel(true));
                const footerActions = document.createElement('div');
                footerActions.className = 'flex items-center gap-4';
                footerActions.append(clearButton, todayButton);
                footer.append(footerActions, doneButton);
                panel.append(header, weekdays, days, footer);
            };

            const positionPanel = () => {
                const bounds = trigger.getBoundingClientRect();
                const width = Math.min(352, window.innerWidth - 16);
                const left = Math.min(Math.max(8, bounds.left), window.innerWidth - width - 8);
                const panelHeight = panel.getBoundingClientRect().height;
                const top = window.innerHeight - bounds.bottom >= panelHeight + 8 || bounds.top >= panelHeight + 8
                    ? (window.innerHeight - bounds.bottom >= panelHeight + 8 ? bounds.bottom + 4 : bounds.top - panelHeight - 4)
                    : Math.max(8, window.innerHeight - panelHeight - 8);

                panel.style.left = `${left}px`;
                panel.style.top = `${top}px`;
                panel.style.width = `${width}px`;
            };

            const closePanel = (returnFocus = false) => {
                panel.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
                if (returnFocus) {
                    trigger.focus();
                }
            };

            trigger.addEventListener('click', () => {
                const isOpen = trigger.getAttribute('aria-expanded') === 'true';
                document.querySelectorAll('.rfid-validity-panel').forEach((openPanel) => openPanel.classList.add('hidden'));
                document.querySelectorAll('.rfid-validity-trigger[aria-expanded="true"]')
                    .forEach((openTrigger) => openTrigger.setAttribute('aria-expanded', 'false'));
                if (isOpen) {
                    closePanel();
                    return;
                }

                renderPanel();
                panel.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
                positionPanel();
            });
            panel.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closePanel(true);
                }
            });
            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target) && !panel.contains(event.target)) {
                    closePanel();
                }
            });
            window.addEventListener('resize', () => {
                if (trigger.getAttribute('aria-expanded') === 'true') {
                    positionPanel();
                }
            });
            window.addEventListener('scroll', () => {
                if (trigger.getAttribute('aria-expanded') === 'true') {
                    positionPanel();
                }
            }, true);
            formatTrigger();
        });
    </script>
    @endunless
@endsection
