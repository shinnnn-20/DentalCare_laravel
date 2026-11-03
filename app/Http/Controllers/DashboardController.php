<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\User;
use App\Services\ClinicRevenueReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClinicRevenueReport $revenueReport): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === 'patient') {
            $patient = $user->patient()->firstOrFail();

            return view('dashboard.patient', [
                'nextAppointment' => $patient->appointments()
                    ->with(['service', 'services'])
                    ->where('starts_at', '>=', now())
                    ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'no_show', 'billed'])
                    ->orderBy('starts_at')
                    ->first(),
                'recentRecords' => $patient->dentalRecords()->latest()->limit(5)->get(),
                'unpaidBills' => $patient->appointments()->with('bill')
                    ->whereHas('bill', fn ($query) => $query->whereIn('payment_status', ['unpaid', 'partially_paid']))
                    ->get()->pluck('bill')->filter(),
            ]);
        }

        $today = today(config('clinic.timezone'));
        $isAdmin = $user->role === 'admin';
        $canViewRevenue = $user->hasRole('admin', 'doctor');
        $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
        $hasAssignedBranch = $user->hasRole('staff', 'doctor') && $user->branch_id !== null;

        if ($hasAssignedBranch) {
            $branches = $branches->where('id', $user->branch_id)->values();
        }

        $filters = $request->validate([
            'branch_id' => ['nullable', Rule::in([
                ...($hasAssignedBranch ? [] : ['unassigned']),
                ...$branches->map(fn (Branch $branch): string => (string) $branch->id)->all(),
            ])],
            ...($isAdmin ? ['statistics_period' => ['nullable', 'in:week,month,year']] : []),
        ]);
        $branchId = $hasAssignedBranch
            ? (string) $user->branch_id
            : ($filters['branch_id'] ?? null);
        $selectedBranchName = match ($branchId) {
            'unassigned' => 'Unassigned appointments',
            null => 'All branches',
            default => $branches->firstWhere('id', (int) $branchId)?->name ?? 'All branches',
        };
        $appointmentsForBranch = fn (): Builder => $this->applyAppointmentBranchFilter(
            Appointment::query(),
            $branchId,
        );
        $patientsForBranch = Patient::query();
        if ($branchId !== null) {
            $patientsForBranch->whereHas('appointments', fn (Builder $query): Builder => $this->applyAppointmentBranchFilter(
                $query,
                $branchId,
            ));
        }
        $paymentsForBranch = fn (): Builder => $this->applyPaymentBranchFilter(Payment::query(), $branchId);
        $queueEntriesForBranch = fn (): Builder => QueueEntry::query()->whereHas(
            'appointment',
            fn (Builder $query): Builder => $this->applyAppointmentBranchFilter($query, $branchId),
        );
        [$todayStartUtc, $todayEndUtc] = $revenueReport->utcRange(
            $today->copy()->startOfDay(),
            $today->copy()->endOfDay(),
        );
        $todayPayments = fn (): Builder => $paymentsForBranch()
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc]);
        $hasTestPaymentsToday = $todayPayments()
            ->where('provider', 'paymongo')
            ->where('is_test', true)
            ->exists();
        $appointments = $appointmentsForBranch()
            ->with(['patient.user', 'service', 'services', 'queueEntry'])
            ->whereDate('starts_at', $today)
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $activeStaff = User::query()
            ->whereIn('role', ['staff', 'doctor'])
            ->where('is_active', true);
        $metrics = [];
        if ($user->hasRole('admin', 'staff')) {
            $metrics[] = ['Patients', number_format($patientsForBranch->count()), $branchId === null ? 'Registered patient accounts' : 'Patients seen at this branch', 'patients'];
        }
        $metrics = [
            ...$metrics,
            ["Today's appointments", number_format($appointmentsForBranch()->whereDate('starts_at', $today)->count()), 'Online and walk-in', 'today_appointments'],
            ['Pending approval', number_format($appointmentsForBranch()->where('status', 'pending')->count()), 'Online requests', 'pending_approval'],
            ['Patients in queue', number_format($queueEntriesForBranch()->whereDate('queue_date', $today)->whereIn('status', ['waiting', 'called'])->count()), 'Waiting or called today', 'queue'],
            ['Completed today', number_format($appointmentsForBranch()->whereDate('starts_at', $today)->where('status', 'completed')->count()), 'Appointments completed', 'completed_today'],
        ];
        if ($canViewRevenue) {
            $metrics[] = ["Today's revenue", '₱'.number_format((float) $todayPayments()->sum('applied_amount'), 2), $hasTestPaymentsToday ? 'Includes PayMongo TEST MODE payments' : 'Payments applied today', 'today_revenue'];
        }
        if ($user->hasRole('admin', 'staff')) {
            $metrics[] = ['Unpaid bills', number_format($this->outstandingBillsForBranch($branchId)->count()), 'Including partial balances', 'unpaid_bills'];
        }
        if ($isAdmin) {
            $metrics[] = ['Active staff', number_format($activeStaff->count()), 'Clinic-wide staff and doctors', 'active_staff'];
        }

        $statistics = [];
        if ($isAdmin) {
            $statistics = $this->dashboardStatistics(
                $filters['statistics_period'] ?? 'week',
                $today,
                $branchId,
                $revenueReport,
            );
        }

        return view('dashboard.clinic', [
            'metrics' => $metrics,
            'isAdmin' => $isAdmin,
            'appointments' => $appointments,
            'statistics' => $statistics,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'selectedBranchName' => $selectedBranchName,
        ]);
    }

    public function details(Request $request, ClinicRevenueReport $revenueReport): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin', 'staff', 'doctor'), 403);

        $branches = Branch::query()->get(['id']);
        $hasAssignedBranch = $user->hasRole('staff', 'doctor') && $user->branch_id !== null;

        if ($hasAssignedBranch) {
            $branches = $branches->where('id', $user->branch_id)->values();
        }

        $filters = $request->validate([
            'metric' => ['required', Rule::in([
                'patients',
                'today_appointments',
                'pending_approval',
                'queue',
                'completed_today',
                'today_revenue',
                'unpaid_bills',
                'active_staff',
            ])],
            'branch_id' => ['nullable', Rule::in([
                ...($hasAssignedBranch ? [] : ['unassigned']),
                ...$branches->map(fn (Branch $branch): string => (string) $branch->id)->all(),
            ])],
            'appointment_branch_id' => ['sometimes', 'nullable', Rule::in([
                'all',
                ...($hasAssignedBranch ? [] : ['unassigned']),
                ...$branches->map(fn (Branch $branch): string => (string) $branch->id)->all(),
            ])],
            'appointment_status' => ['sometimes', 'nullable', Rule::in([
                'all',
                'pending',
                'waiting',
                'approved',
                'checked_in',
                'in_consultation',
                'billed',
                'completed',
                'rejected',
                'cancelled',
                'no_show',
            ])],
            ...($user->role === 'admin' ? ['statistics_period' => ['nullable', 'in:week,month,year']] : []),
            'patient_search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($user->role === 'doctor' && in_array($filters['metric'], ['patients', 'unpaid_bills', 'active_staff'], true)) {
            abort(403);
        }
        if ($user->role === 'staff' && $filters['metric'] === 'active_staff') {
            abort(403);
        }
        if ($filters['metric'] === 'today_revenue' && ! $user->hasRole('admin', 'doctor')) {
            abort(403);
        }

        $branchId = $hasAssignedBranch
            ? (string) $user->branch_id
            : ($filters['branch_id'] ?? null);
        $today = today(config('clinic.timezone'));
        $appointmentBranchId = $hasAssignedBranch
            ? (string) $user->branch_id
            : (array_key_exists('appointment_branch_id', $filters)
                ? $filters['appointment_branch_id']
                : $branchId);
        $appointmentBranchId = $appointmentBranchId === 'all' ? null : $appointmentBranchId;
        $appointmentStatus = $filters['appointment_status'] ?? 'all';
        $todayAppointments = $this->applyAppointmentBranchFilter(Appointment::query(), $appointmentBranchId)
            ->whereDate('starts_at', $today)
            ->when(
                $appointmentStatus !== 'all',
                fn (Builder $query): Builder => $query->where('status', $appointmentStatus),
            )
            ->with(['patient.user', 'branch', 'service', 'services'])
            ->orderBy('starts_at');

        return match ($filters['metric']) {
            'patients' => $this->patientDetails($branchId, $filters['page'] ?? 1),
            'today_appointments' => $this->appointmentDetails(
                'Today’s appointments',
                $todayAppointments,
                $filters['page'] ?? 1,
            ),
            'pending_approval' => $this->appointmentDetails(
                'Pending approval',
                $this->applyAppointmentBranchFilter(Appointment::query(), $branchId)
                    ->where('status', 'pending')
                    ->with(['patient.user', 'branch', 'service', 'services'])
                    ->orderBy('starts_at'),
                $filters['page'] ?? 1,
            ),
            'queue' => $this->queueDetails($branchId, $today, $filters['page'] ?? 1),
            'completed_today' => $this->appointmentDetails(
                'Completed today',
                $this->applyAppointmentBranchFilter(Appointment::query(), $branchId)
                    ->whereDate('starts_at', $today)
                    ->where('status', 'completed')
                    ->with(['patient.user', 'branch', 'service', 'services'])
                    ->orderBy('starts_at'),
                $filters['page'] ?? 1,
                true,
            ),
            'today_revenue' => $this->revenueDetails(
                $branchId,
                $today,
                $filters['page'] ?? 1,
                $revenueReport,
                isset($filters['patient_search']) ? trim($filters['patient_search']) : null,
            ),
            'unpaid_bills' => $this->billDetails($branchId, $filters['page'] ?? 1),
            'active_staff' => $this->staffDetails($filters['page'] ?? 1),
        };
    }

    private function patientDetails(?string $branchId, int $page): JsonResponse
    {
        $query = Patient::query()
            ->when($branchId !== null, fn (Builder $query): Builder => $query->whereHas(
                'appointments',
                fn (Builder $appointmentsQuery): Builder => $this->applyAppointmentBranchFilter($appointmentsQuery, $branchId),
            ))
            ->with('user')
            ->orderBy('id');
        $paginator = $query->paginate(20, ['*'], 'page', $page);

        return $this->detailsResponse(
            'Patients',
            [
                'name' => 'Patient name',
                'patient_id' => 'Patient ID',
                'email' => 'Email',
                'phone' => 'Contact number',
                'registered_at' => 'Registration date',
            ],
            $paginator,
            static fn (Patient $patient): array => [
                'name' => $patient->user->name,
                'patient_id' => $patient->patient_number,
                'email' => $patient->user->email,
                'phone' => $patient->user->phone ?: '—',
                'registered_at' => $patient->created_at->timezone(config('clinic.timezone'))->format('M j, Y'),
            ],
        );
    }

    private function appointmentDetails(string $title, Builder $query, int $page, bool $includeDoctor = false): JsonResponse
    {
        $columns = [
            'name' => 'Patient name',
            'patient_id' => 'Patient ID',
            'date_time' => 'Appointment date and time',
            'branch' => 'Branch',
            'services' => 'Services / treatments',
            'type' => 'Appointment type',
        ];
        if ($includeDoctor) {
            $columns['doctor'] = 'Assigned doctor';
        }
        $columns['status'] = $includeDoctor ? 'Completion status' : 'Status';
        $paginator = $query->paginate(20, ['*'], 'page', $page);

        return $this->detailsResponse(
            $title,
            $columns,
            $paginator,
            static function (Appointment $appointment) use ($includeDoctor): array {
                $record = [
                    'name' => $appointment->patient->user->name,
                    'patient_id' => $appointment->patient->patient_number,
                    'date_time' => $appointment->starts_at->format('M j, Y · g:i A'),
                    'branch' => $appointment->branch?->name ?? 'Unassigned',
                    'services' => $appointment->treatmentNames(),
                    'type' => $appointment->type === 'walk_in' ? 'Walk-in' : 'Online',
                ];
                if ($includeDoctor) {
                    $record['doctor'] = 'Not recorded';
                }
                $record['status'] = self::appointmentStatusLabel($appointment->status);

                return $record;
            },
        );
    }

    private static function appointmentStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'waiting' => 'Waiting',
            'approved' => 'Approved',
            'checked_in' => 'Checked In',
            'in_consultation' => 'In Consultation',
            'billed' => 'Billed',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            default => str_replace('_', ' ', ucfirst($status)),
        };
    }

    private function queueDetails(?string $branchId, Carbon $today, int $page): JsonResponse
    {
        $query = QueueEntry::query()
            ->whereDate('queue_date', $today)
            ->whereIn('status', ['waiting', 'called'])
            ->whereHas('appointment', fn (Builder $appointmentQuery): Builder => $this->applyAppointmentBranchFilter(
                $appointmentQuery,
                $branchId,
            ))
            ->with(['appointment.patient.user', 'appointment.branch'])
            ->orderBy('queue_number');
        $paginator = $query->paginate(20, ['*'], 'page', $page);

        return $this->detailsResponse(
            'Patients in queue',
            [
                'queue_number' => 'Queue number',
                'name' => 'Patient name',
                'patient_id' => 'Patient ID',
                'appointment_time' => 'Appointment time',
                'branch' => 'Branch',
                'type' => 'Appointment type',
                'status' => 'Queue status',
            ],
            $paginator,
            static fn (QueueEntry $entry): array => [
                'queue_number' => $entry->queue_number,
                'name' => $entry->appointment->patient->user->name,
                'patient_id' => $entry->appointment->patient->patient_number,
                'appointment_time' => $entry->appointment->starts_at->format('g:i A'),
                'branch' => $entry->appointment->branch?->name ?? 'Unassigned',
                'type' => $entry->appointment->type === 'walk_in' ? 'Walk-in' : 'Online',
                'status' => ucfirst($entry->status),
            ],
        );
    }

    private function revenueDetails(
        ?string $branchId,
        Carbon $today,
        int $page,
        ClinicRevenueReport $revenueReport,
        ?string $patientSearch,
    ): JsonResponse {
        [$todayStartUtc, $todayEndUtc] = $revenueReport->utcRange(
            $today->copy()->startOfDay(),
            $today->copy()->endOfDay(),
        );
        $payments = $revenueReport->filterPaymentsByPatient(
            $this->applyPaymentBranchFilter(Payment::query(), $branchId)
                ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc]),
            $patientSearch,
        );
        $totalAmount = (float) (clone $payments)->sum('applied_amount');
        $paginator = $payments
            ->with(['bill.patient.user', 'bill.appointment.branch'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(20, ['*'], 'page', $page);

        return $this->detailsResponse(
            "Today's revenue",
            [
                'date_time' => 'Recorded date and time',
                'name' => 'Patient name',
                'patient_id' => 'Patient ID',
                'bill_number' => 'Bill number',
                'branch' => 'Branch',
                'method' => 'Payment method',
                'amount' => 'Applied amount',
            ],
            $paginator,
            static fn (Payment $payment): array => [
                'date_time' => $payment->created_at->timezone(config('clinic.timezone'))->format('M j, Y · g:i A'),
                'name' => $payment->bill->patient->user->name,
                'patient_id' => $payment->bill->patient->patient_number,
                'bill_number' => $payment->bill->bill_number,
                'branch' => $payment->bill->appointment?->branch?->name ?? 'Unassigned',
                'method' => strtoupper($payment->method).($payment->provider === 'paymongo' && $payment->is_test ? ' · PayMongo TEST MODE' : ''),
                'amount' => '₱'.number_format((float) $payment->applied_amount, 2),
            ],
            ['revenue_total' => '₱'.number_format($totalAmount, 2)],
        );
    }

    private function billDetails(?string $branchId, int $page): JsonResponse
    {
        $query = $this->outstandingBillsForBranch($branchId)
            ->with(['patient.user', 'appointment.branch'])
            ->withSum('payments as amount_paid', 'applied_amount')
            ->orderBy('id');
        $paginator = $query->paginate(20, ['*'], 'page', $page);

        return $this->detailsResponse(
            'Unpaid bills',
            [
                'name' => 'Patient name',
                'patient_id' => 'Patient ID',
                'bill_number' => 'Bill number',
                'branch' => 'Branch',
                'bill_date' => 'Bill date',
                'total' => 'Total amount',
                'paid' => 'Amount paid',
                'balance' => 'Remaining balance',
                'status' => 'Payment status',
            ],
            $paginator,
            static function (Bill $bill): array {
                $total = (float) $bill->total;
                $paid = (float) ($bill->amount_paid ?? 0);

                return [
                    'name' => $bill->patient->user->name,
                    'patient_id' => $bill->patient->patient_number,
                    'bill_number' => $bill->bill_number,
                    'branch' => $bill->appointment?->branch?->name ?? 'Unassigned',
                    'bill_date' => $bill->created_at->timezone(config('clinic.timezone'))->format('M j, Y'),
                    'total' => number_format($total, 2),
                    'paid' => number_format($paid, 2),
                    'balance' => number_format(max(0, $total - $paid), 2),
                    'status' => $paid <= 0 ? 'Unpaid' : 'Partially paid',
                ];
            },
        );
    }

    private function staffDetails(int $page): JsonResponse
    {
        $paginator = User::query()
            ->with('branch')
            ->whereIn('role', ['staff', 'doctor'])
            ->where('is_active', true)
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20, ['*'], 'page', $page);

        return $this->detailsResponse(
            'Active staff',
            [
                'name' => 'Name',
                'role' => 'Account role',
                'email' => 'Email',
                'branch' => 'Assigned branch',
                'status' => 'Account status',
            ],
            $paginator,
            static fn (User $staff): array => [
                'name' => $staff->name,
                'role' => ucfirst($staff->role),
                'email' => $staff->email,
                'branch' => $staff->branch?->name ?? 'No branch assigned',
                'status' => $staff->is_active ? 'Active' : 'Inactive',
            ],
        );
    }

    /**
     * @param  array<string, string>  $columns
     * @param  callable(Model): array<string, string>  $mapRecord
     */
    private function detailsResponse(
        string $title,
        array $columns,
        LengthAwarePaginator $paginator,
        callable $mapRecord,
        array $metadata = [],
    ): JsonResponse {
        return response()->json([
            'title' => $title,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'columns' => $columns,
            'records' => $paginator->getCollection()->map($mapRecord)->values()->all(),
            ...$metadata,
        ]);
    }

    /**
     * @return array{
     *     period:string,
     *     rangeLabel:string,
     *     timeline:array<int, array{label:string, appointments:int, revenue:float}>,
     *     statuses:array<int, array{label:string, count:int, percentage:float}>,
     *     appointmentMaximum:int,
     *     revenueMaximum:float
     * }
     */
    private function dashboardStatistics(
        string $period,
        Carbon $today,
        ?string $branchId,
        ClinicRevenueReport $revenueReport,
    ): array {
        $usesMonths = $period === 'year';
        $rangeStart = $usesMonths
            ? $today->copy()->startOfMonth()->subMonths(11)
            : $today->copy()->startOfDay()->subDays($period === 'week' ? 6 : 29);
        $rangeEnd = $today->copy()->endOfDay();
        $bucketKey = static fn (Carbon $date): string => $usesMonths
            ? $date->format('Y-m')
            : $date->toDateString();
        $timeline = [];
        $revenueBuckets = [];
        $cursor = $rangeStart->copy();

        while ($cursor->lessThanOrEqualTo($today)) {
            $key = $bucketKey($cursor);
            $bucketStart = $usesMonths ? $cursor->copy()->startOfMonth() : $cursor->copy()->startOfDay();
            $bucketEnd = $usesMonths ? $cursor->copy()->endOfMonth() : $cursor->copy()->endOfDay();
            if ($bucketEnd->greaterThan($today->copy()->endOfDay())) {
                $bucketEnd = $today->copy()->endOfDay();
            }
            $timeline[$key] = [
                'label' => $usesMonths ? $cursor->format('M Y') : $cursor->format('D j'),
                'appointments' => 0,
                'revenue' => 0.0,
            ];
            $revenueBuckets[$key] = [
                'start' => $bucketStart,
                'end' => $bucketEnd,
            ];
            $usesMonths ? $cursor->addMonth() : $cursor->addDay();
        }

        $appointmentRows = $this->applyAppointmentBranchFilter(Appointment::query(), $branchId)
            ->selectRaw('DATE(starts_at) as activity_date, COUNT(*) as total')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->groupBy('activity_date')
            ->get();

        foreach ($appointmentRows as $row) {
            $key = $bucketKey(Carbon::parse($row->activity_date, config('clinic.timezone')));
            if (isset($timeline[$key])) {
                $timeline[$key]['appointments'] = (int) $row->total;
            }
        }

        $revenueByBucket = $revenueReport->totalsByClinicBuckets(
            $this->applyPaymentBranchFilter(Payment::query(), $branchId),
            $revenueBuckets,
        );
        foreach ($revenueByBucket as $key => $total) {
            if (isset($timeline[$key])) {
                $timeline[$key]['revenue'] = $total;
            }
        }

        $statusCounts = $this->applyAppointmentBranchFilter(Appointment::query(), $branchId)
            ->selectRaw('status, COUNT(*) as total')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $appointmentTotal = max(1, (int) $statusCounts->sum('total'));
        $statuses = $statusCounts->map(fn (object $row): array => [
            'label' => str_replace('_', ' ', ucfirst($row->status)),
            'count' => (int) $row->total,
            'percentage' => (float) $row->total / $appointmentTotal * 100,
        ])->all();
        $timeline = array_values($timeline);

        return [
            'period' => $period,
            'rangeLabel' => match ($period) {
                'month' => 'Last 30 days',
                'year' => 'Last 12 months',
                default => 'Last 7 days',
            },
            'timeline' => $timeline,
            'statuses' => $statuses,
            'appointmentMaximum' => max(1, ...array_column($timeline, 'appointments')),
            'revenueMaximum' => max(1.0, ...array_column($timeline, 'revenue')),
        ];
    }

    private function applyAppointmentBranchFilter(Builder $query, ?string $branchId): Builder
    {
        return match ($branchId) {
            'unassigned' => $query->whereNull('branch_id'),
            null => $query,
            default => $query->where('branch_id', $branchId),
        };
    }

    private function applyPaymentBranchFilter(Builder $query, ?string $branchId): Builder
    {
        if ($branchId !== null) {
            $query->whereHas('bill.appointment', fn (Builder $appointmentQuery): Builder => $this->applyAppointmentBranchFilter(
                $appointmentQuery,
                $branchId,
            ));
        }

        return $query;
    }

    private function billsForBranch(?string $branchId): Builder
    {
        $query = Bill::query();
        if ($branchId !== null) {
            $query->whereHas('appointment', fn (Builder $appointmentQuery): Builder => $this->applyAppointmentBranchFilter(
                $appointmentQuery,
                $branchId,
            ));
        }

        return $query;
    }

    private function outstandingBillsForBranch(?string $branchId): Builder
    {
        return $this->billsForBranch($branchId)
            ->whereRaw(
                'bills.total > (SELECT COALESCE(SUM(payments.applied_amount), 0) FROM payments WHERE payments.bill_id = bills.id)',
            );
    }
}
