<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PayMongoBillCheckout;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PayMongoBillCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_checkout_uses_the_full_remaining_balance_and_waits_for_webhook_confirmation(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_bill_test_123',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/bill-test-session',
                    ],
                ],
            ]),
        ]);
        [, $bill] = $this->createPatientBill(total: '100.05', paymentStatus: 'partially_paid');
        Payment::factory()->for($bill)->create([
            'amount' => 25.02,
            'applied_amount' => 25.02,
        ]);
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->post(route('clinic.billing.paymongo.store', $bill));

        $response->assertRedirect('https://checkout.paymongo.com/bill-test-session');
        Http::assertSent(function (Request $request): bool {
            $attributes = $request->data()['data']['attributes'] ?? [];

            return $request->url() === 'https://api.paymongo.com/v2/checkout_sessions'
                && ($attributes['line_items'] ?? null) === [[
                    'name' => 'Doc Brigs Dental Care bill payment (test)',
                    'amount' => 7503,
                    'currency' => 'PHP',
                    'quantity' => 1,
                ]]
                && ($attributes['payment_method_types'] ?? null) === ['gcash']
                && ($attributes['success_url'] ?? null) === route('paymongo.bill.result', 'success')
                && ($attributes['cancel_url'] ?? null) === route('paymongo.bill.result', 'cancelled')
                && preg_match('/^DENTALCARE-BILL-TEST-[0-9a-f-]{36}$/', $attributes['reference_number'] ?? '') === 1;
        });

        $checkout = PayMongoBillCheckout::query()->sole();
        $this->assertSame($bill->id, $checkout->bill_id);
        $this->assertSame(7503, $checkout->amount_centavos);
        $this->assertSame('cs_bill_test_123', $checkout->checkout_session_id);
        $this->assertSame('pending', $checkout->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'partially_paid',
        ]);
    }

    public function test_clinic_checkout_requires_authentication_and_rejects_patients(): void
    {
        $this->configurePayMongo();
        $patient = User::factory()->create(['role' => 'patient']);
        [, $bill] = $this->createPatientBill();

        $this->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirectToRoute('login');

        $this->actingAs($patient)
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertForbidden();

        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-auth-check',
            'checkout_session_id' => 'cs_bill_test_auth_check',
            'amount_centavos' => 10000,
        ]);

        $this->actingAs($patient)
            ->post(route('clinic.billing.paymongo.reconcile', $checkout))
            ->assertForbidden();

        $this->assertDatabaseCount('paymongo_bill_checkouts', 1);
    }

    public function test_clinic_billing_displays_checkout_only_when_test_credentials_are_configured(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        [, $bill] = $this->createPatientBill();
        $staff = User::factory()->create(['role' => 'staff']);

        Config::set('services.paymongo.secret', 'sk_test_example');
        Config::set('services.paymongo.base_url', 'https://api.paymongo.com/v2');
        Config::set('services.paymongo.webhook_secret', 'whsk_test_example');
        $pendingCheckout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-status-check',
            'checkout_session_id' => 'cs_bill_test_status_check',
            'checkout_url' => 'https://checkout.paymongo.com/status-check',
            'amount_centavos' => 10000,
        ]);

        $this->actingAs($staff)
            ->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertSee('Start GCash test checkout')
            ->assertSee(route('clinic.billing.paymongo.store', $bill))
            ->assertSee('Check existing payment with PayMongo')
            ->assertSee(route('clinic.billing.paymongo.reconcile', $pendingCheckout));

        Config::set('services.paymongo.secret', null);

        $this->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertDontSee('Start GCash test checkout')
            ->assertDontSee('Check existing payment with PayMongo');

        Config::set('services.paymongo.secret', 'sk_live_not_allowed');

        $this->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertDontSee('Start GCash test checkout')
            ->assertDontSee('Check existing payment with PayMongo');

        $this->from(route('clinic.billing.index'))
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('paymongo_bill_checkouts', 1);
    }

    public function test_patient_billing_does_not_display_staff_checkout_controls(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        [$patient, $bill] = $this->createPatientBill();
        $this->configurePayMongo();

        $this->actingAs($patient)
            ->get(route('patient.billing'))
            ->assertOk()
            ->assertDontSee('Start GCash test checkout')
            ->assertDontSee(route('clinic.billing.paymongo.store', $bill));
    }

    public function test_bill_checkout_is_unavailable_outside_the_local_environment(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        $staff = User::factory()->create(['role' => 'staff']);
        [, $bill] = $this->createPatientBill();
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-production',
            'checkout_session_id' => 'cs_bill_test_production',
            'amount_centavos' => 10000,
        ]);
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs($staff)
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertNotFound();

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs($staff)
            ->post(route('clinic.billing.paymongo.reconcile', $checkout))
            ->assertNotFound();

        $this->assertDatabaseCount('paymongo_bill_checkouts', 1);
    }

    public function test_checkout_is_rejected_when_the_bill_has_no_outstanding_balance(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        Http::fake();
        [, $zeroBill] = $this->createPatientBill(total: 0);
        [, $settledBill] = $this->createPatientBill(total: 100);
        $staff = User::factory()->create(['role' => 'staff']);
        Payment::factory()->for($settledBill)->create([
            'amount' => 100,
            'applied_amount' => 100,
        ]);

        $this->from(route('clinic.billing.index'))
            ->actingAs($staff)
            ->post(route('clinic.billing.paymongo.store', $zeroBill))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->from(route('clinic.billing.index'))
            ->post(route('clinic.billing.paymongo.store', $settledBill))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('paymongo_bill_checkouts', 0);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_pending_checkout_is_reused_instead_of_creating_a_second_session(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        Http::fake();
        [, $bill] = $this->createPatientBill();
        $staff = User::factory()->create(['role' => 'staff']);
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-pending',
            'checkout_session_id' => 'cs_bill_test_pending',
            'checkout_url' => 'https://checkout.paymongo.com/pending-session',
            'amount_centavos' => 10000,
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirect('https://checkout.paymongo.com/pending-session');

        $this->assertDatabaseCount('paymongo_bill_checkouts', 1);
        $this->assertSame('pending', $checkout->fresh()->status);
    }

    public function test_pending_checkout_is_replaced_when_the_outstanding_balance_changes(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_bill_test_updated_balance',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/updated-balance',
                    ],
                ],
            ]),
        ]);
        [, $bill] = $this->createPatientBill();
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-stale-pending',
            'checkout_session_id' => 'cs_bill_test_stale_pending',
            'checkout_url' => 'https://checkout.paymongo.com/stale-pending',
            'amount_centavos' => 10000,
        ]);
        Payment::factory()->for($bill)->create([
            'amount' => 25,
            'applied_amount' => 25,
        ]);
        $bill->update(['payment_status' => 'partially_paid']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirect('https://checkout.paymongo.com/updated-balance');

        $this->assertSame('failed', $checkout->fresh()->status);
        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'bill_id' => $bill->id,
            'amount_centavos' => 7500,
            'status' => 'pending',
            'checkout_session_id' => 'cs_bill_test_updated_balance',
        ]);
        Http::assertSent(fn (Request $request): bool => ($request->data()['data']['attributes']['line_items'][0]['amount'] ?? null) === 7500);
    }

    public function test_staff_can_verify_and_reconcile_an_existing_paid_paymongo_checkout(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        $bill = $this->createPatientBill(total: 16000)[1];
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-manual-reconcile',
            'checkout_session_id' => 'cs_bill_test_manual_reconcile',
            'checkout_url' => 'https://checkout.paymongo.com/manual-reconcile',
            'amount_centavos' => 1600000,
        ]);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/cs_bill_test_manual_reconcile' => Http::response([
                'data' => [
                    'id' => 'cs_bill_test_manual_reconcile',
                    'attributes' => [
                        'reference_number' => 'DENTALCARE-BILL-TEST-manual-reconcile',
                        'livemode' => false,
                        'payments' => [[
                            'id' => 'pay_bill_test_manual_reconcile',
                            'attributes' => [
                                'amount' => 1600000,
                                'currency' => 'PHP',
                                'status' => 'paid',
                                'livemode' => false,
                                'paid_at' => 1792494619,
                                'source' => ['type' => 'gcash'],
                            ],
                        ]],
                    ],
                ],
            ]),
        ]);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->from(route('clinic.billing.index'))
            ->post(route('clinic.billing.paymongo.reconcile', $checkout))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHas('status', 'PayMongo confirmed the test payment. The bill balance and receipt have been updated.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions/cs_bill_test_manual_reconcile');
        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'paid',
            'paid_payment_id' => 'pay_bill_test_manual_reconcile',
        ]);
        $this->assertDatabaseHas('payments', [
            'bill_id' => $checkout->bill_id,
            'amount' => 16000,
            'applied_amount' => 16000,
            'method' => 'gcash',
            'provider' => 'paymongo',
            'provider_transaction_id' => 'pay_bill_test_manual_reconcile',
            'provider_checkout_session_id' => 'cs_bill_test_manual_reconcile',
            'is_test' => true,
            'processed_by' => null,
        ]);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('bills', [
            'id' => $checkout->bill_id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_manual_checkout_check_does_not_apply_an_unpaid_paymongo_session(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        $bill = $this->createPatientBill()[1];
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-not-paid',
            'checkout_session_id' => 'cs_bill_test_not_paid',
            'amount_centavos' => 10000,
        ]);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/cs_bill_test_not_paid' => Http::response([
                'data' => [
                    'id' => 'cs_bill_test_not_paid',
                    'attributes' => [
                        'reference_number' => 'DENTALCARE-BILL-TEST-not-paid',
                        'livemode' => false,
                        'payments' => [],
                    ],
                ],
            ]),
        ]);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->from(route('clinic.billing.index'))
            ->post(route('clinic.billing.paymongo.reconcile', $checkout))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
            'paid_payment_id' => null,
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_manual_checkout_check_rejects_a_paymongo_response_for_a_different_session(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        $bill = $this->createPatientBill(total: 100)[1];
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-session-mismatch',
            'checkout_session_id' => 'cs_bill_test_expected_session',
            'amount_centavos' => 10000,
        ]);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/cs_bill_test_expected_session' => Http::response([
                'data' => [
                    'id' => 'cs_bill_test_other_session',
                    'attributes' => [
                        'reference_number' => 'DENTALCARE-BILL-TEST-session-mismatch',
                        'livemode' => false,
                        'payments' => [[
                            'id' => 'pay_bill_test_wrong_session',
                            'attributes' => [
                                'amount' => 10000,
                                'currency' => 'PHP',
                                'status' => 'paid',
                                'livemode' => false,
                                'source' => ['type' => 'gcash'],
                            ],
                        ]],
                    ],
                ],
            ]),
        ]);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->from(route('clinic.billing.index'))
            ->post(route('clinic.billing.paymongo.reconcile', $checkout))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
            'paid_payment_id' => null,
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_checkout_api_failure_is_reported_and_does_not_change_real_billing(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'errors' => [['detail' => 'private upstream error']],
            ], 503),
        ]);
        [, $bill] = $this->createPatientBill();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->from(route('clinic.billing.index'))
            ->actingAs($staff)
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'bill_id' => $bill->id,
            'status' => 'failed',
            'checkout_session_id' => null,
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_signed_matching_gcash_webhook_records_a_test_payment_and_receipt_in_the_bill_ledger(): void
    {
        $this->configurePayMongo();
        [, $bill] = $this->createPatientBill(total: 100, paymentStatus: 'partially_paid');
        $payment = Payment::factory()->for($bill)->create([
            'amount' => 25,
            'applied_amount' => 25,
        ]);
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-webhook',
            'checkout_session_id' => 'cs_bill_test_webhook',
            'checkout_url' => 'https://checkout.paymongo.com/bill-webhook',
            'amount_centavos' => 7500,
        ]);
        $paidAtTimestamp = now()->subDay()->timestamp;
        $payload = $this->paidWebhookPayload($checkout);
        $payload['data']['data']['attributes']['payments'][0]['attributes']['paid_at'] = $paidAtTimestamp;

        $this->sendSignedPayload($payload)
            ->assertOk()
            ->assertExactJson(['received' => true, 'processed' => true]);

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'paid',
            'paid_payment_id' => 'pay_bill_test_123',
        ]);
        $this->assertNotNull($checkout->fresh()->paid_at);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(
            $paidAtTimestamp,
            Payment::query()->where('provider_transaction_id', 'pay_bill_test_123')->sole()->paid_at->timestamp,
        );
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'applied_amount' => 25,
        ]);
        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'transaction_id' => 'TXN-'.now()->setTimestamp($paidAtTimestamp)->format('Ymd').'-000002',
            'amount' => 75,
            'applied_amount' => 75,
            'change_amount' => 0,
            'method' => 'gcash',
            'provider' => 'paymongo',
            'provider_transaction_id' => 'pay_bill_test_123',
            'provider_checkout_session_id' => 'cs_bill_test_webhook',
            'is_test' => true,
            'processed_by' => null,
        ]);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('receipts', [
            'payment_id' => 2,
            'receipt_number' => 'OR-'.now()->setTimestamp($paidAtTimestamp)->format('Y').'-000001',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'role' => 'system',
            'action' => 'payment_processed',
            'module' => 'payments',
            'record_type' => Payment::class,
            'record_id' => '2',
        ]);

        $receipt = Receipt::query()->sole();
        $staff = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)
            ->get(route('receipts.show', $receipt))
            ->assertSee('PayMongo test mode')
            ->assertSee('no real money transferred')
            ->assertSee('pay_bill_test_123')
            ->assertSee('cs_bill_test_webhook');

        $this->get(route('clinic.billing.index'))
            ->assertSee('PayMongo TEST MODE')
            ->assertSee('pay_bill_test_123')
            ->assertSee($receipt->receipt_number);

        $this->get(route('dashboard'))
            ->assertSee('₱100.00')
            ->assertSee('Includes PayMongo TEST MODE payments');

        $this->get(route('admin.reports'))
            ->assertSee('₱100.00')
            ->assertSee('PayMongo TEST MODE payments')
            ->assertSee('do not transfer real money');
    }

    public function test_replayed_bill_webhook_is_idempotent(): void
    {
        $this->configurePayMongo();
        [, $bill] = $this->createPatientBill();
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-replay',
            'checkout_session_id' => 'cs_bill_test_replay',
            'checkout_url' => 'https://checkout.paymongo.com/bill-replay',
            'amount_centavos' => 10000,
        ]);
        $payload = $this->paidWebhookPayload($checkout);
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = $this->webhookSignature($timestamp, $rawPayload);

        $this->postSignedWebhook($rawPayload, $timestamp, $signature)
            ->assertExactJson(['received' => true, 'processed' => true]);
        $this->postSignedWebhook($rawPayload, $timestamp, $signature)
            ->assertExactJson(['received' => true, 'processed' => true]);

        $this->assertDatabaseCount('paymongo_bill_checkouts', 1);
        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'paid',
            'paid_payment_id' => 'pay_bill_test_123',
        ]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_confirmed_test_payment_is_flagged_for_review_when_the_bill_balance_changes(): void
    {
        $this->configurePayMongo();
        [, $bill] = $this->createPatientBill(total: 100, paymentStatus: 'partially_paid');
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-balance-changed',
            'checkout_session_id' => 'cs_bill_test_balance_changed',
            'checkout_url' => 'https://checkout.paymongo.com/bill-balance-changed',
            'amount_centavos' => 10000,
        ]);
        Payment::factory()->for($bill)->create([
            'amount' => 25,
            'applied_amount' => 25,
        ]);

        $this->sendSignedPayload($this->paidWebhookPayload($checkout))
            ->assertExactJson(['received' => true, 'processed' => false]);

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'review',
            'paid_payment_id' => 'pay_bill_test_123',
        ]);
        $this->assertNotNull($checkout->fresh()->paid_at);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'partially_paid',
        ]);

        Http::preventStrayRequests();
        Http::fake();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->from(route('clinic.billing.index'))
            ->actingAs($staff)
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->get(route('clinic.billing.index'))
            ->assertSee('Review this payment before starting another checkout.');
    }

    public function test_a_paymongo_payment_id_cannot_be_applied_to_two_bills(): void
    {
        $this->configurePayMongo();
        [, $firstBill] = $this->createPatientBill();
        [, $secondBill] = $this->createPatientBill();
        $firstCheckout = $firstBill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-first-bill',
            'checkout_session_id' => 'cs_bill_test_first_bill',
            'checkout_url' => 'https://checkout.paymongo.com/first-bill',
            'amount_centavos' => 10000,
        ]);
        $secondCheckout = $secondBill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-second-bill',
            'checkout_session_id' => 'cs_bill_test_second_bill',
            'checkout_url' => 'https://checkout.paymongo.com/second-bill',
            'amount_centavos' => 10000,
        ]);

        $this->sendSignedPayload($this->paidWebhookPayload($firstCheckout))
            ->assertExactJson(['received' => true, 'processed' => true]);
        $this->sendSignedPayload($this->paidWebhookPayload($secondCheckout))
            ->assertExactJson(['received' => true, 'processed' => false]);

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $secondCheckout->id,
            'status' => 'review',
            'paid_payment_id' => null,
        ]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('bills', [
            'id' => $firstBill->id,
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $secondBill->id,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_admin_can_reopen_an_existing_test_checkout(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        Http::fake();
        [, $bill] = $this->createPatientBill();
        $admin = User::factory()->create(['role' => 'admin']);
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-admin',
            'checkout_session_id' => 'cs_bill_test_admin',
            'checkout_url' => 'https://checkout.paymongo.com/admin-session',
            'amount_centavos' => 10000,
        ]);

        $this->actingAs($admin)
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirect('https://checkout.paymongo.com/admin-session');

        $this->assertDatabaseCount('paymongo_bill_checkouts', 1);
        $this->assertSame('pending', $checkout->fresh()->status);
    }

    public function test_new_checkout_is_blocked_until_a_confirmed_test_payment_is_reconciled(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();
        Http::fake();
        [, $bill] = $this->createPatientBill();
        $staff = User::factory()->create(['role' => 'staff']);
        $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-unreconciled',
            'checkout_session_id' => 'cs_bill_test_unreconciled',
            'checkout_url' => 'https://checkout.paymongo.com/unreconciled',
            'amount_centavos' => 10000,
            'status' => 'paid',
            'paid_payment_id' => 'pay_bill_test_legacy',
            'paid_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('clinic.billing.index'))
            ->assertSee('confirmed but is not yet in this bill’s payment history');

        $this->from(route('clinic.billing.index'))
            ->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirectToRoute('clinic.billing.index')
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('paymongo_bill_checkouts', 1);
    }

    public function test_bill_webhook_rejects_a_mismatched_amount_without_changing_billing(): void
    {
        $this->configurePayMongo();
        [, $bill] = $this->createPatientBill();
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-mismatch',
            'checkout_session_id' => 'cs_bill_test_mismatch',
            'checkout_url' => 'https://checkout.paymongo.com/bill-mismatch',
            'amount_centavos' => 100000,
        ]);
        $payload = $this->paidWebhookPayload($checkout);
        $payload['data']['data']['attributes']['payments'][0]['attributes']['amount'] = 10001;

        $this->sendSignedPayload($payload)->assertUnprocessable();

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
            'paid_payment_id' => null,
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_bill_webhook_rejects_mismatched_session_or_non_gcash_and_live_payments(): void
    {
        $this->configurePayMongo();
        [, $bill] = $this->createPatientBill();
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-invalid',
            'checkout_session_id' => 'cs_bill_test_invalid',
            'checkout_url' => 'https://checkout.paymongo.com/bill-invalid',
            'amount_centavos' => 100000,
        ]);

        $wrongReferencePayload = $this->paidWebhookPayload($checkout);
        $wrongReferencePayload['data']['data']['attributes']['reference_number'] = 'DENTALCARE-BILL-TEST-other';
        $this->sendSignedPayload($wrongReferencePayload)->assertNotFound();

        $wrongMethodPayload = $this->paidWebhookPayload($checkout);
        $wrongMethodPayload['data']['data']['attributes']['payments'][0]['attributes']['source']['type'] = 'card';
        $this->sendSignedPayload($wrongMethodPayload)->assertUnprocessable();

        $livePaymentPayload = $this->paidWebhookPayload($checkout);
        $livePaymentPayload['data']['data']['attributes']['payments'][0]['attributes']['livemode'] = true;
        $this->sendSignedPayload($livePaymentPayload)->assertUnprocessable();

        $this->assertDatabaseHas('paymongo_bill_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
            'paid_payment_id' => null,
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
        ]);
    }

    /**
     * @return array{0: User, 1: Bill}
     */
    private function createPatientBill(?User $user = null, string|int $total = 100, string $paymentStatus = 'unpaid'): array
    {
        $user ??= User::factory()->create(['role' => 'patient']);
        $patient = $user->patient()->first() ?? Patient::factory()->for($user)->create();
        $bill = Bill::factory()->for($patient)->create([
            'total' => $total,
            'subtotal' => $total,
            'payment_status' => $paymentStatus,
        ]);

        return [$user, $bill];
    }

    private function configurePayMongo(): void
    {
        Config::set('services.paymongo.secret', 'sk_test_example');
        Config::set('services.paymongo.base_url', 'https://api.paymongo.com/v2');
        Config::set('services.paymongo.webhook_secret', 'whsk_test_example');
    }

    /**
     * @return array<string, mixed>
     */
    private function paidWebhookPayload(PayMongoBillCheckout $checkout): array
    {
        return [
            'data' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'data' => [
                    'id' => $checkout->checkout_session_id,
                    'type' => 'checkout_session',
                    'attributes' => [
                        'reference_number' => $checkout->reference_number,
                        'livemode' => false,
                        'payments' => [
                            [
                                'id' => 'pay_bill_test_123',
                                'attributes' => [
                                    'amount' => $checkout->amount_centavos,
                                    'currency' => 'PHP',
                                    'status' => 'paid',
                                    'livemode' => false,
                                    'source' => ['type' => 'gcash'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendSignedPayload(array $payload): TestResponse
    {
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;

        return $this->postSignedWebhook($rawPayload, $timestamp, $this->webhookSignature($timestamp, $rawPayload));
    }

    private function postSignedWebhook(string $rawPayload, string $timestamp, string $signature): TestResponse
    {
        return $this->call(
            'POST',
            route('paymongo.test.webhook'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
            ],
            content: $rawPayload,
        );
    }

    private function webhookSignature(string $timestamp, string $rawPayload): string
    {
        return hash_hmac(
            'sha256',
            $timestamp.'.'.$rawPayload,
            (string) config('services.paymongo.webhook_secret'),
        );
    }
}
