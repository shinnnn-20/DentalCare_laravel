<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\PayMongoBillCheckout;
use App\Services\PayMongoBillPaymentReconciler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayMongoBillCheckoutController extends Controller
{
    public function reconcile(
        PayMongoBillCheckout $checkout,
        PayMongoBillPaymentReconciler $reconciler,
    ): RedirectResponse {
        $this->ensureLocalEnvironment();

        if (! $this->hasValidTestConfiguration()) {
            return back()->withErrors([
                'checkout' => 'PayMongo test credentials are not configured for this local environment.',
            ]);
        }

        if ($checkout->status !== 'pending'
            || ! is_string($checkout->checkout_session_id)
            || ! str_starts_with($checkout->checkout_session_id, 'cs_')) {
            return back()->withErrors([
                'checkout' => 'Only a pending PayMongo checkout session can be checked.',
            ]);
        }

        $baseUrl = str_replace('/v2', '/v1', rtrim((string) config('services.paymongo.base_url'), '/'));

        try {
            $response = Http::acceptJson()
                ->withBasicAuth((string) config('services.paymongo.secret'), '')
                ->connectTimeout(5)
                ->timeout(15)
                ->get(rtrim($baseUrl, '/').'/checkout_sessions/'.rawurlencode($checkout->checkout_session_id));
        } catch (ConnectionException $exception) {
            Log::warning('PayMongo bill test checkout status could not be retrieved.', [
                'error_category' => $this->connectionErrorCategory($exception),
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo could not be reached to verify this checkout. Check the local application logs and try again.',
            ]);
        }

        if (! $response->successful()) {
            Log::warning('PayMongo bill test checkout status request returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo could not verify this checkout. Check the local application logs and try again.',
            ]);
        }

        $session = $response->json('data');
        $attributes = is_array($session) ? ($session['attributes'] ?? null) : null;

        if (! is_array($session)
            || ($session['id'] ?? null) !== $checkout->checkout_session_id
            || ! is_array($attributes)
            || ($attributes['reference_number'] ?? null) !== $checkout->reference_number
            || ($attributes['livemode'] ?? null) !== false) {
            Log::warning('PayMongo bill test checkout status response did not match the recorded test checkout.', [
                'checkout_id' => $checkout->id,
                'session_id' => $checkout->checkout_session_id,
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo returned checkout details that do not match this bill’s recorded test session.',
            ]);
        }

        $paidPayment = $reconciler->paidPayment($attributes['payments'] ?? null);

        if ($paidPayment === null) {
            return back()->withErrors([
                'checkout' => 'PayMongo does not report a completed GCash test payment for this checkout yet.',
            ]);
        }

        $result = $reconciler->process($checkout->reference_number, $checkout->checkout_session_id, $paidPayment);

        if ($result === 'processed') {
            return back()->with('status', 'PayMongo confirmed the test payment. The bill balance and receipt have been updated.');
        }

        if ($result === 'review') {
            return back()->withErrors([
                'checkout' => 'PayMongo confirmed payment, but the bill balance changed. The checkout is flagged for staff review and was not applied.',
            ]);
        }

        if ($result === 'payment_conflict') {
            return back()->withErrors([
                'checkout' => 'PayMongo confirmed a payment that conflicts with an existing record. The checkout is flagged for staff review.',
            ]);
        }

        if ($result === 'amount_mismatch') {
            return back()->withErrors([
                'checkout' => 'The confirmed PayMongo amount does not match the recorded checkout amount.',
            ]);
        }

        return back()->withErrors([
            'checkout' => 'The recorded checkout could not be found for reconciliation.',
        ]);
    }

    public function store(Bill $bill): RedirectResponse
    {
        $this->ensureLocalEnvironment();

        if (! $this->hasValidTestConfiguration()) {
            return back()->withErrors([
                'checkout' => 'GCash test checkout is not configured for this local environment.',
            ]);
        }

        $checkout = DB::transaction(function () use ($bill): PayMongoBillCheckout {
            $lockedBill = Bill::query()
                ->lockForUpdate()
                ->findOrFail($bill->id);
            $totalCentavos = $this->pesosToCentavos((string) $lockedBill->total);
            $paidCentavos = $this->pesosToCentavos((string) $lockedBill->payments()->sum('applied_amount'));
            $remainingCentavos = max(0, $totalCentavos - $paidCentavos);

            if ($remainingCentavos === 0) {
                throw ValidationException::withMessages([
                    'checkout' => 'This bill has no outstanding balance.',
                ]);
            }

            if ($lockedBill->payMongoBillCheckouts()->where('status', 'review')->exists()) {
                throw ValidationException::withMessages([
                    'checkout' => 'A confirmed GCash test payment for this bill needs staff review before another checkout can be started.',
                ]);
            }

            foreach ($lockedBill->payMongoBillCheckouts()->where('status', 'paid')->get() as $paidCheckout) {
                $paymentWasRecorded = is_string($paidCheckout->paid_payment_id)
                    && $lockedBill->payments()
                        ->where('provider', 'paymongo')
                        ->where('is_test', true)
                        ->where('provider_transaction_id', $paidCheckout->paid_payment_id)
                        ->exists();

                if (! $paymentWasRecorded) {
                    throw ValidationException::withMessages([
                        'checkout' => 'A confirmed PayMongo test payment is waiting for billing reconciliation. Resolve it before starting another checkout.',
                    ]);
                }
            }

            $pendingCheckout = $lockedBill->payMongoBillCheckouts()
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($pendingCheckout !== null) {
                if ($pendingCheckout->amount_centavos !== $remainingCentavos) {
                    $pendingCheckout->update(['status' => 'failed']);
                } elseif (is_string($pendingCheckout->checkout_url)) {
                    return $pendingCheckout;
                } elseif ($pendingCheckout->created_at->greaterThan(now()->subMinutes(2))) {
                    throw ValidationException::withMessages([
                        'checkout' => 'A GCash test checkout is already being prepared. Please try again shortly.',
                    ]);
                } else {
                    $pendingCheckout->update(['status' => 'failed']);
                }
            }

            return $lockedBill->payMongoBillCheckouts()->create([
                'reference_number' => 'DENTALCARE-BILL-TEST-'.Str::uuid(),
                'amount_centavos' => $remainingCentavos,
            ]);
        });

        if (is_string($checkout->checkout_url)) {
            return redirect()->away($checkout->checkout_url);
        }

        $bill->loadMissing(['patient.user']);
        $patientUser = $bill->patient->user;
        $secretKey = config('services.paymongo.secret');
        $baseUrl = config('services.paymongo.base_url');

        $billing = array_filter([
            'name' => $patientUser->name,
            'email' => $patientUser->email,
            'phone' => $bill->patient->emergency_contact,
        ]);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($secretKey, '')
                ->connectTimeout(5)
                ->timeout(15)
                ->post(rtrim($baseUrl, '/').'/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'billing' => $billing,
                            'description' => 'Payment for Bill '.$bill->bill_number.' - '.$patientUser->name,
                            'line_items' => [
                                [
                                    'name' => config('clinic.name').' bill payment (test)',
                                    'amount' => $checkout->amount_centavos,
                                    'currency' => 'PHP',
                                    'quantity' => 1,
                                ],
                            ],
                            'payment_method_types' => ['gcash'],
                            'success_url' => route('paymongo.bill.result', 'success'),
                            'cancel_url' => route('paymongo.bill.result', 'cancelled'),
                            'reference_number' => $checkout->reference_number,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $checkout->update(['status' => 'failed']);
            Log::warning('PayMongo bill test checkout could not connect to the API.', [
                'error_category' => $this->connectionErrorCategory($exception),
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo could not be reached. Check the local application logs and try again.',
            ]);
        }

        if (! $response->successful()) {
            $checkout->update(['status' => 'failed']);
            Log::warning('PayMongo bill test checkout returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo could not start the GCash test checkout. Check the local application logs and try again.',
            ]);
        }

        $checkoutUrl = $response->json('data.attributes.checkout_url');
        $checkoutSessionId = $response->json('data.id');

        if (! is_string($checkoutUrl)
            || ! $this->isPayMongoCheckoutUrl($checkoutUrl)
            || ! is_string($checkoutSessionId)
            || ! str_starts_with($checkoutSessionId, 'cs_')) {
            $checkout->update(['status' => 'failed']);
            Log::warning('PayMongo bill test checkout returned an invalid checkout response.', [
                'status' => $response->status(),
            ]);

            return back()->withErrors([
                'checkout' => 'PayMongo returned an invalid GCash test checkout response. Check the local application logs and try again.',
            ]);
        }

        $checkout->update([
            'checkout_session_id' => $checkoutSessionId,
            'checkout_url' => $checkoutUrl,
        ]);

        return redirect()->away($checkoutUrl);
    }

    public function result(string $result): View
    {
        $this->ensureLocalEnvironment();
        abort_unless(in_array($result, ['success', 'cancelled'], true), 404);

        return view('paymongo.bill-checkout-result', ['result' => $result]);
    }

    private function ensureLocalEnvironment(): void
    {
        abort_unless(
            app()->isLocal() || (app()->environment('testing') && app()->runningUnitTests()),
            404,
        );
    }

    private function hasValidTestConfiguration(): bool
    {
        $secretKey = config('services.paymongo.secret');
        $baseUrl = config('services.paymongo.base_url');
        $webhookSecret = config('services.paymongo.webhook_secret');

        return is_string($secretKey)
            && str_starts_with($secretKey, 'sk_test_')
            && $baseUrl === 'https://api.paymongo.com/v2'
            && is_string($webhookSecret)
            && $webhookSecret !== '';
    }

    private function isPayMongoCheckoutUrl(string $checkoutUrl): bool
    {
        $urlParts = parse_url($checkoutUrl);

        return filter_var($checkoutUrl, FILTER_VALIDATE_URL) !== false
            && is_array($urlParts)
            && ($urlParts['scheme'] ?? null) === 'https'
            && ($urlParts['host'] ?? null) === 'checkout.paymongo.com'
            && ! isset($urlParts['user'])
            && ! isset($urlParts['pass'])
            && ! isset($urlParts['port']);
    }

    private function pesosToCentavos(string $amount): int
    {
        $isNegative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '+-');
        [$pesos, $fractionalCentavos] = array_pad(explode('.', $amount, 2), 2, '');
        $centavos = ((int) $pesos * 100) + (int) str_pad(substr($fractionalCentavos, 0, 2), 2, '0');

        return $isNegative ? -$centavos : $centavos;
    }

    private function connectionErrorCategory(ConnectionException $exception): string
    {
        if (preg_match('/cURL error (\d+)/i', $exception->getMessage(), $matches) !== 1) {
            return 'connection_exception';
        }

        return match ((int) $matches[1]) {
            6 => 'dns_resolution',
            7 => 'connection_failed',
            28 => 'connection_timeout',
            35 => 'tls_handshake',
            60 => 'tls_certificate_validation',
            default => 'curl_error_'.$matches[1],
        };
    }
}
