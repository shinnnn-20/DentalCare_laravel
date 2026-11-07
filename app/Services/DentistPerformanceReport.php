<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\DentalRecord;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DentistPerformanceReport
{
    /**
     * @return array{
     *     performance: array<int, array<string, int|float|string|null>>,
     *     details: array<int, array<string, int|float|string|null>>,
     *     clinic_collected: float,
     *     unattributed_collected: float
     * }
     */
    public function generate(
        Carbon $from,
        Carbon $to,
        ?string $branchId,
        ?string $dentistSelection,
    ): array {
        $rangeStart = $from->copy()->startOfDay()->utc()->format('Y-m-d H:i:s');
        $rangeEnd = $to->copy()->endOfDay()->utc()->format('Y-m-d H:i:s');
        $bills = $this->billsForRange($rangeStart, $rangeEnd, $branchId);
        $performance = [];
        $details = [];
        $allocatedCollectedCents = 0;

        foreach ($bills as $bill) {
            $items = $bill->items;

            if ($items->isEmpty()) {
                $this->addUnitemizedBill(
                    $bill,
                    $performance,
                    $details,
                    $rangeStart,
                    $rangeEnd,
                    $dentistSelection,
                    $allocatedCollectedCents,
                );

                continue;
            }

            $grossWeights = $items
                ->map(fn ($item): int => (int) round((float) $item->subtotal * 100))
                ->all();
            $netAmounts = $this->allocate(
                (int) round((float) $bill->total * 100),
                $grossWeights,
            );
            $paidAmounts = array_fill(0, $items->count(), 0);
            $paidInRangeAmounts = array_fill(0, $items->count(), 0);

            foreach ($bill->payments as $payment) {
                $paymentCents = (int) round((float) $payment->applied_amount * 100);
                $paymentAllocation = $this->allocate($paymentCents, $netAmounts);
                foreach ($paymentAllocation as $index => $amount) {
                    $paidAmounts[$index] += $amount;
                }

                if ($this->isWithinRange($payment->created_at, $rangeStart, $rangeEnd)) {
                    foreach ($paymentAllocation as $index => $amount) {
                        $paidInRangeAmounts[$index] += $amount;
                    }
                    $allocatedCollectedCents += array_sum($paymentAllocation);
                }
            }

            foreach ($items as $index => $item) {
                $dentist = $item->treatingDentist;
                $itemDentistId = $dentist?->id;
                if (! $this->dentistMatches($itemDentistId, $dentistSelection)) {
                    continue;
                }

                $record = $bill->appointment?->dentalRecord;
                $treatmentDate = $item->is_add_on ? $item->created_at : $record?->created_at;
                $performedInRange = $treatmentDate !== null
                    && $this->isWithinRange($treatmentDate, $rangeStart, $rangeEnd);
                $billedInRange = $this->isWithinRange($item->created_at, $rangeStart, $rangeEnd);
                $appointmentInRange = $bill->appointment?->status === 'completed'
                    && $this->isWithinRange($bill->appointment->starts_at, $rangeStart, $rangeEnd);
                $netCents = $netAmounts[$index] ?? 0;
                $paidCents = $paidAmounts[$index] ?? 0;
                $paidInRangeCents = $paidInRangeAmounts[$index] ?? 0;
                $balanceCents = max(0, $netCents - $paidCents);
                $branch = $bill->appointment?->branch;
                $summaryKey = ($itemDentistId ?? 'unassigned').':'.($branch?->id ?? 'unassigned');

                $this->initializeSummary($performance, $summaryKey, $dentist, $branch);
                if ($performedInRange) {
                    $performance[$summaryKey]['services_performed'] += (int) $item->quantity;
                    $performance[$summaryKey]['patient_ids'][$bill->patient_id] = true;
                }
                if ($appointmentInRange) {
                    $performance[$summaryKey]['appointment_ids'][$bill->appointment_id] = true;
                }
                if ($billedInRange) {
                    $performance[$summaryKey]['billed_cents'] += $netCents;
                    $performance[$summaryKey]['services_billed'] += (int) $item->quantity;
                    $performance[$summaryKey]['outstanding_cents'] += $balanceCents;
                }
                $performance[$summaryKey]['collected_cents'] += $paidInRangeCents;

                if (! $performedInRange && ! $billedInRange && $paidInRangeCents === 0 && ! $appointmentInRange) {
                    continue;
                }

                $details[] = [
                    'dentist_id' => $itemDentistId,
                    'dentist_name' => $dentist?->name ?? 'Not recorded',
                    'assigned_branch' => $dentist?->branch?->name ?? 'Unassigned',
                    'branch_id' => $branch?->id,
                    'patient_name' => $bill->patient?->user?->name ?? 'Unknown patient',
                    'patient_id' => $bill->patient?->patient_number ?? '—',
                    'appointment_date' => $bill->appointment?->starts_at
                        ? $bill->appointment->starts_at->timezone(config('clinic.timezone'))->format('M j, Y')
                        : '—',
                    'branch' => $branch?->name ?? 'Unassigned',
                    'service' => $item->service_name,
                    'bill_number' => $bill->bill_number,
                    'service_amount' => $netCents / 100,
                    'amount_paid' => $paidCents / 100,
                    'collected_in_period' => $paidInRangeCents / 100,
                    'remaining_balance' => $balanceCents / 100,
                    'payment_status' => str_replace('_', ' ', ucfirst($bill->payment_status)),
                ];
            }
        }

        $this->addUnbilledTreatments(
            $performance,
            $details,
            $rangeStart,
            $rangeEnd,
            $branchId,
            $dentistSelection,
        );

        $clinicCollectedCents = (int) round($this->clinicCollected($rangeStart, $rangeEnd, $branchId) * 100);
        foreach ($performance as &$summary) {
            $summary['patients_treated'] = count($summary['patient_ids']);
            $summary['completed_appointments'] = count($summary['appointment_ids']);
            $summary['total_billed'] = $summary['billed_cents'] / 100;
            $summary['total_collected'] = $summary['collected_cents'] / 100;
            $summary['revenue'] = $summary['total_collected'];
            $summary['outstanding_balance'] = $summary['outstanding_cents'] / 100;
            unset(
                $summary['patient_ids'],
                $summary['appointment_ids'],
                $summary['billed_cents'],
                $summary['collected_cents'],
                $summary['outstanding_cents'],
            );
        }
        unset($summary);

        $performance = array_values(array_filter(
            $performance,
            fn (array $summary): bool => $this->dentistMatches(
                $summary['dentist_id'] === null ? null : (int) $summary['dentist_id'],
                $dentistSelection,
            ) && ($summary['patients_treated'] > 0
                || $summary['services_performed'] > 0
                || $summary['completed_appointments'] > 0
                || $summary['total_billed'] > 0
                || $summary['total_collected'] > 0),
        ));
        usort(
            $performance,
            static fn (array $left, array $right): int => [
                $left['dentist_name'],
                $left['branch'],
            ] <=> [
                $right['dentist_name'],
                $right['branch'],
            ],
        );
        $details = array_values(array_filter(
            $details,
            fn (array $detail): bool => $this->dentistMatches(
                $detail['dentist_id'] === null ? null : (int) $detail['dentist_id'],
                $dentistSelection,
            ),
        ));

        return [
            'performance' => $performance,
            'details' => $details,
            'clinic_collected' => $clinicCollectedCents / 100,
            'unattributed_collected' => max(0, $clinicCollectedCents - $allocatedCollectedCents) / 100,
        ];
    }

    /**
     * @return Collection<int, Bill>
     */
    private function billsForRange(string $rangeStart, string $rangeEnd, ?string $branchId): Collection
    {
        return Bill::query()
            ->whereHas('appointment', function (Builder $query) use ($branchId): void {
                $this->applyBranchFilter($query, $branchId, 'appointments.branch_id');
            })
            ->where(function (Builder $query) use ($rangeStart, $rangeEnd): void {
                $query->whereBetween('bills.created_at', [$rangeStart, $rangeEnd])
                    ->orWhereHas('payments', fn (Builder $payments): Builder => $payments
                        ->whereBetween('created_at', [$rangeStart, $rangeEnd]))
                    ->orWhereHas('items', fn (Builder $items): Builder => $items
                        ->whereBetween('created_at', [$rangeStart, $rangeEnd]))
                    ->orWhereHas('appointment.dentalRecord', fn (Builder $records): Builder => $records
                        ->whereBetween('created_at', [$rangeStart, $rangeEnd]))
                    ->orWhereHas('appointment', fn (Builder $appointments): Builder => $appointments
                        ->whereBetween('starts_at', [$rangeStart, $rangeEnd]));
            })
            ->with([
                'items.treatingDentist.branch',
                'payments',
                'patient.user',
                'appointment.branch',
                'appointment.dentalRecord.treatingDentist.branch',
                'appointment.services',
                'appointment.service',
            ])
            ->get();
    }

    /**
     * @param  array<string, array<string, int|float|string|null|array>>  $performance
     * @param  array<int, array<string, int|float|string|null>>  $details
     */
    private function addUnitemizedBill(
        Bill $bill,
        array &$performance,
        array &$details,
        string $rangeStart,
        string $rangeEnd,
        ?string $dentistSelection,
        int &$allocatedCollectedCents,
    ): void {
        $billCollectedInRange = 0;
        $billCollectedAllTime = 0;
        foreach ($bill->payments as $payment) {
            $amount = (int) round((float) $payment->applied_amount * 100);
            $billCollectedAllTime += $amount;
            if ($this->isWithinRange($payment->created_at, $rangeStart, $rangeEnd)) {
                $billCollectedInRange += $amount;
            }
        }
        $allocatedCollectedCents += $billCollectedInRange;

        $record = $bill->appointment?->dentalRecord;
        $dentist = $record?->treatingDentist;
        if (! $this->dentistMatches($dentist?->id, $dentistSelection)) {
            return;
        }

        $branch = $bill->appointment?->branch;
        $key = ($dentist?->id ?? 'unassigned').':'.($branch?->id ?? 'unassigned');
        $this->initializeSummary($performance, $key, $dentist, $branch);
        $billedInRange = $this->isWithinRange($bill->created_at, $rangeStart, $rangeEnd);
        $performedInRange = $record !== null && $this->isWithinRange($record->created_at, $rangeStart, $rangeEnd);

        if ($performedInRange) {
            $serviceCount = max(1, $bill->appointment?->services->count() ?? 0);
            $performance[$key]['services_performed'] += $serviceCount;
            $performance[$key]['patient_ids'][$bill->patient_id] = true;
        }
        if ($bill->appointment?->status === 'completed'
            && $this->isWithinRange($bill->appointment->starts_at, $rangeStart, $rangeEnd)) {
            $performance[$key]['appointment_ids'][$bill->appointment_id] = true;
        }
        if ($billedInRange) {
            $billTotalCents = (int) round((float) $bill->total * 100);
            $performance[$key]['billed_cents'] += $billTotalCents;
            $performance[$key]['outstanding_cents'] += max(0, $billTotalCents - $billCollectedAllTime);
            $performance[$key]['services_billed'] += max(1, $bill->appointment?->services->count() ?? 0);
        }
        $performance[$key]['collected_cents'] += $billCollectedInRange;
        $details[] = [
            'dentist_id' => $dentist?->id,
            'dentist_name' => $dentist?->name ?? 'Not recorded',
            'assigned_branch' => $dentist?->branch?->name ?? 'Unassigned',
            'branch_id' => $branch?->id,
            'patient_name' => $bill->patient?->user?->name ?? 'Unknown patient',
            'patient_id' => $bill->patient?->patient_number ?? '—',
            'appointment_date' => $bill->appointment?->starts_at
                ? $bill->appointment->starts_at->timezone(config('clinic.timezone'))->format('M j, Y')
                : '—',
            'branch' => $branch?->name ?? 'Unassigned',
            'service' => 'No service line recorded',
            'bill_number' => $bill->bill_number,
            'service_amount' => (int) round((float) $bill->total * 100) / 100,
            'amount_paid' => $billCollectedAllTime / 100,
            'collected_in_period' => $billCollectedInRange / 100,
            'remaining_balance' => max(0, (int) round((float) $bill->total * 100) - $billCollectedAllTime) / 100,
            'payment_status' => str_replace('_', ' ', ucfirst($bill->payment_status)),
        ];
    }

    /**
     * @param  array<string, array<string, int|float|string|null|array>>  $performance
     * @param  array<int, array<string, int|float|string|null>>  $details
     */
    private function addUnbilledTreatments(
        array &$performance,
        array &$details,
        string $rangeStart,
        string $rangeEnd,
        ?string $branchId,
        ?string $dentistSelection,
    ): void {
        $records = DentalRecord::query()
            ->where(function (Builder $query) use ($rangeStart, $rangeEnd): void {
                $query->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->orWhereHas('appointment', fn (Builder $appointments): Builder => $appointments
                        ->whereBetween('starts_at', [$rangeStart, $rangeEnd]));
            })
            ->whereHas('appointment')
            ->with([
                'patient.user',
                'treatingDentist.branch',
                'appointment.branch',
                'appointment.service',
                'appointment.services',
                'appointment.bill.items',
            ])
            ->get();

        foreach ($records as $record) {
            $appointment = $record->appointment;
            if ($appointment === null || $appointment->bill !== null) {
                continue;
            }
            if (! $this->branchMatches($appointment->branch_id, $branchId)) {
                continue;
            }

            $dentist = $record->treatingDentist;
            if (! $this->dentistMatches($dentist?->id, $dentistSelection)) {
                continue;
            }

            $services = $appointment->services;
            if ($services->isEmpty() && $appointment->service !== null) {
                $services = collect([$appointment->service]);
            }
            if ($services->isEmpty()) {
                $services = collect([(object) ['name' => $record->treatment]]);
            }

            $branch = $appointment->branch;
            $key = ($dentist?->id ?? 'unassigned').':'.($branch?->id ?? 'unassigned');
            $this->initializeSummary($performance, $key, $dentist, $branch);
            $performedInRange = $this->isWithinRange($record->created_at, $rangeStart, $rangeEnd);
            if ($performedInRange) {
                $performance[$key]['services_performed'] += $services->count();
                $performance[$key]['patient_ids'][$record->patient_id] = true;
            }
            if ($appointment->status === 'completed'
                && $this->isWithinRange($appointment->starts_at, $rangeStart, $rangeEnd)) {
                $performance[$key]['appointment_ids'][$appointment->id] = true;
            }

            foreach ($services as $service) {
                $details[] = [
                    'dentist_id' => $dentist?->id,
                    'dentist_name' => $dentist?->name ?? 'Not recorded',
                    'assigned_branch' => $dentist?->branch?->name ?? 'Unassigned',
                    'branch_id' => $branch?->id,
                    'patient_name' => $record->patient?->user?->name ?? 'Unknown patient',
                    'patient_id' => $record->patient?->patient_number ?? '—',
                    'appointment_date' => $appointment->starts_at
                        ->timezone(config('clinic.timezone'))
                        ->format('M j, Y'),
                    'branch' => $branch?->name ?? 'Unassigned',
                    'service' => $service->name,
                    'bill_number' => 'Not billed',
                    'service_amount' => 0.0,
                    'amount_paid' => 0.0,
                    'collected_in_period' => 0.0,
                    'remaining_balance' => 0.0,
                    'payment_status' => 'Not billed',
                ];
            }
        }
    }

    /**
     * @param  array<string, array<string, int|float|string|null|array>>  $performance
     */
    private function initializeSummary(
        array &$performance,
        string $key,
        mixed $dentist,
        mixed $branch,
    ): void {
        $performance[$key] ??= [
            'dentist_id' => $dentist?->id,
            'dentist_name' => $dentist?->name ?? 'Not recorded',
            'assigned_branch' => $dentist?->branch?->name ?? 'Unassigned',
            'branch_id' => $branch?->id,
            'branch' => $branch?->name ?? 'Unassigned',
            'patients_treated' => 0,
            'services_performed' => 0,
            'services_billed' => 0,
            'completed_appointments' => 0,
            'billed_cents' => 0,
            'collected_cents' => 0,
            'outstanding_cents' => 0,
            'patient_ids' => [],
            'appointment_ids' => [],
        ];
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function allocate(int $amount, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $weights = array_map(static fn (int $weight): int => max(0, $weight), $weights);
        $weightTotal = array_sum($weights);
        if ($weightTotal === 0) {
            $weights = array_fill(0, count($weights), 1);
            $weightTotal = count($weights);
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;
        foreach ($weights as $index => $weight) {
            $weightedAmount = $amount * $weight;
            $allocations[$index] = intdiv($weightedAmount, $weightTotal);
            $remainders[$index] = $weightedAmount % $weightTotal;
            $allocated += $allocations[$index];
        }

        $order = array_keys($weights);
        usort($order, static fn (int $left, int $right): int => $remainders[$right] <=> $remainders[$left]);
        for ($index = 0, $remaining = $amount - $allocated; $index < $remaining; $index++) {
            $allocations[$order[$index % count($order)]]++;
        }

        ksort($allocations);

        return $allocations;
    }

    private function clinicCollected(string $rangeStart, string $rangeEnd, ?string $branchId): float
    {
        $payments = Payment::query()->whereBetween('payments.created_at', [$rangeStart, $rangeEnd]);
        if ($branchId === null || $branchId === 'all') {
            return (float) $payments->sum('applied_amount');
        }

        return (float) $payments
            ->where(function (Builder $query) use ($branchId): void {
                $query->whereHas('bill.appointment', function (Builder $appointments) use ($branchId): void {
                    $this->applyBranchFilter($appointments, $branchId, 'appointments.branch_id');
                });
                if ($branchId === 'unassigned') {
                    $query->orWhereDoesntHave('bill.appointment');
                }
            })
            ->sum('applied_amount');
    }

    private function applyBranchFilter(Builder $query, ?string $branchId, string $column): void
    {
        if ($branchId === 'unassigned') {
            $query->whereNull($column);
        } elseif ($branchId !== null && $branchId !== 'all') {
            $query->where($column, $branchId);
        }
    }

    private function branchMatches(?int $appointmentBranchId, ?string $branchId): bool
    {
        if ($branchId === null || $branchId === 'all') {
            return true;
        }
        if ($branchId === 'unassigned') {
            return $appointmentBranchId === null;
        }

        return $appointmentBranchId === (int) $branchId;
    }

    private function dentistMatches(?int $actualDentistId, ?string $dentistSelection): bool
    {
        if ($dentistSelection === null || $dentistSelection === '') {
            return true;
        }
        if ($dentistSelection === 'unassigned') {
            return $actualDentistId === null;
        }

        return $actualDentistId === (int) $dentistSelection;
    }

    private function isWithinRange(Carbon $date, string $rangeStart, string $rangeEnd): bool
    {
        $utcDate = $date->copy()->utc();

        return $utcDate->betweenIncluded(
            Carbon::parse($rangeStart, 'UTC'),
            Carbon::parse($rangeEnd, 'UTC'),
        );
    }
}
