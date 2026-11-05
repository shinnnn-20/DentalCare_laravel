@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="clinic-report-page">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print-hidden">
            <div><p class="text-sm font-medium text-teal-700">{{ auth()->user()->role === 'doctor' ? 'Doctor portal' : 'Administration' }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Clinic reports</h1><p class="mt-2 text-slate-500">Summary for {{ $from }} through {{ $to }} · {{ $selectedBranchName }}.</p></div>
            <button class="btn-secondary" onclick="window.print()">Print</button>
        </div>
        <form method="GET" class="mb-6 flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5 print-hidden">
            <label class="form-label">From<input class="form-input" type="date" name="from" value="{{ $from }}" required></label>
            <label class="form-label">To<input class="form-input" type="date" name="to" value="{{ $to }}" required></label>
            <label class="form-label">Branch
                <select class="form-input min-w-48" name="branch_id">
                    @unless ($hasAssignedBranch)
                        <option value="" @selected($selectedBranchId === null)>All branches</option>
                    @endunless
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) $selectedBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                    @unless ($hasAssignedBranch)
                        <option value="unassigned" @selected($selectedBranchId === 'unassigned')>Unassigned appointments</option>
                    @endunless
                </select>
            </label>
            <label class="form-label">Blocked time by
                <select class="form-input" name="availability_period">
                    <option value="month" @selected($availabilityPeriod === 'month')>Month</option>
                    <option value="year" @selected($availabilityPeriod === 'year')>Year</option>
                </select>
            </label>
            <button class="btn-primary">Apply filters</button>
        </form>
        <header class="report-print-header" data-report-branch="{{ $selectedBranchName }}">
            <img class="report-print-logo" src="{{ asset('images/landing/doc-brigs-dental-care-logo.jpg') }}" alt="Doc Brigs Dental Care logo">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-800">{{ config('clinic.name') }}</p>
            <h1 class="mt-2 text-2xl font-bold">Clinic Activity Report</h1>
            <p class="mt-2 text-sm">Reporting period: <strong>{{ \Illuminate\Support\Carbon::parse($from)->format('F j, Y') }}</strong> through <strong>{{ \Illuminate\Support\Carbon::parse($to)->format('F j, Y') }}</strong></p>
            <p class="mt-1 text-sm">Branch: <strong>{{ $selectedBranchName }}</strong></p>
            <p class="mt-1 text-xs text-slate-500">{{ config('clinic.address') }} · {{ config('clinic.phone') }}</p>
        </header>
        <div class="report-cards grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-report-details-url="{{ route('admin.reports.details') }}" data-report-from="{{ $from }}" data-report-to="{{ $to }}" data-report-branch="{{ $selectedBranchId ?? '' }}">
        @foreach ([
            ['Appointments', $appointments, 'appointments'],
            ['Walk-in appointments', $walkIns, 'walk_ins'],
            ['Completed appointments', $completed, 'completed'],
            ['Cancelled appointments', $cancelled, 'cancelled'],
            ['New patient registrations', $newPatients, 'new_patients'],
            ['Revenue collected', '₱'.number_format((float) $revenue, 2), 'revenue'],
        ] as [$label, $value, $metric])
            <article class="stat-card flex flex-col items-start">
                <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold text-slate-900">{{ $value }}</p>
                <button class="mt-3 text-sm font-semibold text-teal-800 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 print-hidden"
                    type="button" data-report-detail="{{ $metric }}" aria-haspopup="dialog">
                    View Details
                </button>
            </article>
        @endforeach
        </div>
        <dialog class="m-auto max-h-[90dvh] w-[min(72rem,calc(100vw-2rem))] overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50 print-hidden" data-report-details>
            <section class="flex max-h-[90dvh] flex-col bg-white">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900" data-report-detail-title>Report details</h2>
                        <p class="mt-1 text-sm text-slate-500" data-report-detail-count aria-live="polite"></p>
                    </div>
                    <button class="btn-secondary shrink-0" type="button" data-report-detail-close>Close</button>
                </header>
                <div class="hidden grid gap-3 border-b border-slate-200 px-4 py-3 sm:grid-cols-2 sm:px-6" data-report-revenue-filters>
                    <label class="form-label">Filter by Branch
                        <select class="form-input" data-report-revenue-branch>
                            @unless ($hasAssignedBranch)
                                <option value="">All branches</option>
                            @endunless
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                            @unless ($hasAssignedBranch)
                                <option value="unassigned">Unassigned appointments</option>
                            @endunless
                        </select>
                    </label>
                    <form class="flex items-end gap-2" data-report-revenue-search-form>
                        <label class="form-label min-w-0 flex-1">Patient name or ID
                            <input class="form-input" type="search" maxlength="120" placeholder="Search payments" data-report-revenue-patient>
                        </label>
                        <button class="btn-secondary shrink-0" type="submit">Search</button>
                    </form>
                </div>
                <div class="min-h-32 overflow-auto p-4 sm:p-6">
                    <p class="hidden rounded-lg bg-rose-50 p-4 text-sm text-rose-800" role="alert" data-report-detail-error></p>
                    <p class="py-8 text-center text-sm text-slate-500" data-report-detail-loading>Loading records…</p>
                    <p class="hidden py-8 text-center text-sm text-slate-500" data-report-detail-empty>No records found for this report selection.</p>
                    <div class="hidden overflow-x-auto" data-report-detail-table-wrap>
                        <table class="data-table">
                            <thead><tr data-report-detail-head></tr></thead>
                            <tbody data-report-detail-body></tbody>
                        </table>
                    </div>
                </div>
                <footer class="flex items-center justify-between gap-3 border-t border-slate-200 p-4">
                    <button class="btn-secondary" type="button" data-report-detail-previous disabled>Previous</button>
                    <p class="text-sm text-slate-500" data-report-detail-page></p>
                    <button class="btn-secondary" type="button" data-report-detail-next disabled>Next</button>
                </footer>
            </section>
        </dialog>
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5" data-report-availability>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Blocked appointment availability</h2>
                    <p class="mt-1 text-sm text-slate-500">Active blocked dates and time for the selected range and branch.</p>
                </div>
                <p class="text-sm text-slate-600"><strong class="text-slate-900">{{ $availabilityBlocks }}</strong> blocks · <strong class="text-slate-900">{{ number_format($availabilityBlockedHours, 2) }}</strong> hours</p>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>{{ $availabilityPeriod === 'year' ? 'Year' : 'Month' }}</th><th>Blocked intervals</th><th>Blocked hours</th></tr></thead>
                    <tbody>
                        @foreach ($availabilityBreakdown as $period)
                            <tr><td>{{ $period['label'] }}</td><td>{{ $period['blocks'] }}</td><td>{{ number_format($period['hours'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="print-hidden mt-8 grid gap-4 xl:grid-cols-2" data-report-statistics>
            <article class="rounded-2xl border border-slate-200 bg-white p-5" data-report-appointments>
                <h2 class="font-semibold text-slate-900">Appointments over time</h2>
                <p class="mt-1 text-sm text-slate-500">Appointments by {{ count($timeline) === 1 ? 'selected period' : 'date range bucket' }}.</p>
                <div class="mt-5 flex h-48 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Appointments by date for the selected report range">
                    @foreach ($timeline as $point)
                        <div class="flex h-full min-w-10 flex-1 flex-col items-center justify-end gap-1">
                            <span class="text-xs font-semibold text-slate-700">{{ $point['appointments'] }}</span>
                            <div class="flex h-32 w-full items-end rounded-t-md bg-slate-50">
                                <div class="w-full rounded-t-md bg-teal-600" style="height: {{ $point['appointments'] / $appointmentMaximum * 100 }}%"></div>
                            </div>
                            <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5" data-report-revenue>
                <h2 class="font-semibold text-slate-900">Revenue collected</h2>
                <p class="mt-1 text-sm text-slate-500">Applied payments recorded within this report range.</p>
                <div class="mt-5 flex h-48 items-end gap-2 overflow-x-auto border-b border-slate-100 pb-2" role="img" aria-label="Applied payment totals by date for the selected report range">
                    @foreach ($timeline as $point)
                        <div class="flex h-full min-w-10 flex-1 flex-col items-center justify-end gap-1">
                            <span class="whitespace-nowrap text-[0.65rem] font-semibold text-slate-700">₱{{ number_format($point['revenue'], 0) }}</span>
                            <div class="flex h-32 w-full items-end rounded-t-md bg-slate-50">
                                <div class="w-full rounded-t-md bg-cyan-600" style="height: {{ $point['revenue'] / $revenueMaximum * 100 }}%"></div>
                            </div>
                            <span class="whitespace-nowrap text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 xl:col-span-2" data-report-statuses>
                <h2 class="font-semibold text-slate-900">Appointment status distribution</h2>
                <p class="mt-1 text-sm text-slate-500">Current system statuses for appointments scheduled within this report range.</p>
                @forelse ($statuses as $status)
                    <div class="mt-4 grid grid-cols-[minmax(7rem,1fr)_minmax(0,3fr)_auto] items-center gap-3">
                        <span class="text-sm font-medium capitalize text-slate-700">{{ $status['label'] }}</span>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-teal-600" style="width: {{ $status['percentage'] }}%"></div>
                        </div>
                        <span class="min-w-16 text-right text-sm font-semibold text-slate-900">{{ $status['count'] }}</span>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-slate-500">No appointments in this report range.</p>
                @endforelse
            </article>
        </section>
        <div class="report-print-table-wrapper">
            <table class="report-print-table">
                <thead><tr><th>Measure</th><th>Result</th></tr></thead>
                <tbody>
                    <tr><td>Appointments</td><td>{{ $appointments }}</td></tr>
                    <tr><td>Walk-in appointments</td><td>{{ $walkIns }}</td></tr>
                    <tr><td>Completed appointments</td><td>{{ $completed }}</td></tr>
                    <tr><td>Cancelled appointments</td><td>{{ $cancelled }}</td></tr>
                    <tr><td>New patient registrations</td><td>{{ $newPatients }}</td></tr>
                    <tr><td>Revenue collected</td><td>₱{{ number_format((float) $revenue, 2) }}</td></tr>
                    <tr><td>Active blocked intervals</td><td>{{ $availabilityBlocks }}</td></tr>
                    <tr><td>Active blocked hours</td><td>{{ number_format($availabilityBlockedHours, 2) }}</td></tr>
                    @foreach ($availabilityBreakdown as $period)
                        <tr><td>Blocked intervals · {{ $period['label'] }}</td><td>{{ $period['blocks'] }}</td></tr>
                        <tr><td>Blocked hours · {{ $period['label'] }}</td><td>{{ number_format($period['hours'], 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="report-note mt-5 text-sm text-slate-500">Revenue uses applied payment amounts; partial payments are included only for their paid portion. Revenue includes PayMongo TEST MODE payments when present; they simulate payment and do not transfer real money.</p>
        <footer class="report-print-footer">Generated {{ now(config('clinic.timezone'))->format('F j, Y · g:i A') }} · {{ config('clinic.name') }}</footer>
    </div>
    <script>
        (() => {
            const cards = document.querySelector('[data-report-details-url]');
            const dialog = document.querySelector('[data-report-details]');
            const title = dialog.querySelector('[data-report-detail-title]');
            const count = dialog.querySelector('[data-report-detail-count]');
            const error = dialog.querySelector('[data-report-detail-error]');
            const loading = dialog.querySelector('[data-report-detail-loading]');
            const empty = dialog.querySelector('[data-report-detail-empty]');
            const tableWrap = dialog.querySelector('[data-report-detail-table-wrap]');
            const tableHead = dialog.querySelector('[data-report-detail-head]');
            const tableBody = dialog.querySelector('[data-report-detail-body]');
            const pageLabel = dialog.querySelector('[data-report-detail-page]');
            const previous = dialog.querySelector('[data-report-detail-previous]');
            const next = dialog.querySelector('[data-report-detail-next]');
            const close = dialog.querySelector('[data-report-detail-close]');
            const revenueFilters = dialog.querySelector('[data-report-revenue-filters]');
            const revenueBranch = dialog.querySelector('[data-report-revenue-branch]');
            const revenueSearch = dialog.querySelector('[data-report-revenue-patient]');
            const revenueSearchForm = dialog.querySelector('[data-report-revenue-search-form]');
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
                if (payload.revenue_total !== undefined) {
                    count.textContent += ` · ${payload.revenue_total} total collected`;
                }
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
                const url = new URL(cards.dataset.reportDetailsUrl, window.location.origin);
                url.searchParams.set('metric', selectedMetric);
                url.searchParams.set('from', cards.dataset.reportFrom);
                url.searchParams.set('to', cards.dataset.reportTo);
                url.searchParams.set('page', String(page));
                if (cards.dataset.reportBranch) {
                    url.searchParams.set('branch_id', cards.dataset.reportBranch);
                }
                if (selectedMetric === 'revenue') {
                    url.searchParams.delete('branch_id');
                    if (revenueBranch.value) {
                        url.searchParams.set('branch_id', revenueBranch.value);
                    }
                    if (revenueSearch.value.trim()) {
                        url.searchParams.set('patient_search', revenueSearch.value.trim());
                    }
                }

                try {
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error('Could not load these report records. Please close the popup and try again.');
                    }
                    renderDetails(await response.json());
                } catch (requestError) {
                    error.textContent = requestError instanceof Error
                        ? requestError.message
                        : 'Could not load these report records. Please close the popup and try again.';
                    setVisible(error, true);
                } finally {
                    setVisible(loading, false);
                }
            };

            document.querySelectorAll('[data-report-detail]').forEach((button) => {
                button.addEventListener('click', () => {
                    selectedMetric = button.dataset.reportDetail;
                    const isRevenue = selectedMetric === 'revenue';
                    revenueFilters.classList.toggle('hidden', !isRevenue);
                    if (isRevenue) {
                        revenueBranch.value = cards.dataset.reportBranch || '';
                        revenueSearch.value = '';
                    }
                    dialog.showModal();
                    loadPage(1);
                });
            });
            revenueBranch.addEventListener('change', () => loadPage(1));
            revenueSearchForm.addEventListener('submit', (event) => {
                event.preventDefault();
                loadPage(1);
            });
            close.addEventListener('click', () => dialog.close());
            previous.addEventListener('click', () => loadPage(currentPage - 1));
            next.addEventListener('click', () => loadPage(currentPage + 1));
        })();
    </script>
@endsection
