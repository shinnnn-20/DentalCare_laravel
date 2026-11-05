<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClinicRevenueReport
{
    /**
     * @param  Builder<Payment>  $payments
     * @return Builder<Payment>
     */
    public function filterPaymentsByPatient(Builder $payments, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $payments;
        }

        return $payments->whereHas('bill.patient', function (Builder $patientQuery) use ($search): void {
            $patientQuery
                ->where('patient_number', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', '%'.$search.'%'));
        });
    }

    /**
     * @return array{string, string}
     */
    public function utcRange(Carbon $startsAt, Carbon $endsAt): array
    {
        return [
            $startsAt->copy()->utc()->format('Y-m-d H:i:s'),
            $endsAt->copy()->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  Builder<Payment>  $payments
     * @param  array<string, array{start: Carbon, end: Carbon}>  $buckets
     * @return array<string, float>
     */
    public function totalsByClinicBuckets(Builder $payments, array $buckets): array
    {
        if ($buckets === []) {
            return [];
        }

        $bucketRanges = array_values($buckets);
        [$rangeStart, $rangeEnd] = $this->utcRange(
            $bucketRanges[0]['start'],
            $bucketRanges[array_key_last($bucketRanges)]['end'],
        );
        $expressions = [];
        $bindings = [];

        foreach ($bucketRanges as $index => $bucket) {
            [$bucketStart, $bucketEnd] = $this->utcRange($bucket['start'], $bucket['end']);
            $expressions[] = "COALESCE(SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN applied_amount ELSE 0 END), 0) as bucket_{$index}";
            $bindings[] = $bucketStart;
            $bindings[] = $bucketEnd;
        }

        $totals = $payments
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->selectRaw(implode(', ', $expressions), $bindings)
            ->first();

        $bucketTotals = [];

        foreach (array_keys($buckets) as $index => $key) {
            $bucketTotals[$key] = (float) ($totals->{'bucket_'.$index} ?? 0);
        }

        return $bucketTotals;
    }
}
