<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\SmsLog;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClinicWorkflowController extends Controller
{
    public function queue(): View
    {
        return view('queue.index', [
            'queueEntries' => QueueEntry::query()
                ->with(['appointment.patient.user', 'appointment.service', 'appointment.services'])
                ->whereDate('queue_date', today(config('clinic.timezone')))
                ->whereIn('status', ['waiting', 'called', 'in_consultation'])
                ->orderBy('id')
                ->get(),
            'checkInAppointments' => Appointment::query()
                ->with(['patient.user', 'service', 'services'])
                ->whereDate('starts_at', '>=', today(config('clinic.timezone')))
                ->where('status', 'approved')
                ->whereDoesntHave('queueEntry')
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    public function createRecord(Appointment $appointment): View
    {
        abort_unless(in_array($appointment->status, ['in_consultation', 'completed'], true), 422);

        return view('records.create', [
            'appointment' => $appointment->load(['patient.user', 'service', 'services']),
        ]);
    }

    public function storeRecord(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        abort_unless(in_array($appointment->status, ['in_consultation', 'completed'], true), 422);

        $data = $request->validate([
            'diagnosis' => ['required', 'string', 'max:5000'],
            'treatment' => ['required', 'string', 'max:5000'],
            'prescription' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if ($appointment->dentalRecord()->exists()) {
            throw ValidationException::withMessages(['appointment' => 'A dental record already exists for this appointment.']);
        }

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

        return redirect()->route('clinic.patients.show', $appointment->patient_id)->with('status', 'Dental record saved.');
    }

    public function createBill(Appointment $appointment): View
    {
        abort_unless($appointment->status === 'in_consultation', 422);
        abort_if($appointment->dentalRecord()->doesntExist(), 422, 'A dental record is required before billing.');
        abort_if($appointment->bill()->exists(), 409, 'A bill already exists for this appointment.');
        $appointment->load(['patient.user', 'services', 'service']);

        return view('billing.create', [
            'appointment' => $appointment,
        ]);
    }

    public function storeBill(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        abort_unless($appointment->status === 'in_consultation', 422);
        abort_if($appointment->dentalRecord()->doesntExist(), 422, 'A dental record is required before billing.');
        abort_if($appointment->bill()->exists(), 409, 'A bill already exists for this appointment.');
        $appointment->load(['service', 'services', 'patient.user']);

        $services = $appointment->services->isNotEmpty()
            ? $appointment->services
            : ($appointment->service ? collect([$appointment->service]) : collect());

        abort_if($services->isEmpty(), 422, 'This appointment has no treatments associated with it.');

        foreach (['discount', 'final_price'] as $field) {
            $value = $request->input($field);

            if (is_string($value)
                && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $value) === 1) {
                $request->merge([$field => str_replace(',', '', $value)]);
            }
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
            'discount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
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

        $discountCents = (int) round((float) $data['discount'] * 100);

        if ($discountCents > $subtotalCents) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the subtotal.']);
        }

        $bill = DB::transaction(function () use ($appointment, $request, $subtotalCents, $discountCents, $itemRows): Bill {
            $totalCents = $subtotalCents - $discountCents;
            $bill = Bill::create([
                'bill_number' => 'B-PENDING-'.Str::uuid(),
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'created_by' => $request->user()->id,
                'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                'discount' => number_format($discountCents / 100, 2, '.', ''),
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'payment_status' => $totalCents === 0 ? 'paid' : 'unpaid',
            ]);
            $bill->update(['bill_number' => sprintf('B-%s-%06d', now()->format('Y'), $bill->id)]);

            foreach ($itemRows as $row) {
                $bill->items()->create($row);
            }

            $appointment->update(['status' => 'billed']);

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

    public function billing(Request $request): View
    {
        $filters = $request->validate([
            'bill' => ['nullable', 'integer', 'exists:bills,id'],
        ]);

        return view('billing.index', [
            'bills' => Bill::query()
                ->with(['patient.user', 'appointment', 'items', 'payments.receipt', 'payMongoBillCheckouts'])
                ->when(
                    $filters['bill'] ?? null,
                    fn ($query, $billId) => $query->orderByRaw('CASE WHEN bills.id = ? THEN 0 ELSE 1 END', [$billId]),
                )
                ->latest()
                ->paginate(20),
            'selectedBillId' => isset($filters['bill']) ? (int) $filters['bill'] : null,
            'paymongoTestCheckoutAvailable' => $this->paymongoTestCheckoutAvailable(),
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

    public function patientNotifications(Request $request): View
    {
        $patient = $request->user()->patient()->firstOrFail();

        return view('logs.patient-notifications', [
            'logs' => SmsLog::query()->whereBelongsTo($patient)->latest()->paginate(20),
        ]);
    }

    public function reports(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = $filters['from'] ?? today()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();
        $rangeStart = Carbon::parse($from, config('clinic.timezone'))->startOfDay();
        $rangeEnd = Carbon::parse($to, config('clinic.timezone'))->endOfDay();
        $rangeDays = $rangeStart->diffInDays($rangeEnd) + 1;
        $granularity = $rangeDays <= 31 ? 'day' : ($rangeDays <= 180 ? 'week' : 'month');
        $bucketKey = static function (Carbon $date) use ($granularity): string {
            return match ($granularity) {
                'week' => $date->copy()->startOfWeek()->toDateString(),
                'month' => $date->format('Y-m'),
                default => $date->toDateString(),
            };
        };
        $timeline = [];
        $cursor = $rangeStart->copy();

        while ($cursor->lessThanOrEqualTo($rangeEnd)) {
            $key = $bucketKey($cursor);
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
            }
            match ($granularity) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        $appointmentRows = Appointment::query()
            ->selectRaw('DATE(starts_at) as activity_date, COUNT(*) as total')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->groupBy('activity_date')
            ->get();
        foreach ($appointmentRows as $row) {
            $key = $bucketKey(Carbon::parse($row->activity_date, config('clinic.timezone')));
            if (isset($timeline[$key])) {
                $timeline[$key]['appointments'] += (int) $row->total;
            }
        }

        $revenueRows = DB::table('payments')
            ->selectRaw('DATE(created_at) as activity_date, SUM(applied_amount) as total')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy('activity_date')
            ->get();
        foreach ($revenueRows as $row) {
            $key = $bucketKey(Carbon::parse($row->activity_date, config('clinic.timezone')));
            if (isset($timeline[$key])) {
                $timeline[$key]['revenue'] += (float) $row->total;
            }
        }

        $statusCounts = Appointment::query()
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

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'appointments' => Appointment::whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'walkIns' => Appointment::where('type', 'walk_in')->whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'completed' => Appointment::where('status', 'completed')->whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->whereBetween('starts_at', [$rangeStart, $rangeEnd])->count(),
            'revenue' => DB::table('payments')->whereBetween('created_at', [$rangeStart, $rangeEnd])->sum('applied_amount'),
            'newPatients' => Patient::whereBetween('created_at', [$rangeStart, $rangeEnd])->count(),
            'timeline' => $timeline,
            'statuses' => $statuses,
            'appointmentMaximum' => $appointmentMaximum,
            'revenueMaximum' => $revenueMaximum,
        ]);
    }

    public function auditLogs(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'in:newest,oldest'],
        ]);

        return view('logs.audit', [
            'logs' => AuditLog::query()
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
                ->withQueryString(),
            'modules' => AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function smsLogs(): View
    {
        return view('logs.sms', [
            'logs' => SmsLog::with('patient.user')->latest()->paginate(40),
        ]);
    }
}
