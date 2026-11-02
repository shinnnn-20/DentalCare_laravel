@extends('layouts.app')

@section('title', 'Manage Appointment Availability')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium text-teal-700">Clinic operations</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Manage Appointment Availability</h1>
        <p class="mt-2 text-slate-500">Block a branch-specific time range. Existing appointments remain booked and are never cancelled by a block.</p>
    </div>

    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-semibold text-slate-900">Block a date or time range</h2>
        <p class="mt-1 text-sm text-slate-500">Select 10:00 AM–5:00 PM in 30-minute increments. A full clinic day can be blocked from 10:00 AM to 5:00 PM.</p>
        <form method="POST" action="{{ route('clinic.availability.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-availability-form data-clinic-date="{{ $clinicNow->toDateString() }}" data-clinic-time="{{ $clinicNow->format('H:i') }}">
            @csrf
            <label class="form-label">Branch
                <select class="form-input" name="branch_id" required>
                    <option value="">Choose a branch</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $hasAssignedBranch ? auth()->user()->branch_id : '') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">Date
                <input class="form-input date-time-input" type="date" name="date" min="{{ $clinicNow->toDateString() }}" value="{{ old('date', $clinicNow->toDateString()) }}" data-availability-date required>
            </label>
            <label class="form-label">Reason
                <select class="form-input" name="reason" required>
                    <option value="">Choose a reason</option>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ $reason }}</option>
                    @endforeach
                </select>
            </label>
            <div class="form-label"><span>Start time</span>
                <span class="availability-time-control">
                    <select class="form-input" name="starts_at" data-availability-time="start" required>
                        <option value="">Select time</option>
                        @for ($minutes = 600; $minutes <= 990; $minutes += 30)
                            @php($timeValue = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60))
                            <option value="{{ $timeValue }}" @selected(old('starts_at') === $timeValue)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $timeValue)->format('g:i A') }}</option>
                        @endfor
                    </select>
                </span>
            </div>
            <div class="form-label"><span>End time</span>
                <span class="availability-time-control">
                    <select class="form-input" name="ends_at" data-availability-time="end" required>
                        <option value="">Select time</option>
                        @for ($minutes = 630; $minutes <= 1020; $minutes += 30)
                            @php($timeValue = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60))
                            <option value="{{ $timeValue }}" @selected(old('ends_at') === $timeValue)>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $timeValue)->format('g:i A') }}</option>
                        @endfor
                    </select>
                </span>
            </div>
            <p class="hidden rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 sm:col-span-2 xl:col-span-3" data-time-range-error role="alert"></p>
            <label class="form-label sm:col-span-2 xl:col-span-3">Additional description <span class="font-normal text-slate-400">(optional)</span>
                <textarea class="form-input min-h-24" name="description" maxlength="1000">{{ old('description') }}</textarea>
            </label>
            <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 sm:col-span-2 xl:col-span-3">
                <input class="mt-1" type="checkbox" name="confirm_existing_appointments" value="1" @checked(old('confirm_existing_appointments'))>
                <span>Confirm blocking even if existing appointments overlap. Those appointments will remain booked and must be handled separately.</span>
            </label>
            <div class="sm:col-span-2 xl:col-span-3">
                <button class="btn-primary">Block appointment availability</button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Availability blocks</h2>
            <p class="mt-1 text-sm text-slate-500">Active blocks prevent patient bookings and appointment approvals at the selected branch.</p>
            <form method="GET" action="{{ route('clinic.availability.index') }}" class="mt-4 grid gap-3 sm:flex sm:flex-wrap sm:items-end">
                <label class="form-label sm:min-w-48">Branch
                    <select class="form-input" name="branch_id">
                        @unless ($hasAssignedBranch)
                            <option value="">All Branches</option>
                        @endunless
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label sm:min-w-48">Date
                    <input class="form-input date-time-input" type="date" name="date" value="{{ request('date') }}">
                </label>
                <button class="btn-secondary">Filter</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Branch</th><th>Date and time</th><th>Reason</th><th>Description</th><th>Created by</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($availabilityBlocks as $availabilityBlock)
                        <tr>
                            <td data-label="Branch">{{ $availabilityBlock->branch->name }}</td>
                            <td data-label="Date and time">
                                <span class="font-semibold">{{ $availabilityBlock->starts_at->format('M j, Y') }}</span>
                                <span class="block text-xs text-slate-500">{{ $availabilityBlock->starts_at->format('g:i A') }}–{{ $availabilityBlock->ends_at->format('g:i A') }}</span>
                            </td>
                            <td data-label="Reason">{{ $availabilityBlock->reason }}</td>
                            <td data-label="Description">{{ $availabilityBlock->description ?: '—' }}</td>
                            <td data-label="Created by">{{ $availabilityBlock->creator?->name ?? 'Former user' }}</td>
                            <td data-label="Status">
                                <span @class(['status-badge', 'bg-teal-50 text-teal-800' => $availabilityBlock->is_active, 'bg-slate-100 text-slate-600' => ! $availabilityBlock->is_active])>{{ $availabilityBlock->is_active ? 'Active' : 'Disabled' }}</span>
                            </td>
                            <td data-label="Action">
                                <form method="POST" action="{{ route('clinic.availability.update', $availabilityBlock) }}" class="flex flex-col gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $availabilityBlock->is_active ? 0 : 1 }}">
                                    @if (! $availabilityBlock->is_active)
                                        <label class="flex items-center gap-2 text-xs text-amber-900">
                                            <input type="checkbox" name="confirm_existing_appointments" value="1">
                                            Confirm if visits overlap
                                        </label>
                                    @endif
                                    <button class="btn-small {{ $availabilityBlock->is_active ? 'text-rose-700' : 'text-teal-800' }}">{{ $availabilityBlock->is_active ? 'Enable time again' : 'Re-enable block' }}</button>
                                </form>
                                @if (auth()->user()->hasRole('admin', 'staff'))
                                    <form method="POST" action="{{ route('clinic.availability.destroy', $availabilityBlock) }}" class="mt-2" onsubmit="return confirm('Permanently delete this availability block?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-small w-full text-rose-700">Delete block</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-slate-500">No appointment availability blocks match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $availabilityBlocks->links() }}</div>
    </section>
<script>
        document.querySelectorAll('[data-availability-time]').forEach((select, index) => {
            const picker = document.createElement('span');
            const trigger = document.createElement('button');
            const menu = document.createElement('div');
            const menuId = `availability-time-options-${index}`;
            const form = select.form;
            const dateInput = form.querySelector('[data-availability-date]');
            const errorMessage = form.querySelector('[data-time-range-error]');

            picker.className = 'availability-time-picker';
            trigger.type = 'button';
            trigger.className = 'availability-time-trigger';
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.setAttribute('aria-controls', menuId);
            trigger.setAttribute('aria-label', select.dataset.availabilityTime === 'start' ? 'Select start time' : 'Select end time');
            menu.id = menuId;
            menu.className = 'availability-time-menu hidden';
            menu.setAttribute('role', 'listbox');
            menu.setAttribute('aria-label', select.dataset.availabilityTime === 'start' ? 'Start times' : 'End times');
            select.classList.add('availability-time-native');
            select.required = false;
            select.setAttribute('aria-hidden', 'true');
            select.tabIndex = -1;
            select.parentNode.insertBefore(picker, select);
            picker.append(trigger, select);
            document.body.append(menu);

            const closeMenu = (returnFocus = false) => {
                menu.classList.add('hidden');
                menu.classList.remove('availability-time-menu-fixed');
                menu.style.removeProperty('left');
                menu.style.removeProperty('top');
                menu.style.removeProperty('width');
                trigger.setAttribute('aria-expanded', 'false');
                if (returnFocus) {
                    trigger.focus();
                }
            };

            const positionMenu = () => {
                const bounds = trigger.getBoundingClientRect();
                const menuHeight = Math.min(menu.scrollHeight, window.innerHeight * 0.4, 240);
                const spaceBelow = window.innerHeight - bounds.bottom;
                const top = spaceBelow >= menuHeight + 8 || spaceBelow >= bounds.top
                    ? bounds.bottom + 4
                    : Math.max(8, bounds.top - menuHeight - 4);
                menu.classList.add('availability-time-menu-fixed');
                menu.style.left = `${Math.max(8, bounds.left)}px`;
                menu.style.top = `${top}px`;
                menu.style.width = `${bounds.width}px`;
            };

            const renderMenu = () => {
                const selectedValue = select.value;
                menu.replaceChildren();

                Array.from(select.options).filter((option) => option.value !== '').forEach((option) => {
                    const item = document.createElement('button');
                    const label = document.createElement('span');
                    const interval = document.createElement('span');

                    item.type = 'button';
                    item.className = 'availability-time-option';
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', String(option.value === selectedValue));
                    item.disabled = option.disabled;
                    label.textContent = option.textContent;
                    interval.className = 'availability-time-interval';
                    interval.textContent = '30 min';
                    item.append(label, interval);
                    item.addEventListener('click', () => {
                        select.value = option.value;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        closeMenu(true);
                    });
                    menu.append(item);
                });
            };

            const updateTrigger = () => {
                trigger.replaceChildren();
                const selectedOption = select.selectedOptions[0];
                const label = document.createElement('span');
                const icon = document.createElement('span');

                label.className = selectedOption?.value ? '' : 'text-slate-500';
                label.textContent = selectedOption?.textContent || 'Select time';
                icon.className = 'availability-time-chevron';
                icon.setAttribute('aria-hidden', 'true');
                trigger.append(label, icon);
                trigger.setAttribute('aria-disabled', String(select.disabled));
                trigger.disabled = select.disabled;
            };

            const updateChoices = () => {
                const startSelect = form.querySelector('[data-availability-time="start"]');
                const endSelect = form.querySelector('[data-availability-time="end"]');
                const isToday = dateInput.value === form.dataset.clinicDate;
                const isPastTime = (time) => isToday && time <= form.dataset.clinicTime;

                if (isPastTime(startSelect.value)) {
                    startSelect.value = '';
                }
                if (isPastTime(endSelect.value)) {
                    endSelect.value = '';
                }

                Array.from(startSelect.options).forEach((option) => {
                    if (option.value !== '') {
                        option.disabled = isPastTime(option.value)
                            || (endSelect.value !== '' && option.value >= endSelect.value);
                    }
                });
                Array.from(endSelect.options).forEach((option) => {
                    if (option.value !== '') {
                        option.disabled = isPastTime(option.value)
                            || (startSelect.value !== '' && option.value <= startSelect.value);
                    }
                });

                form.querySelectorAll('[data-availability-time]').forEach((timeSelect) => {
                    timeSelect.dispatchEvent(new Event('availability-options-updated'));
                });
            };

            if (index === 0) {
                dateInput.addEventListener('change', updateChoices);
            }

            trigger.addEventListener('click', () => {
                if (trigger.disabled) {
                    return;
                }

                const isOpen = trigger.getAttribute('aria-expanded') === 'true';
                document.querySelectorAll('.availability-time-menu-fixed').forEach((openMenu) => {
                    openMenu.classList.add('hidden');
                    openMenu.classList.remove('availability-time-menu-fixed');
                });
                document.querySelectorAll('.availability-time-trigger[aria-expanded="true"]').forEach((openTrigger) => {
                    openTrigger.setAttribute('aria-expanded', 'false');
                });

                if (isOpen) {
                    closeMenu();
                    return;
                }

                renderMenu();
                menu.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
                positionMenu();
                menu.querySelector('button:not(:disabled)')?.focus();
            });

            select.addEventListener('change', () => {
                errorMessage.classList.add('hidden');
                const startSelect = form.querySelector('[data-availability-time="start"]');
                const endSelect = form.querySelector('[data-availability-time="end"]');

                if (startSelect.value && endSelect.value && startSelect.value >= endSelect.value) {
                    if (select.dataset.availabilityTime === 'start') {
                        endSelect.value = '';
                    } else {
                        startSelect.value = '';
                    }
                }

                updateChoices();
                updateTrigger();
            });
            select.addEventListener('availability-options-updated', () => {
                renderMenu();
                updateTrigger();
            });
            menu.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeMenu(true);
                }
            });
            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target) && !menu.contains(event.target)) {
                    closeMenu();
                }
            });
            window.addEventListener('resize', () => {
                if (trigger.getAttribute('aria-expanded') === 'true') {
                    positionMenu();
                }
            });
            window.addEventListener('scroll', () => {
                if (trigger.getAttribute('aria-expanded') === 'true') {
                    positionMenu();
                }
            }, true);

            updateChoices();
        });

        document.querySelector('[data-availability-form]').addEventListener('submit', (event) => {
            const form = event.currentTarget;
            const startSelect = form.querySelector('[data-availability-time="start"]');
            const endSelect = form.querySelector('[data-availability-time="end"]');
            const errorMessage = form.querySelector('[data-time-range-error]');

            if (startSelect.value && endSelect.value && startSelect.value < endSelect.value) {
                return;
            }

            event.preventDefault();
            errorMessage.textContent = 'Choose an end time after the start time.';
            errorMessage.classList.remove('hidden');
            form.querySelector(`[data-availability-time="${startSelect.value ? 'end' : 'start'}"]`)
                .previousElementSibling
                .focus();
        });
</script>
@endsection
