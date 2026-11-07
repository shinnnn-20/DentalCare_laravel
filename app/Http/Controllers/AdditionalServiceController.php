<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use App\Services\AuditTrail;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdditionalServiceController extends Controller
{
    public function create(Request $request, Bill $bill): View
    {
        $this->authorizeBillBranch($request, $bill);
        $bill->load([
            'appointment.dentalRecord.treatingDentist',
            'patient.user',
            'items.treatingDentist',
            'payments',
        ]);
        $appointment = $bill->appointment;
        abort_unless($appointment !== null && $this->isEligible($appointment, $bill), 422);
        $canAddServices = ! $this->isPaid($bill);

        return view('billing.additional-services', [
            'bill' => $bill,
            'canAddServices' => $canAddServices,
            'services' => $canAddServices ? $this->availableServices($bill) : collect(),
            'treatingDentists' => User::query()
                ->where('role', 'doctor')
                ->where('is_active', true)
                ->when(
                    $appointment->branch_id === null,
                    fn ($query) => $query->whereNull('branch_id'),
                    fn ($query) => $query->where('branch_id', $appointment->branch_id),
                )
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(
        Request $request,
        Bill $bill,
        AppointmentCompletionService $appointmentCompletion,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $this->authorizeBillBranch($request, $bill);
        $appointment = $bill->appointment()->firstOrFail();
        abort_unless($this->isEligible($appointment, $bill), 422);
        if ($this->isPaid($bill)) {
            throw ValidationException::withMessages([
                'service_ids' => 'Additional services cannot be added to a paid bill.',
            ]);
        }

        $serviceIdsInput = $request->input('service_ids');

        if (is_array($serviceIdsInput)) {
            $itemsInput = $request->input('items');

            if (is_array($itemsInput)) {
                foreach ($itemsInput as $key => $itemData) {
                    if (isset($itemData['final_price'])
                        && is_string($itemData['final_price'])
                        && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $itemData['final_price']) === 1) {
                        $itemsInput[$key]['final_price'] = str_replace(',', '', $itemData['final_price']);
                    }
                }

                $request->merge(['items' => $itemsInput]);
            }
        }

        $data = $request->validate([
            'service_ids' => ['required', 'array', 'min:1', 'max:20'],
            'service_ids.*' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'items' => ['sometimes', 'array'],
            'confirm_add_ons' => ['required', 'accepted'],
        ]);

        $serviceIds = array_map('intval', $data['service_ids']);
        $appointment->load('dentalRecord');
        $serviceDentists = $request->input('service_dentists');
        $serviceDentists = array_replace(
            array_fill_keys(
                array_map('strval', $serviceIds),
                $appointment->dentalRecord?->treating_dentist_id,
            ),
            is_array($serviceDentists) ? $serviceDentists : [],
        );
        $request->merge(['service_dentists' => $serviceDentists]);
        $services = Service::query()
            ->when(
                $appointment->branch_id !== null,
                fn ($query) => $query->forBranch((int) $appointment->branch_id),
                fn ($query) => $query->whereNull('branch_id'),
            )
            ->whereIn('id', $serviceIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages([
                'service_ids' => 'Choose only active services.',
            ]);
        }

        $hasDuplicateServiceId = $bill->items()
            ->whereIn('service_id', $serviceIds)
            ->exists();
        $hasMatchingServiceSnapshot = $bill->items()
            ->whereIn('service_name', $services->pluck('name'))
            ->exists();

        if ($hasDuplicateServiceId || $hasMatchingServiceSnapshot) {
            throw ValidationException::withMessages([
                'service_ids' => 'A service can only be added to this bill once.',
            ]);
        }

        $rules = [];

        foreach ($services as $service) {
            $rules["service_dentists.{$service->id}"] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function (QueryBuilder $query) use ($appointment): void {
                    $query->where('role', 'doctor')
                        ->where('is_active', true)
                        ->when(
                            $appointment->branch_id === null,
                            fn (QueryBuilder $query): QueryBuilder => $query->whereNull('branch_id'),
                            fn (QueryBuilder $query): QueryBuilder => $query->where('branch_id', $appointment->branch_id),
                        );
                }),
            ];
            if ($service->requires_price_confirmation) {
                $rules["items.{$service->id}.final_price"] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'];
                $rules["items.{$service->id}.confirm_final_price"] = ['accepted'];
            } else {
                $rules["items.{$service->id}.final_price"] = ['prohibited'];
                $rules["items.{$service->id}.confirm_final_price"] = ['prohibited'];
            }
        }

        $data = [...$data, ...$request->validate($rules)];

        DB::transaction(function () use ($bill, $data, $serviceIds, $services, $request, $auditTrail, $appointmentCompletion): void {
            $lockedBill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $appointment = Appointment::query()
                ->whereKey($lockedBill->appointment_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($this->isEligible($appointment, $lockedBill), 422);
            if ($this->isPaid($lockedBill)) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Additional services cannot be added to a paid bill.',
                ]);
            }

            $existingServiceIds = $lockedBill->items()
                ->whereIn('service_id', $serviceIds)
                ->pluck('service_id')
                ->map(static fn ($serviceId): int => (int) $serviceId);
            $hasMatchingServiceSnapshot = $lockedBill->items()
                ->whereIn('service_name', $services->pluck('name'))
                ->exists();

            if ($existingServiceIds->isNotEmpty() || $hasMatchingServiceSnapshot) {
                throw ValidationException::withMessages([
                    'service_ids' => 'A service can only be added to this bill once.',
                ]);
            }

            $additionalCents = 0;
            $auditDescriptions = [];
            $itemRows = [];

            foreach ($serviceIds as $serviceId) {
                $service = $services->get($serviceId);

                if ($service === null || ! $service->is_active) {
                    throw ValidationException::withMessages([
                        'service_ids' => 'Choose only active services.',
                    ]);
                }

                $priceValue = $service->requires_price_confirmation
                    ? $data['items'][$serviceId]['final_price']
                    : $service->price;
                $priceCents = (int) round((float) $priceValue * 100);
                $additionalCents += $priceCents;
                $price = number_format($priceCents / 100, 2, '.', '');

                $itemRows[] = [
                    'service_id' => $service->id,
                    'treating_dentist_id' => $data['service_dentists'][$serviceId] ?? null,
                    'service_name' => $service->name,
                    'is_add_on' => true,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'subtotal' => $price,
                ];
                $auditDescriptions[] = sprintf(
                    '%s (#%d) at ₱%s',
                    $service->name,
                    $service->id,
                    $price,
                );
            }

            $subtotalCents = (int) round((float) $lockedBill->subtotal * 100) + $additionalCents;
            $discountCents = (int) round((float) $lockedBill->discount * 100);
            $totalCents = (int) round((float) $lockedBill->total * 100) + $additionalCents;
            $paidCents = (int) round((float) $lockedBill->payments()->sum('applied_amount') * 100);

            foreach ($itemRows as $itemRow) {
                $lockedBill->items()->create($itemRow);
            }

            $lockedBill->update([
                'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                'discount' => number_format($discountCents / 100, 2, '.', ''),
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'payment_status' => $paidCents >= $totalCents
                    ? 'paid'
                    : ($paidCents > 0 ? 'partially_paid' : 'unpaid'),
            ]);

            $auditTrail->record(
                $request->user(),
                'additional_services_added',
                'billing',
                $lockedBill,
                sprintf(
                    'Added %s to bill %s for patient %s; total changed from PHP %s to PHP %s.',
                    implode('; ', $auditDescriptions),
                    $lockedBill->bill_number,
                    $lockedBill->patient()->value('patient_number') ?? 'unknown patient',
                    number_format(($totalCents - $additionalCents) / 100, 2),
                    number_format($totalCents / 100, 2),
                ),
            );
            $appointmentCompletion->sync($lockedBill);
        });

        return redirect()
            ->to(route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id)
            ->with('status', 'Additional services added to the bill.');
    }

    private function availableServices(Bill $bill): Collection
    {
        return Service::query()
            ->when(
                $bill->appointment->branch_id !== null,
                fn ($query) => $query->forBranch((int) $bill->appointment->branch_id),
                fn ($query) => $query->whereNull('branch_id'),
            )
            ->where('is_active', true)
            ->whereNotIn('id', $bill->items()->whereNotNull('service_id')->select('service_id'))
            ->whereNotIn('name', $bill->items()->select('service_name'))
            ->when(Schema::hasColumn('services', 'category'), fn ($query) => $query->orderBy('category'))
            ->orderBy('name')
            ->get();
    }

    private function isEligible(Appointment $appointment, Bill $bill): bool
    {
        return $appointment->patient_id === $bill->patient_id
            && in_array($appointment->status, ['billed', 'completed'], true);
    }

    private function isPaid(Bill $bill): bool
    {
        $paidAmount = $bill->relationLoaded('payments')
            ? $bill->payments->sum('applied_amount')
            : $bill->payments()->sum('applied_amount');

        return $bill->payment_status === 'paid'
            || (int) round((float) $paidAmount * 100)
                >= (int) round((float) $bill->total * 100);
    }

    private function authorizeBillBranch(Request $request, Bill $bill): void
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);

        if ($user->hasRole('admin') || $user->branch_id === null) {
            return;
        }

        abort_unless(
            $bill->appointment()->where('branch_id', $user->branch_id)->exists(),
            404,
        );
    }
}
