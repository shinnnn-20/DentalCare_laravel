<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PayMongoBillCheckout;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayMongoBillPaymentReconciler
{
    /**
     * @return array{id: string, amount: int, paid_at: int|null}|null
     */
    public function paidPayment(mixed $payments): ?array
    {
        if (! is_array($payments)) {
            return null;
        }

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $paymentAttributes = $payment['attributes'] ?? null;
            $source = is_array($paymentAttributes) ? ($paymentAttributes['source'] ?? null) : null;

            if (! is_array($paymentAttributes)
                || ($paymentAttributes['status'] ?? null) !== 'paid'
                || ($paymentAttributes['currency'] ?? null) !== 'PHP'
                || ! is_int($paymentAttributes['amount'] ?? null)
                || (($paymentAttributes['paid_at'] ?? null) !== null && ! is_int($paymentAttributes['paid_at']))
                || ($paymentAttributes['livemode'] ?? null) !== false
                || ! is_array($source)
                || ($source['type'] ?? null) !== 'gcash'
                || ! is_string($payment['id'] ?? null)) {
                continue;
            }

            return [
                'id' => $payment['id'],
                'amount' => $paymentAttributes['amount'],
                'paid_at' => $paymentAttributes['paid_at'] ?? null,
            ];
        }

        return null;
    }

    /**
     * @param  array{id: string, amount: int, paid_at: int|null}  $paidPayment
     */
    public function process(string $referenceNumber, string $sessionId, array $paidPayment): string
    {
        return DB::transaction(function () use ($referenceNumber, $sessionId, $paidPayment): string {
            $checkout = PayMongoBillCheckout::query()
                ->where('reference_number', $referenceNumber)
                ->where('checkout_session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($checkout === null) {
                return 'not_found';
            }

            if ($paidPayment['amount'] !== $checkout->amount_centavos) {
                return 'amount_mismatch';
            }

            if ($checkout->status === 'review') {
                return 'review';
            }

            if ($checkout->status === 'paid' && $checkout->paid_payment_id !== $paidPayment['id']) {
                return 'payment_conflict';
            }

            $bill = Bill::query()->lockForUpdate()->findOrFail($checkout->bill_id);
            $existingPayment = Payment::query()
                ->where('provider_transaction_id', $paidPayment['id'])
                ->first();

            if ($existingPayment !== null) {
                if ($existingPayment->bill_id === $bill->id
                    && $existingPayment->provider === 'paymongo'
                    && $existingPayment->is_test
                    && $existingPayment->method === 'gcash'
                    && $existingPayment->provider_checkout_session_id === $sessionId
                    && (int) round((float) $existingPayment->applied_amount * 100) === $checkout->amount_centavos) {
                    $checkout->update([
                        'status' => 'paid',
                        'paid_payment_id' => $paidPayment['id'],
                        'paid_at' => $existingPayment->paid_at ?? now(),
                    ]);

                    return 'processed';
                }

                $checkout->update([
                    'status' => 'review',
                    'paid_at' => now(),
                ]);

                return 'payment_conflict';
            }

            $totalCentavos = (int) round((float) $bill->total * 100);
            $paidCentavos = (int) round((float) $bill->payments()->sum('applied_amount') * 100);
            $remainingCentavos = max(0, $totalCentavos - $paidCentavos);

            if ($remainingCentavos !== $checkout->amount_centavos) {
                $checkout->update([
                    'status' => 'review',
                    'paid_payment_id' => $paidPayment['id'],
                    'paid_at' => now(),
                ]);

                return 'review';
            }

            $paidAt = $paidPayment['paid_at'] === null
                ? now()
                : now()->setTimestamp($paidPayment['paid_at']);
            $payment = Payment::query()->create([
                'bill_id' => $bill->id,
                'transaction_id' => 'TXN-PENDING-'.Str::uuid(),
                'amount' => number_format($paidPayment['amount'] / 100, 2, '.', ''),
                'applied_amount' => number_format($paidPayment['amount'] / 100, 2, '.', ''),
                'change_amount' => '0.00',
                'method' => 'gcash',
                'provider' => 'paymongo',
                'provider_transaction_id' => $paidPayment['id'],
                'provider_checkout_session_id' => $sessionId,
                'is_test' => true,
                'paid_at' => $paidAt,
                'processed_by' => null,
            ]);
            $payment->update([
                'transaction_id' => sprintf('TXN-%s-%06d', $paidAt->format('Ymd'), $payment->id),
            ]);

            $receipt = Receipt::query()->create([
                'payment_id' => $payment->id,
                'receipt_number' => 'OR-PENDING-'.Str::uuid(),
            ]);
            $receipt->update([
                'receipt_number' => sprintf('OR-%s-%06d', $paidAt->format('Y'), $receipt->id),
            ]);

            $bill->update(['payment_status' => 'paid']);
            $checkout->update([
                'status' => 'paid',
                'paid_payment_id' => $paidPayment['id'],
                'paid_at' => $paidAt,
            ]);

            AuditLog::query()->create([
                'user_id' => null,
                'role' => 'system',
                'action' => 'payment_processed',
                'module' => 'payments',
                'record_type' => Payment::class,
                'record_id' => (string) $payment->id,
                'description' => "PayMongo test payment {$paidPayment['id']} recorded for bill {$bill->bill_number}.",
            ]);

            return 'processed';
        });
    }
}
