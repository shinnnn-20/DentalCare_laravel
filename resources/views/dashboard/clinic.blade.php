@extends('layouts.app')

@section('title', 'Clinic overview')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-teal-700">{{ now()->format('l, F j, Y') }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Clinic overview</h1>
            <p class="mt-2 text-slate-500">A quick view of today's clinic activity.</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2">
                @if ($isAdmin)
                    <label class="form-label">Statistics period
                        <select class="form-input min-w-40" name="statistics_period">
                            <option value="week" @selected($statistics['period'] === 'week')>Last 7 days</option>
                            <option value="month" @selected($statistics['period'] === 'month')>Last 30 days</option>
                            <option value="year" @selected($statistics['period'] === 'year')>Last 12 months</option>
                        </select>
                    </label>
                @endif
                <label class="form-label">Branch
                    <select class="form-input min-w-48" name="branch_id">
                        @unless (auth()->user()->hasRole('staff', 'doctor') && auth()->user()->branch_id !== null)
                            <option value="" @selected($selectedBranchId === null)>All branches</option>
                        @endunless
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                        @unless (auth()->user()->hasRole('staff', 'doctor') && auth()->user()->branch_id !== null)
                            <option value="unassigned" @selected($selectedBranchId === 'unassigned')>Unassigned appointments</option>
                        @endunless
                    </select>
                </label>
                <button class="btn-secondary" type="submit">Apply filters</button>
            </form>
            <a href="{{ route('appointments.index') }}" class="btn-primary">View appointments</a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($metrics as [$label, $value, $hint, $detailKey])
            @if ($detailKey === null)
                <article class="stat-card">
                    <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ $value }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ $hint }}</p>
                </article>
            @else
                <article class="stat-card p-0 transition hover:border-teal-300 hover:shadow-md focus-within:ring-2 focus-within:ring-teal-200">
                    <button class="block w-full rounded-2xl p-5 text-left focus:outline-none" type="button" data-dashboard-detail="{{ $detailKey }}" aria-haspopup="dialog">
                        <span class="block text-sm font-medium text-slate-500">{{ $label }}</span>
                        <span class="mt-3 block text-3xl font-bold text-slate-900">{{ $value }}</span>
                        <span class="mt-2 block text-xs text-slate-400">{{ $hint }}</span>
                        <span class="mt-3 block text-xs font-semibold text-teal-800">View Details</span>
                    </button>
                </article>
            @endif
        @endforeach
    </div>

    <dialog class="m-auto max-h-[90dvh] w-[min(72rem,calc(100vw-2rem))] overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50" data-dashboard-details data-url="{{ route('dashboard.details') }}" data-branch="{{ $selectedBranchId ?? '' }}" data-default-appointment-branch="{{ $selectedBranchId ?? 'all' }}" data-period="{{ $isAdmin ? $statistics['period'] : '' }}">
        <section class="flex max-h-[90dvh] flex-col bg-white">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 sm:p-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-900" data-details-title>Statistics details</h2>
                    <p class="mt-1 text-sm text-slate-500" data-details-count aria-live="polite"></p>
                </div>
                <button class="btn-secondary shrink-0" type="button" data-details-close>Close</button>
            </header>
            <div class="hidden grid gap-3 border-b border-slate-200 px-4 py-3 sm:grid-cols-2 sm:px-6" data-appointment-filters>
                <label class="form-label">Filter by Status
                    <select class="form-input" data-appointment-status>
                        <option value="all">All Statuses</option>
                        <option value="waiting">Waiting</option>
                        <option value="approved">Approved</option>
                        <option value="in_consultation">In Consultation</option>
                        <option value="billed">Billed</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                        <option value="pending">Pending</option>
                        <option value="checked_in">Checked In</option>
                    </select>
                </label>
                <label class="form-label">Filter by Branch
                    <select class="form-input" data-appointment-branch>
                        <option value="all">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                        <option value="unassigned">Unassigned appointments</option>
                    </select>
                </label>
            </div>
            <div class="min-h-32 overflow-auto p-4 sm:p-6">
                <p class="hidden rounded-lg bg-rose-50 p-4 text-sm text-rose-800" role="alert" data-details-error></p>
                <p class="py-8 text-center text-sm text-slate-500" data-details-loading>Loading records…</p>
                <p class="hidden py-8 text-center text-sm text-slate-500" data-details-empty>No records found.</p>
                <div class="hidden overflow-x-auto" data-details-table-wrap>
                    <table class="data-table">
                        <thead><tr data-details-head></tr></thead>
                        <tbody data-details-body></tbody>
                    </table>
                </div>
            </div>
            <footer class="flex items-center justify-between gap-3 border-t border-slate-200 p-4">
                <button class="btn-secondary" type="button" data-details-previous disabled>Previous</button>
                <p class="text-sm text-slate-500" data-details-page></p>
                <button class="btn-secondary" type="button" data-details-next disabled>Next</button>
            </footer>
        </section>
    </dialog>

    @if ($isAdmin)
        <section class="mt-8" data-admin-statistics data-branch="{{ $selectedBranchName }}">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Clinic statistics</h2>
                    <p class="mt-1 text-sm text-slate-500">Appointments and recorded payments · {{ $statistics['rangeLabel'] }} · {{ $selectedBranchName }}</p>
                </div>
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-5" data-appointments-trend>
                    <h3 class="font-semibold text-slate-900">Appointments over time</h3>
                    <p class="mt-1 text-sm text-slate-500">Scheduled appointments by day or month.</p>
                    <div class="mt-5 flex h-44 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Appointment counts by date">
                        @foreach ($statistics['timeline'] as $point)
                            <div class="flex h-full min-w-9 flex-1 flex-col items-center justify-end gap-1">
                                <span class="text-xs font-semibold text-slate-700">{{ $point['appointments'] }}</span>
                                <div class="flex h-28 w-full items-end rounded-t-md bg-slate-50">
                                    <div class="w-full rounded-t-md bg-teal-600" style="height: {{ $point['appointments'] / $statistics['appointmentMaximum'] * 100 }}%"></div>
                                </div>
                                <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5" data-revenue-trend>
                    <h3 class="font-semibold text-slate-900">Revenue collected</h3>
                    <p class="mt-1 text-sm text-slate-500">Applied payment amounts recorded during the selected period at {{ $selectedBranchName }}.</p>
                    <div class="mt-5 flex h-44 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Applied payments by date">
                        @foreach ($statistics['timeline'] as $point)
                            <div class="flex h-full min-w-9 flex-1 flex-col items-center justify-end gap-1">
                                <span class="whitespace-nowrap text-[0.65rem] font-semibold text-slate-700">₱{{ number_format($point['revenue'], 0) }}</span>
                                <div class="flex h-28 w-full items-end rounded-t-md bg-slate-50">
                                    <div class="w-full rounded-t-md bg-cyan-600" style="height: {{ $point['revenue'] / $statistics['revenueMaximum'] * 100 }}%"></div>
                                </div>
                                <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-5 xl:col-span-2" data-appointment-statuses>
                    <h3 class="font-semibold text-slate-900">Appointment status distribution</h3>
                    <p class="mt-1 text-sm text-slate-500">Statuses currently recorded for appointments in the selected period.</p>
                    @forelse ($statistics['statuses'] as $status)
                        <div class="mt-4 grid grid-cols-[minmax(7rem,1fr)_minmax(0,3fr)_auto] items-center gap-3">
                            <span class="text-sm font-medium capitalize text-slate-700">{{ $status['label'] }}</span>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-teal-600" style="width: {{ $status['percentage'] }}%"></div>
                            </div>
                            <span class="min-w-16 text-right text-sm font-semibold text-slate-900">{{ $status['count'] }} {{ $status['count'] === 1 ? 'appointment' : 'appointments' }}</span>
                        </div>
                    @empty
                        <p class="mt-4 text-sm text-slate-500">No appointments in this period.</p>
                    @endforelse
                </article>
            </div>
        </section>
    @endif

    <section class="mt-8 rounded-2xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div><h2 class="font-semibold text-slate-900">Today's schedule</h2><p class="mt-1 text-sm text-slate-500">Upcoming and active appointments</p></div>
            <a class="text-sm font-semibold text-teal-800 hover:underline" href="{{ route('appointments.index') }}">Full schedule</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Time</th><th>Patient</th><th>Service</th><th>Type</th><th>Status</th><th>Queue</th></tr></thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td data-label="Time"><span class="inline-flex flex-col gap-1"><span class="font-semibold text-slate-800">{{ $appointment->starts_at->format('g:i A') }}</span><span class="text-xs text-slate-500">{{ $appointment->starts_at->format('M j, Y') }}</span></span></td>
                        <td data-label="Patient"><a class="font-semibold text-teal-800 hover:underline" href="{{ route('clinic.patients.show', $appointment->patient) }}">{{ $appointment->patient->user->name }}</a><div class="text-xs text-slate-400">{{ $appointment->patient->patient_number }}</div></td>
                        <td data-label="Service"><span class="inline-flex rounded-lg bg-teal-50 px-2.5 py-1.5 font-semibold text-teal-900">{{ $appointment->treatmentNames() }}</span></td>
                        <td data-label="Type" class="capitalize">{{ str_replace('_', ' ', $appointment->type) }}</td>
                        <td data-label="Status"><span class="status-badge">{{ str_replace('_', ' ', $appointment->status) }}</span></td>
                        <td data-label="Queue">{{ $appointment->queueEntry?->queue_number ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">No appointments scheduled for today.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <script>
        (() => {
            const dialog = document.querySelector('[data-dashboard-details]');
            const title = dialog.querySelector('[data-details-title]');
            const count = dialog.querySelector('[data-details-count]');
            const error = dialog.querySelector('[data-details-error]');
            const loading = dialog.querySelector('[data-details-loading]');
            const empty = dialog.querySelector('[data-details-empty]');
            const tableWrap = dialog.querySelector('[data-details-table-wrap]');
            const tableHead = dialog.querySelector('[data-details-head]');
            const tableBody = dialog.querySelector('[data-details-body]');
            const pageLabel = dialog.querySelector('[data-details-page]');
            const previous = dialog.querySelector('[data-details-previous]');
            const next = dialog.querySelector('[data-details-next]');
            const close = dialog.querySelector('[data-details-close]');
            const appointmentFilters = dialog.querySelector('[data-appointment-filters]');
            const appointmentStatus = dialog.querySelector('[data-appointment-status]');
            const appointmentBranch = dialog.querySelector('[data-appointment-branch]');
            let selectedMetric = '';
            let currentPage = 1;
            let lastPage = 1;

            const setVisible = (element, isVisible) => element.classList.toggle('hidden', !isVisible);
            const setLoading = (isLoading) => {
                setVisible(loading, isLoading);
                setVisible(error, false);
                setVisible(empty, false);
                setVisible(tableWrap, false);
            };
            const renderDetails = (payload) => {
                title.textContent = payload.title;
                count.textContent = `${payload.total} ${payload.total === 1 ? 'record' : 'records'}`;
                currentPage = payload.page;
                lastPage = payload.last_page;
                pageLabel.textContent = payload.total > 0 ? `Page ${currentPage} of ${lastPage}` : '';
                previous.disabled = currentPage <= 1;
                next.disabled = currentPage >= lastPage;
                tableHead.replaceChildren();
                tableBody.replaceChildren();

                Object.entries(payload.columns).forEach(([key, label]) => {
                    const heading = document.createElement('th');
                    heading.scope = 'col';
                    heading.textContent = label;
                    tableHead.append(heading);
                });
                payload.records.forEach((record) => {
                    const row = document.createElement('tr');
                    Object.keys(payload.columns).forEach((key) => {
                        const cell = document.createElement('td');
                        cell.dataset.label = payload.columns[key];
                        cell.textContent = record[key] ?? '—';
                        row.append(cell);
                    });
                    tableBody.append(row);
                });
                setVisible(empty, payload.total === 0);
                setVisible(tableWrap, payload.total > 0);
            };
            const loadPage = async (page) => {
                setLoading(true);
                const url = new URL(dialog.dataset.url, window.location.origin);
                url.searchParams.set('metric', selectedMetric);
                url.searchParams.set('page', String(page));
                if (dialog.dataset.branch) {
                    url.searchParams.set('branch_id', dialog.dataset.branch);
                }
                if (dialog.dataset.period) {
                    url.searchParams.set('statistics_period', dialog.dataset.period);
                }
                if (selectedMetric === 'today_appointments') {
                    url.searchParams.set('appointment_status', appointmentStatus.value);
                    url.searchParams.set('appointment_branch_id', appointmentBranch.value);
                }

                try {
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error('Could not load these dashboard records. Please close the popup and try again.');
                    }
                    renderDetails(await response.json());
                } catch (requestError) {
                    error.textContent = requestError instanceof Error
                        ? requestError.message
                        : 'Could not load these dashboard records. Please close the popup and try again.';
                    setVisible(error, true);
                } finally {
                    setVisible(loading, false);
                }
            };

            document.querySelectorAll('[data-dashboard-detail]').forEach((button) => {
                button.addEventListener('click', () => {
                    selectedMetric = button.dataset.dashboardDetail;
                    const isTodayAppointments = selectedMetric === 'today_appointments';
                    appointmentFilters.classList.toggle('hidden', !isTodayAppointments);
                    if (isTodayAppointments) {
                        appointmentStatus.value = 'all';
                        appointmentBranch.value = dialog.dataset.defaultAppointmentBranch || 'all';
                    }
                    dialog.showModal();
                    loadPage(1);
                });
            });
            appointmentStatus.addEventListener('change', () => loadPage(1));
            appointmentBranch.addEventListener('change', () => loadPage(1));
            close.addEventListener('click', () => dialog.close());
            previous.addEventListener('click', () => loadPage(currentPage - 1));
            next.addEventListener('click', () => loadPage(currentPage + 1));
        })();
    </script>
@endsection
