<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentAvailabilityBlock;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\RfidCard;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use App\Services\ClinicRevenueReport;
use App\Services\SmsNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClinicWorkflowController extends Controller
{
    public function queue(Request $request): View
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $hasAssignedBranch = $user->hasRole('staff', 'doctor') && $user->branch_id !== null;

        return view('queue.index', [
            'queueEntries' => QueueEntry::query()
                ->with(['appointment.patient.user', 'appointment.service', 'appointment.services', 'appointment.dentalRecord'])
                ->when($hasAssignedBranch, fn (Builder $query): Builder => $query->whereHas(
                    'appointment',
                    fn (Builder $appointmentQuery): Builder => $appointmentQuery->where('branch_id', $user->branch_id),
                ))
                ->whereDate('queue_date', today(config('clinic.timezone')))
                ->whereIn('status', ['waiting', 'called', 'in_consultation'])
                ->orderBy('id')
                ->get(),
            'checkInAppointments' => Appointment::query()
                ->with(['patient.user', 'service', 'services'])
                ->when($hasAssignedBranch, fn (Builder $query): Builder => $query->where('branch_id', $user->branch_id))
                ->whereDate('starts_at', '>=', today(config('clinic.timezone')))
                ->where('status', 'approved')
                ->whereDoesntHave('queueEntry')
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    public function createRecord(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $this->authorizeAssignedBranch($request, $appointment);

        if ($appointment->dentalRecord()->exists()) {
            return $this->redirectToAppointment($request, $appointment, 'A dental record already exists for this appointment.');
        }

        abort_unless(in_array($appointment->status, ['in_consultation', 'completed'], true), 422);

        return view('records.create', [
            'appointment' => $appointment->load(['patient.user', 'service', 'services']),
            'appointmentListFilters' => $this->appointmentListFilters($request),
        ]);
    }

    public function storeRecord(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $this->authorizeAssignedBranch($request, $appointment);

        if ($appointment->dentalRecord()->exists()) {
            return $this->redirectToAppointment($request, $appointment, 'A dental record already exists for this appointment.');
        }

        abort_unless(in_array($appointment->status, ['in_consultation', 'completed'], true), 422);

        $data = $request->validate([
            'diagnosis' => ['required', 'string', 'max:5000'],
            'treatment' => ['required', 'string', 'max:5000'],
            'prescription' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $record = DentalRecord::create([
            ...$data,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'created_by' => $request->user()->id,
        ]);
        $auditTrail->record($request->user(), 'dental_record_created', 'dental_records', $record);
        $notifications->notifyClinicStaff(
            $request->user(),
            'patient.dental_record_created',
            'Dental Record Created',
            "{$request->user()->name} created a dental record for {$appointment->patient->user->name}.",
            route('clinic.patients.show', $appointment->patient_id, false),
            'dental_record',
            $record->id,
        );

        return $this->redirectToAppointment($request, $appointment, 'Dental record saved.');
    }

    /**
     * @return array<string, int|string>
     */
    private function appointmentListFilters(Request $request): array
    {
        $filters = [];
        $branchId = $request->query('branch_id');

        if (is_string($branchId) && $branchId === 'unassigned') {
            $filters['branch_id'] = $branchId;
        } elseif (is_string($branchId) && ctype_digit($branchId) && Branch::query()->whereKey($branchId)->exists()) {
            $filters['branch_id'] = $branchId;
        }

        $search = $request->query('search');

        if (is_string($search) && mb_strlen($search) <= 100) {
            $filters['search'] = $search;
        }

        return $filters;
    }

    private function redirectToAppointment(Request $request, Appointment $appointment, string $message): RedirectResponse
    {
        if ($request->user()->hasRole('doctor')) {
            return redirect()->route('clinic.queue.index')->with('status', $message);
        }

        $query = [
            ...$this->appointmentListFilters($request),
            'date' => $appointment->starts_at->toDateString(),
            'status' => $appointment->status,
            'appointment_id' => $appointment->id,
        ];

        return redirect()->to(route('clinic.appointment-records', $query).'#appointment-'.$appointment->id)
            ->with('status', $message);
    }

    public function createBill(Request $request, Appointment $appointment): View|RedirectResponse
    {
        $this->authorizeAssignedBranch($request, $appointment);

        $existingBill = $appointment->bill()->first();

        if ($existingBill !== null) {
            return $this->redirectToExistingBill($existingBill);
        }

        abort_unless($appointment->status === 'in_consultation', 422);
        abort_if($appointment->dentalRecord()->doesntExist(), 422, 'A dental record is required before billing.');
        $appointment->load(['patient.user', 'services', 'service']);

        return view('billing.create', [
            'appointment' => $appointment,
        ]);
    }

    public function storeBill(
        Request $request,
        Appointment $appointment,
        AppointmentCompletionService $appointmentCompletion,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $this->authorizeAssignedBranch($request, $appointment);

        $existingBill = $appointment->bill()->first();

        if ($existingBill !== null) {
            return $this->redirectToExistingBill($existingBill);
        }

        abort_unless($appointment->status === 'in_consultation', 422);
        abort_if($appointment->dentalRecord()->doesntExist(), 422, 'A dental record is required before billing.');
        $appointment->load(['service', 'services', 'patient.user']);

        $services = $appointment->services->isNotEmpty()
            ? $appointment->services
            : ($appointment->service ? collect([$appointment->service]) : collect());

        abort_if($services->isEmpty(), 422, 'This appointment has no treatments associated with it.');

        $finalPrice = $request->input('final_price');
        if (is_string($finalPrice)
            && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $finalPrice) === 1) {
            $request->merge(['final_price' => str_replace(',', '', $finalPrice)]);
        }

        $itemsInput = $request->input('items');
        if (is_array($itemsInput)) {
            foreach ($itemsInput as $key => $itemData) {
                if (isset($itemData['final_price']) && is_string($itemData['final_price'])
                    && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $itemData['final_price']) === 1) {
                    $itemsInput[$key]['final_price'] = str_replace(',', '', $itemData['final_price']);
                }
            }
            $request->merge(['items' => $itemsInput]);
        }

        $rules = [
            'discount_value' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
        ];

        if ($services->count() === 1) {
            $singleService = $services->first();
            if ($singleService->requires_price_confirmation) {
                $rules['final_price'] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'];
                $rules['confirm_final_price'] = ['accepted'];
            } else {
                $rules['final_price'] = ['prohibited'];
                $rules['confirm_final_price'] = ['prohibited'];
            }
        } else {
            foreach ($services as $service) {
                if ($service->requires_price_confirmation) {
                    $rules["items.{$service->id}.final_price"] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'];
                    $rules["items.{$service->id}.confirm_final_price"] = ['accepted'];
                }
            }
        }

        $data = $request->validate($rules);

        $itemRows = [];
        $subtotalCents = 0;

        foreach ($services as $service) {
            if ($service->requires_price_confirmation) {
                $priceValue = $services->count() === 1
                    ? $data['final_price']
                    : ($data['items'][$service->id]['final_price'] ?? $data['final_price'] ?? null);
            } else {
                $priceValue = $service->price;
            }

            $itemCents = (int) round((float) $priceValue * 100);
            $subtotalCents += $itemCents;

            $itemRows[] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'quantity' => 1,
                'unit_price' => number_format($itemCents / 100, 2, '.', ''),
                'subtotal' => number_format($itemCents / 100, 2, '.', ''),
            ];
        }

        $discountBasisPoints = (int) round((float) $data['discount_value'] * 100);
        $discountCents = intdiv($subtotalCents * $discountBasisPoints + 5000, 10000);

        $bill = DB::transaction(function () use ($appointment, $request, $subtotalCents, $discountBasisPoints, $discountCents, $itemRows, $appointmentCompletion): Bill {
            $totalCents = $subtotalCents - $discountCents;
            $bill = Bill::create([
                'bill_number' => 'B-PENDING-'.Str::uuid(),
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'created_by' => $request->user()->id,
                'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                'discount_type' => 'percentage',
                'discount_value' => number_format($discountBasisPoints / 100, 2, '.', ''),
                'discount' => number_format($discountCents / 100, 2, '.', ''),
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'payment_status' => $totalCents === 0 ? 'paid' : 'unpaid',
            ]);
            $bill->update(['bill_number' => sprintf('B-%s-%06d', now()->format('Y'), $bill->id)]);

            foreach ($itemRows as $row) {
                $bill->items()->create($row);
            }

            $appointment->update(['status' => 'billed']);
            $appointmentCompletion->sync($bill);

            return $bill;
        });

        $auditTrail->record($request->user(), 'bill_created', 'billing', $bill);
        $notifications->notifyClinicStaff(
            $request->user(),
            'billing.bill_created',
            'Bill Created',
            "{$request->user()->name} created bill {$bill->bill_number} for {$appointment->patient->user->name}.",
            route('clinic.billing.index', ['bill' => $bill->id], false).'#bill-'.$bill->id,
            'bill',
            $bill->id,
        );

        return redirect()->route('clinic.billing.index')->with('status', 'Bill created.');
    }

    private function redirectToExistingBill(Bill $bill): RedirectResponse
    {
        $billingUrl = route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id;

        return redirect()->to($billingUrl)->with('status', 'A bill already exists for this appointment.');
    }

    public function billing(Request $request): View
    {
        $user = $request->user();
        $isBranchDoctor = $user->hasRole('doctor');
        abort_if($isBranchDoctor && $user->branch_id === null, 403);
        $filters = $request->validate([
            'bill' => ['nullable', 'integer'],
        ]);

        return view('billing.index', [
            'bills' => Bill::query()
                ->with(['creator', 'patient.user', 'appointment', 'items', 'payments.receipt', 'payMongoBillCheckouts'])
                ->when(
                    $user->hasRole('staff', 'doctor') && $user->branch_id !== null,
                    fn (Builder $query): Builder => $query->whereHas(
                        'appointment',
                        fn (Builder $appointmentsQuery): Builder => $appointmentsQuery->where('branch_id', $user->branch_id),
                    ),
                )
                ->when(
                    $filters['bill'] ?? null,
                    fn ($query, $billId) => $query->orderByRaw('CASE WHEN bills.id = ? THEN 0 ELSE 1 END', [$billId]),
                )
                ->latest()
                ->paginate(20),
            'selectedBillId' => isset($filters['bill']) ? (int) $filters['bill'] : null,
            'paymongoTestCheckoutAvailable' => $this->paymongoTestCheckoutAvailable(),
            'isReadOnly' => false,
        ]);
    }

    public function records(Request $request): View
    {
        $patient = $request->user()->patient()->firstOrFail();

        return view('records.index', [
            'records' => $patient->dentalRecords()
                ->with(['creator', 'appointment.service', 'appointment.services'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function patientBilling(Request $request): View
    {
        $patient = $request->user()->patient()->firstOrFail();

        return view('billing.patient', [
            'bills' => Bill::query()
                ->with(['appointment', 'items', 'payments.receipt'])
                ->whereBelongsTo($patient)
                ->latest()
                ->paginate(20),
        ]);
    }

    private function paymongoTestCheckoutAvailable(): bool
    {
        $secretKey = config('services.paymongo.secret');
        $baseUrl = config('services.paymongo.base_url');
        $webhookSecret = config('services.paymongo.webhook_secret');

        return (app()->isLocal() || (app()->environment('testing') && app()->runningUnitTests()))
            && is_string($secretKey)
            && str_starts_with($secretKey, 'sk_test_')
            && $baseUrl === 'https://api.paymongo.com/v2'
            && is_string($webhookSecret)
            && $webhookSecret !== '';
    }

    private function authorizeAssignedBranch(Request $request, Appointment $appointment): void
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);

        if ($user->hasRole('admin') || $user->branch_id === null) {
            return;
        }

        abort_unless((int) $appointment->branch_id === (int) $user->branch_id, 404);
    }

    public function patientNotifications(Request $request): View
    {
        $patient = $request->user()->patient()->firstOrFail();

        return view('logs.patient-notifications', [
            'logs' => SmsLog::query()
                ->whereBelongsTo($patient)
                ->whereIn('type', SmsNotifier::ALLOWED_TYPES)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function reports(Request $request, ClinicRevenueReport $revenueReport): View
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $hasAssignedBranch = $user->role === 'doctor' && $user->branch_id !== null;
        $branches = Branch::query()
            ->when($hasAssignedBranch, fn (Builder $query): Builder => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', Rule::in([
                ...($hasAssignedBranch ? [] : ['unassigned']),
                ...$branches->map(fn (object $branch): string => (string) $branch->id)->all(),
            ])],
        ]);
        $from = $filters['from'] ?? today(config('clinic.timezone'))->toDateString();
        $to = $filters['to'] ?? today(config('clinic.timezone'))->toDateString();
        $branchId = $hasAssignedBranch ? (string) $user->branch_id : ($filters['branch_id'] ?? null);
        $selectedBranchName = match ($branchId) {
            'unassigned' => 'Unassigned appointments',
            null => 'All branches',
            default => $branches->firstWhere('id', (int) $branchId)?->name ?? 'All branches',
        };
        $rangeStart = Carbon::parse($from, config('clinic.timezone'))->startOfDay();
        $rangeEnd = Carbon::parse($to, config('clinic.timezone'))->endOfDay();
        [$rangeStartUtc, $rangeEndUtc] = $revenueReport->utcRange($rangeStart, $rangeEnd);
        $rangeDays = $rangeStart->copy()->startOfDay()->diffInDays($rangeEnd->copy()->startOfDay()) + 1;
        $granularity = $rangeDays <= 31 ? 'day' : ($rangeDays <= 180 ? 'week' : 'month');
        $bucketKey = static function (Carbon $date) use ($granularity): string {
            return match ($granularity) {
                'week' => $date->copy()->startOfWeek()->toDateString(),
                'month' => $date->format('Y-m'),
                default => $date->toDateString(),
            };
        };
        $timeline = [];
        $revenueBuckets = [];
        $cursor = $rangeStart->copy();

        while ($cursor->lessThanOrEqualTo($rangeEnd)) {
            $key = $bucketKey($cursor);
            $bucketStart = match ($granularity) {
                'week' => $cursor->copy()->startOfWeek(),
                'month' => $cursor->copy()->startOfMonth(),
                default => $cursor->copy()->startOfDay(),
            };
            $bucketEnd = match ($granularity) {
                'week' => $cursor->copy()->endOfWeek(),
                'month' => $cursor->copy()->endOfMonth(),
                default => $cursor->copy()->endOfDay(),
            };
            if ($bucketStart->lessThan($rangeStart)) {
                $bucketStart = $rangeStart->copy();
            }
            if ($bucketEnd->greaterThan($rangeEnd)) {
                $bucketEnd = $rangeEnd->copy();
            }
            if (! isset($timeline[$key])) {
                $timeline[$key] = [
                    'label' => match ($granularity) {
                        'week' => $cursor->copy()->startOfWeek()->format('M j'),
                        'month' => $cursor->format('M Y'),
                        default => $cursor->format('M j'),
                    },
                    'appointments' => 0,
                    'revenue' => 0.0,
                ];
                $revenueBuckets[$key] = [
                    'start' => $bucketStart,
                    'end' => $bucketEnd,
                ];
            }
            $cursor = $bucketEnd->copy()->addSecond();
        }

        $appointmentsForBranch = fn (): Builder => $this->applyReportBranchFilter(
            Appointment::query(),
            $branchId,
        );
        $appointmentRows = $appointmentsForBranch()
            ->selectRaw('DATE(starts_at) as activity_date, COUNT(*) as total')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->groupBy('activity_date')
            ->get()
            ->map(static function (object $row): array {
                /** @var Model $appointment */
                $appointment = $row;

                return [
                    'activity_date' => (string) $appointment->getAttribute('activity_date'),
                    'total' => (int) $appointment->getAttribute('total'),
                ];
            });
        foreach ($appointmentRows as $row) {
            $key = $bucketKey(Carbon::parse($row['activity_date'], config('clinic.timezone')));
            if (isset($timeline[$key])) {
                $timeline[$key]['appointments'] += $row['total'];
            }
        }

        $revenueByBucket = $revenueReport->totalsByClinicBuckets(
            $this->applyReportPaymentBranchFilter(Payment::query(), $branchId),
            $revenueBuckets,
        );
        foreach ($revenueByBucket as $key => $total) {
            if (isset($timeline[$key])) {
                $timeline[$key]['revenue'] = $total;
            }
        }

        $statusCounts = $appointmentsForBranch()
            ->selectRaw('status, COUNT(*) as total')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $statusTotal = max(1, (int) $statusCounts->sum('total'));
        $statuses = $statusCounts->map(fn (object $row): array => [
            'label' => str_replace('_', ' ', ucfirst($row->status)),
            'count' => (int) $row->total,
            'percentage' => (float) $row->total / $statusTotal * 100,
        ])->all();
        $timeline = array_values($timeline);
        $appointmentMaximum = max(1, ...array_column($timeline, 'appointments'));
        $revenueMaximum = max(1.0, ...array_column($timeline, 'revenue'));

        $newPatientsQuery = Patient::query()->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc]);
        if ($branchId !== null) {
            if ($hasAssignedBranch) {
                $newPatientsQuery->forBranch((int) $branchId);
            } else {
                $newPatientsQuery->whereHas('appointments', fn (Builder $query): Builder => $this->applyReportBranchFilter(
                    $query,
                    $branchId,
                ));
            }
        }

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'appointments' => $appointmentsForBranch()->whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'walkIns' => $appointmentsForBranch()->where('type', 'walk_in')->whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'completed' => $appointmentsForBranch()->where('status', 'completed')->whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'cancelled' => $appointmentsForBranch()->where('status', 'cancelled')->whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'revenue' => $this->applyReportPaymentBranchFilter(Payment::query(), $branchId)
                ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
                ->sum('applied_amount'),
            'newPatients' => $newPatientsQuery->count(),
            'timeline' => $timeline,
            'statuses' => $statuses,
            'appointmentMaximum' => $appointmentMaximum,
            'revenueMaximum' => $revenueMaximum,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'selectedBranchName' => $selectedBranchName,
            'hasAssignedBranch' => $hasAssignedBranch,
        ]);
    }

    public function reportDetails(Request $request, ClinicRevenueReport $revenueReport): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $hasAssignedBranch = $user->role === 'doctor' && $user->branch_id !== null;
        $branches = Branch::query()
            ->when($hasAssignedBranch, fn (Builder $query): Builder => $query->whereKey($user->branch_id))
            ->get();
        $filters = $request->validate([
            'metric' => ['required', Rule::in([
                'appointments',
                'walk_ins',
                'completed',
                'cancelled',
                'new_patients',
                'revenue',
            ])],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', Rule::in([
                ...($hasAssignedBranch ? [] : ['unassigned']),
                ...$branches->map(fn (object $branch): string => (string) $branch->id)->all(),
            ])],
            'patient_search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $rangeStart = Carbon::parse($filters['from'], config('clinic.timezone'))->startOfDay();
        $rangeEnd = Carbon::parse($filters['to'], config('clinic.timezone'))->endOfDay();
        [$rangeStartUtc, $rangeEndUtc] = $revenueReport->utcRange($rangeStart, $rangeEnd);
        $branchId = $hasAssignedBranch ? (string) $user->branch_id : ($filters['branch_id'] ?? null);
        $page = $filters['page'] ?? 1;

        if ($filters['metric'] === 'new_patients') {
            $query = Patient::query()
                ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
                ->when(
                    $branchId !== null && $hasAssignedBranch,
                    fn (Builder $query): Builder => $query->forBranch((int) $branchId),
                )
                ->when(
                    $branchId !== null && ! $hasAssignedBranch,
                    fn (Builder $query): Builder => $query->whereHas(
                        'appointments',
                        fn (Builder $appointmentsQuery): Builder => $this->applyReportBranchFilter($appointmentsQuery, $branchId),
                    ),
                )
                ->with('user')
                ->orderBy('id');
            $paginator = $query->paginate(20, ['*'], 'page', $page);

            return $this->reportDetailsResponse(
                'New patient registrations',
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

        if ($filters['metric'] === 'revenue') {
            $query = $revenueReport->filterPaymentsByPatient(
                $this->applyReportPaymentBranchFilter(Payment::query(), $branchId)
                    ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc]),
                isset($filters['patient_search']) ? trim($filters['patient_search']) : null,
            );
            $totalAmount = (float) (clone $query)->sum('applied_amount');
            $query
                ->with(['bill.patient.user', 'bill.appointment.branch'])
                ->orderBy('created_at')
                ->orderBy('id');
            $paginator = $query->paginate(20, ['*'], 'page', $page);

            return $this->reportDetailsResponse(
                'Revenue collected',
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

        $query = $this->applyReportBranchFilter(Appointment::query(), $branchId)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->when($filters['metric'] === 'walk_ins', fn (Builder $query): Builder => $query->where('type', 'walk_in'))
            ->when($filters['metric'] === 'completed', fn (Builder $query): Builder => $query->where('status', 'completed'))
            ->when($filters['metric'] === 'cancelled', fn (Builder $query): Builder => $query->where('status', 'cancelled'))
            ->with(['patient.user', 'branch', 'service', 'services'])
            ->orderBy('starts_at')
            ->orderBy('id');
        $paginator = $query->paginate(20, ['*'], 'page', $page);
        $title = match ($filters['metric']) {
            'walk_ins' => 'Walk-in appointments',
            'completed' => 'Completed appointments',
            'cancelled' => 'Cancelled appointments',
            default => 'Appointments',
        };

        return $this->reportDetailsResponse(
            $title,
            [
                'name' => 'Patient name',
                'patient_id' => 'Patient ID',
                'date_time' => 'Appointment date and time',
                'branch' => 'Branch',
                'services' => 'Services / treatments',
                'type' => 'Appointment type',
                'status' => 'Status',
            ],
            $paginator,
            static fn (Appointment $appointment): array => [
                'name' => $appointment->patient->user->name,
                'patient_id' => $appointment->patient->patient_number,
                'date_time' => $appointment->starts_at->format('M j, Y · g:i A'),
                'branch' => $appointment->branch?->name ?? 'Unassigned',
                'services' => $appointment->treatmentNames(),
                'type' => $appointment->type === 'walk_in' ? 'Walk-in' : 'Online',
                'status' => str_replace('_', ' ', ucfirst($appointment->status)),
            ],
        );
    }

    /**
     * @param  array<string, string>  $columns
     * @param  callable(Model): array<string, string>  $mapRecord
     */
    private function reportDetailsResponse(
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyReportBranchFilter(Builder $query, ?string $branchId): Builder
    {
        return match ($branchId) {
            'unassigned' => $query->whereNull('branch_id'),
            null => $query,
            default => $query->where('branch_id', $branchId),
        };
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    private function applyReportPaymentBranchFilter(Builder $query, ?string $branchId): Builder
    {
        if ($branchId !== null) {
            $query->whereHas('bill.appointment', fn (Builder $appointmentQuery): Builder => $this->applyReportBranchFilter(
                $appointmentQuery,
                $branchId,
            ));
        }

        return $query;
    }

    public function auditLogs(Request $request): View
    {
        $user = $request->user();
        $isBranchDoctor = $user->hasRole('doctor');
        abort_if($isBranchDoctor && $user->branch_id === null, 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'in:newest,oldest'],
        ]);

        $scopedLogs = AuditLog::query();

        if ($isBranchDoctor) {
            $branchId = $user->branch_id;
            $scopedLogs->where(function (Builder $query) use ($branchId, $user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id)->whereNull('record_type');
                })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'Appointment')
                            ->whereIn('record_id', Appointment::query()->select('id')->where('branch_id', $branchId));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'Patient')
                            ->whereIn('record_id', Patient::query()
                                ->forBranch((int) $branchId)
                                ->select('patients.id'));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'Bill')
                            ->whereIn('record_id', Bill::query()
                                ->select('bills.id')
                                ->whereHas('appointment', fn (Builder $appointmentQuery): Builder => $appointmentQuery
                                    ->where('branch_id', $branchId)));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'Payment')
                            ->whereIn('record_id', Payment::query()
                                ->select('payments.id')
                                ->whereHas('bill.appointment', fn (Builder $appointmentQuery): Builder => $appointmentQuery
                                    ->where('branch_id', $branchId)));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'QueueEntry')
                            ->whereIn('record_id', QueueEntry::query()
                                ->select('queue_entries.id')
                                ->whereHas('appointment', fn (Builder $appointmentQuery): Builder => $appointmentQuery
                                    ->where('branch_id', $branchId)));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'RfidCard')
                            ->whereIn('record_id', RfidCard::query()
                                ->select('rfid_cards.id')
                                ->whereHas('patient.appointments', fn (Builder $appointmentQuery): Builder => $appointmentQuery
                                    ->where('branch_id', $branchId)));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'AppointmentAvailabilityBlock')
                            ->whereIn('record_id', AppointmentAvailabilityBlock::query()
                                ->select('id')
                                ->where('branch_id', $branchId));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'DentalRecord')
                            ->whereIn('record_id', DentalRecord::query()
                                ->select('id')
                                ->whereHas('appointment', fn (Builder $appointmentQuery): Builder => $appointmentQuery
                                    ->where('branch_id', $branchId)));
                    })
                    ->orWhere(function (Builder $query) use ($branchId): void {
                        $query->where('record_type', 'User')
                            ->whereIn('record_id', User::query()
                                ->select('id')
                                ->where('branch_id', $branchId)
                                ->whereIn('role', ['staff', 'doctor']));
                    });
            });
        }

        $logs = (clone $scopedLogs)
            ->with('user')
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('action', 'like', '%'.$search.'%')
                        ->orWhere('module', 'like', '%'.$search.'%')
                        ->orWhere('record_type', 'like', '%'.$search.'%')
                        ->orWhere('record_id', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('created_at', ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc')
            ->paginate(40)
            ->withQueryString();

        return view('logs.audit', [
            'logs' => $logs,
            'modules' => (clone $scopedLogs)->select('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => (clone $scopedLogs)->select('action')->distinct()->orderBy('action')->pluck('action'),
            'isReadOnly' => $isBranchDoctor,
        ]);
    }

    public function smsLogs(Request $request): View
    {
        $user = $request->user();
        $isBranchDoctor = $user->hasRole('doctor');
        abort_if($isBranchDoctor && $user->branch_id === null, 403);

        return view('logs.sms', [
            'logs' => SmsLog::with('patient.user')
                ->whereIn('type', SmsNotifier::ALLOWED_TYPES)
                ->when(
                    $isBranchDoctor,
                    fn (Builder $query): Builder => $query->whereHas(
                        'appointment',
                        fn (Builder $appointmentQuery): Builder => $appointmentQuery->where('branch_id', $user->branch_id),
                    ),
                )
                ->latest()
                ->paginate(40),
            'isReadOnly' => $isBranchDoctor,
        ]);
    }
}
