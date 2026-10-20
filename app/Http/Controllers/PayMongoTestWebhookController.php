<?php

namespace App\Http\Controllers;

use App\Models\PayMongoBillCheckout;
use App\Models\PayMongoTestCheckout;
use App\Services\PayMongoBillPaymentReconciler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;

class PayMongoTestWebhookController extends Controller
{
    private const int TEST_AMOUNT_CENTAVOS = 10000;

    public function __invoke(Request $request, PayMongoBillPaymentReconciler $reconciler): JsonResponse
    {
        abort_unless(
            app()->isLocal() || (app()->environment('testing') && app()->runningUnitTests()),
            404,
        );

        $webhookSecret = config('services.paymongo.webhook_secret');

        if (! is_string($webhookSecret) || $webhookSecret === '') {
            Log::warning('PayMongo test webhook secret is not configured.');

            return response()->json(['message' => 'Webhook is not configured.'], 503);
        }

        if (! $this->hasValidSignature($request, $webhookSecret)) {
            Log::warning('PayMongo test webhook signature was rejected.', [
                'signature_header_present' => $request->headers->has('Paymongo-Signature'),
                'body_bytes' => strlen($request->getContent()),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::warning('PayMongo test webhook payload was not valid JSON.');

            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $event = $payload['data'] ?? null;
        $eventAttributes = is_array($event) && is_array($event['attributes'] ?? null)
            ? $event['attributes']
            : null;
        $usesEventResourceEnvelope = is_array($event) && ($event['type'] ?? null) === 'event';
        $eventType = $usesEventResourceEnvelope
            ? ($eventAttributes['type'] ?? null)
            : (is_array($event) ? ($event['type'] ?? null) : null);
        $eventData = $usesEventResourceEnvelope
            ? ($eventAttributes['data'] ?? null)
            : (is_array($event) ? ($event['data'] ?? null) : null);
        $livemode = $usesEventResourceEnvelope
            ? ($eventAttributes['livemode'] ?? null)
            : (is_array($event) ? ($event['livemode'] ?? null) : null);

        Log::info('PayMongo test webhook received.', [
            'payload_keys' => is_array($payload) ? array_keys($payload) : [],
            'event_keys' => is_array($event) ? array_keys($event) : [],
            'event_attributes_keys' => is_array($eventAttributes) ? array_keys($eventAttributes) : [],
            'event_type' => is_string($eventType)
                ? substr($eventType, 0, 100)
                : null,
            'event_data_keys' => is_array($eventData) ? array_keys($eventData) : [],
        ]);

        if (! is_array($event) || ! is_string($eventType)) {
            Log::warning('PayMongo test webhook did not contain the expected event envelope.');

            return response()->json(['message' => 'Invalid webhook event.'], 400);
        }

        if ($eventType !== 'checkout_session.payment.paid') {
            Log::notice('PayMongo test webhook event type was not processed.', [
                'event_type' => $eventType,
            ]);

            return response()->json(['received' => true, 'processed' => false]);
        }

        if ($livemode !== false) {
            Log::warning('PayMongo test webhook event was not in test mode.', [
                'livemode' => $livemode,
            ]);

            return response()->json(['message' => 'Only test-mode events are accepted.'], 422);
        }

        $session = $eventData;
        $attributes = is_array($session) ? ($session['attributes'] ?? null) : null;
        $sessionId = is_array($session) ? ($session['id'] ?? null) : null;
        $referenceNumber = is_array($attributes) ? ($attributes['reference_number'] ?? null) : null;

        if (! is_string($sessionId) || ! is_string($referenceNumber)) {
            Log::warning('PayMongo test webhook checkout identifiers were missing.', [
                'session_id_present' => is_string($sessionId),
                'reference_number_present' => is_string($referenceNumber),
            ]);

            return response()->json(['message' => 'Invalid checkout session event.'], 400);
        }

        if (($attributes['livemode'] ?? null) !== false) {
            Log::warning('PayMongo test webhook checkout session was not in test mode.', [
                'session_id' => $sessionId,
            ]);

            return response()->json(['message' => 'Only test-mode GCash checkout sessions are accepted.'], 422);
        }

        $paidPayment = $reconciler->paidPayment($attributes['payments'] ?? null);

        if ($paidPayment === null) {
            Log::warning('PayMongo test webhook did not contain a matching paid payment.', [
                'session_id' => $sessionId,
                'payments_count' => is_array($attributes['payments'] ?? null)
                    ? count($attributes['payments'])
                    : 0,
            ]);

            return response()->json(['message' => 'No matching paid test payment was found.'], 422);
        }

        $billCheckoutExists = PayMongoBillCheckout::query()
            ->where('reference_number', $referenceNumber)
            ->where('checkout_session_id', $sessionId)
            ->exists();

        if ($billCheckoutExists) {
            $result = $reconciler->process($referenceNumber, $sessionId, $paidPayment);

            if ($result === 'amount_mismatch') {
                Log::warning('PayMongo bill test webhook amount did not match the recorded checkout amount.', [
                    'session_id' => $sessionId,
                ]);

                return response()->json(['message' => 'No matching paid test payment was found.'], 422);
            }

            if ($result === 'review') {
                Log::warning('PayMongo bill test payment requires staff review because the bill balance changed.', [
                    'session_id' => $sessionId,
                    'payment_id' => $paidPayment['id'],
                ]);

                return response()->json(['received' => true, 'processed' => false]);
            }

            if ($result === 'payment_conflict') {
                Log::warning('PayMongo bill test webhook referenced a different or already-used payment.', [
                    'session_id' => $sessionId,
                    'payment_id' => $paidPayment['id'],
                ]);

                return response()->json(['received' => true, 'processed' => false]);
            }

            if ($result === 'not_found') {
                return response()->json(['message' => 'Checkout session was not found.'], 404);
            }

            Log::info('PayMongo bill test webhook processed.', [
                'session_id' => $sessionId,
                'reference_number' => $referenceNumber,
                'payment_id' => $paidPayment['id'],
            ]);

            return response()->json(['received' => true, 'processed' => true]);
        }

        $checkout = DB::transaction(function () use ($referenceNumber, $sessionId, $paidPayment): ?PayMongoTestCheckout {
            $checkout = PayMongoTestCheckout::query()
                ->where('reference_number', $referenceNumber)
                ->where('checkout_session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($checkout === null) {
                return null;
            }

            if ($paidPayment['amount'] !== self::TEST_AMOUNT_CENTAVOS) {
                return $checkout;
            }

            if ($checkout->status === 'paid') {
                return $checkout;
            }

            $checkout->status = 'paid';
            $checkout->paid_payment_id = $paidPayment['id'];
            $checkout->paid_at = now();
            $checkout->save();

            return $checkout;
        });

        if ($checkout === null) {
            Log::warning('PayMongo test webhook did not match a recorded test checkout.', [
                'session_id' => $sessionId,
                'reference_number' => $referenceNumber,
            ]);

            return response()->json(['message' => 'Checkout session was not found.'], 404);
        }

        if ($paidPayment['amount'] !== self::TEST_AMOUNT_CENTAVOS) {
            Log::warning('PayMongo standalone test webhook payment amount did not match the expected test checkout amount.', [
                'session_id' => $sessionId,
            ]);

            return response()->json(['message' => 'No matching paid test payment was found.'], 422);
        }

        if ($checkout->status === 'paid' && $checkout->paid_payment_id !== $paidPayment['id']) {
            Log::warning('PayMongo test webhook referenced a different payment for an already paid test checkout.');

            return response()->json(['received' => true, 'processed' => false]);
        }

        Log::info('PayMongo test webhook processed.', [
            'session_id' => $sessionId,
            'reference_number' => $referenceNumber,
            'payment_id' => $paidPayment['id'],
        ]);

        return response()->json(['received' => true, 'processed' => true]);
    }

    private function hasValidSignature(Request $request, string $secret): bool
    {
        $header = $request->header('Paymongo-Signature');

        if (! is_string($header) || $header === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? null;
        $testSignature = $parts['te'] ?? null;

        if (! is_string($timestamp)
            || preg_match('/^\d+$/', $timestamp) !== 1
            || ! is_string($testSignature)
            || preg_match('/^[a-f0-9]{64}$/i', $testSignature) !== 1) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expectedSignature, strtolower($testSignature));
    }
}
