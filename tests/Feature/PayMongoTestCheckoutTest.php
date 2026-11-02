<?php

namespace Tests\Feature;

use App\Models\PayMongoTestCheckout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PayMongoTestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_local_admins_can_open_the_test_checkout(): void
    {
        $this->get(route('paymongo.test.index'))
            ->assertRedirectToRoute('login');

        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->get(route('paymongo.test.index'))
            ->assertForbidden();

        Config::set('services.paymongo.secret', null);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('paymongo.test.index'))
            ->assertOk()
            ->assertSee('PayMongo GCash test checkout')
            ->assertSee('PAYMONGO_SECRET_KEY')
            ->assertDontSee('Start ₱100 test checkout');
    }

    public function test_admin_can_create_an_isolated_gcash_test_checkout(): void
    {
        Config::set('services.paymongo.secret', 'sk_test_example');
        Config::set('services.paymongo.base_url', 'https://api.paymongo.com/v2');
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_example',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/test-session',
                    ],
                ],
            ]),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('paymongo.test.store'));

        $response->assertRedirect('https://checkout.paymongo.com/test-session');
        Http::assertSent(function (Request $request): bool {
            $attributes = $request->data()['data']['attributes'] ?? [];
            $authorization = $request->header('Authorization')[0] ?? '';

            return $request->url() === 'https://api.paymongo.com/v2/checkout_sessions'
                && str_starts_with($authorization, 'Basic ')
                && ($attributes['line_items'] ?? null) === [[
                    'name' => 'Doc Brigs Dental Care Test Checkout',
                    'amount' => 10000,
                    'currency' => 'PHP',
                    'quantity' => 1,
                ]]
                && ($attributes['payment_method_types'] ?? null) === ['gcash']
                && ($attributes['success_url'] ?? null) === route('paymongo.test.success')
                && ($attributes['cancel_url'] ?? null) === route('paymongo.test.cancelled')
                && preg_match('/^DENTALCARE-TEST-[0-9a-f-]{36}$/', $attributes['reference_number'] ?? '') === 1;
        });

        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('payments', 0);
        $checkout = PayMongoTestCheckout::query()->sole();
        $this->assertStringStartsWith('DENTALCARE-TEST-', $checkout->reference_number);
        $this->assertSame('cs_test_example', $checkout->checkout_session_id);
        $this->assertSame('pending', $checkout->status);
    }

    public function test_checkout_fails_closed_when_no_test_key_is_configured(): void
    {
        Config::set('services.paymongo.secret', null);
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('paymongo.test.index'))
            ->post(route('paymongo.test.store'));

        $response->assertRedirect(route('paymongo.test.index'))
            ->assertSessionHasErrors('checkout');
    }

    public function test_live_keys_are_rejected_by_the_test_checkout(): void
    {
        Config::set('services.paymongo.secret', 'sk_live_example');
        Config::set('services.paymongo.base_url', 'https://api.paymongo.com/v2');
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('paymongo.test.index'))
            ->post(route('paymongo.test.store'));

        $response->assertRedirect(route('paymongo.test.index'))
            ->assertSessionHasErrors('checkout');
    }

    public function test_connection_failures_are_logged_as_safe_categories(): void
    {
        $this->configureTestKey();
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => static function (): never {
                throw new ConnectionException('Connection failed');
            },
        ]);
        Log::spy();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('paymongo.test.index'))
            ->post(route('paymongo.test.store'));

        $response->assertRedirect(route('paymongo.test.index'))
            ->assertSessionHasErrors('checkout')
            ->assertSessionHas('checkout_diagnostic', 'connection_exception');
        Log::shouldHaveReceived('warning')
            ->with('PayMongo test checkout could not connect to the API.', [
                'error_category' => 'connection_exception',
            ]);
        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_api_errors_are_not_exposed_to_the_user(): void
    {
        $this->configureTestKey();
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'errors' => [['detail' => 'private upstream error detail']],
            ], 422),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('paymongo.test.index'))
            ->post(route('paymongo.test.store'));

        $response->assertRedirect(route('paymongo.test.index'))
            ->assertSessionHasErrors('checkout');
        $this->get(route('paymongo.test.index'))
            ->assertOk()
            ->assertDontSee('private upstream error detail');
        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_checkout_urls_are_not_used_for_redirects(): void
    {
        $this->configureTestKey();
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'attributes' => [
                        'checkout_url' => 'https://untrusted.example.test/checkout',
                    ],
                ],
            ]),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('paymongo.test.index'))
            ->post(route('paymongo.test.store'));

        $response->assertRedirect(route('paymongo.test.index'))
            ->assertSessionHasErrors('checkout');
    }

    public function test_return_pages_do_not_claim_that_a_payment_was_verified(): void
    {
        $this->get(route('paymongo.test.success'))
            ->assertOk()
            ->assertSee('does not prove that a payment succeeded');

        $this->get(route('paymongo.test.cancelled'))
            ->assertOk()
            ->assertSee('does not verify payment');
    }

    public function test_checkout_is_unavailable_outside_local_environment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Config::set('services.paymongo.webhook_secret', 'whsk_test_example');
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->actingAs($admin)
            ->get(route('paymongo.test.index'))
            ->assertNotFound();

        $this->get(route('paymongo.test.success'))
            ->assertNotFound();

        $this->post(route('paymongo.test.webhook'))
            ->assertNotFound();
    }

    public function test_valid_test_webhook_marks_only_its_recorded_test_checkout_paid(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();

        $response = $this->sendSignedPayload($this->paidWebhookPayload($checkout));

        $response->assertOk()
            ->assertExactJson(['received' => true, 'processed' => true]);
        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'paid',
            'paid_payment_id' => 'pay_test_payment_123',
        ]);
        $this->assertNotNull($checkout->fresh()->paid_at);
        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_event_resource_webhook_envelope_marks_its_recorded_checkout_paid(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();
        $checkoutSession = $this->paidWebhookPayload($checkout)['data']['data'];
        $payload = [
            'data' => [
                'id' => 'evt_test_paid_123',
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'data' => $checkoutSession,
                ],
            ],
        ];

        $response = $this->sendSignedPayload($payload);

        $response->assertOk()
            ->assertExactJson(['received' => true, 'processed' => true]);
        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'paid',
            'paid_payment_id' => 'pay_test_payment_123',
        ]);
        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_replayed_paid_webhook_is_idempotent(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();
        $payload = $this->paidWebhookPayload($checkout);
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = $this->webhookSignature($timestamp, $rawPayload);

        $this->postSignedWebhook($rawPayload, $timestamp, $signature)
            ->assertOk()
            ->assertExactJson(['received' => true, 'processed' => true]);

        $this->postSignedWebhook($rawPayload, $timestamp, $signature)
            ->assertOk()
            ->assertExactJson(['received' => true, 'processed' => true]);

        $this->assertDatabaseCount('paymongo_test_checkouts', 1);
        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'paid',
            'paid_payment_id' => 'pay_test_payment_123',
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_webhook_signature_does_not_update_checkout(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();
        $payload = json_encode($this->paidWebhookPayload($checkout), JSON_THROW_ON_ERROR);

        $this->postSignedWebhook($payload, (string) now()->timestamp, str_repeat('0', 64))
            ->assertUnauthorized();

        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
            'paid_payment_id' => null,
        ]);
    }

    public function test_webhook_signature_is_verified_against_the_exact_raw_body(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();
        $signedBody = json_encode($this->paidWebhookPayload($checkout), JSON_THROW_ON_ERROR);
        $alteredBody = str_replace(',"livemode":false', ', "livemode":false', $signedBody);
        $timestamp = (string) now()->timestamp;
        $signature = $this->webhookSignature($timestamp, $signedBody);

        $this->postSignedWebhook($alteredBody, $timestamp, $signature)
            ->assertUnauthorized();

        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_rejects_live_mode_and_wrong_amount_events(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();
        $livePayload = $this->paidWebhookPayload($checkout);
        $livePayload['data']['livemode'] = true;

        $this->sendSignedPayload($livePayload)->assertUnprocessable();

        $wrongAmountPayload = $this->paidWebhookPayload($checkout);
        $wrongAmountPayload['data']['data']['attributes']['payments'][0]['attributes']['amount'] = 10001;

        $this->sendSignedPayload($wrongAmountPayload)->assertUnprocessable();

        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
            'paid_payment_id' => null,
        ]);
    }

    public function test_webhook_requires_a_recorded_checkout_session_and_matching_reference(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();
        $payload = $this->paidWebhookPayload($checkout);
        $payload['data']['data']['attributes']['reference_number'] = 'DENTALCARE-TEST-not-recorded';

        $this->sendSignedPayload($payload)->assertNotFound();

        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
        ]);
    }

    public function test_unrelated_signed_webhook_event_is_acknowledged_without_processing(): void
    {
        $this->configureWebhookSecret();
        $checkout = $this->createPendingTestCheckout();
        $payload = $this->paidWebhookPayload($checkout);
        $payload['data']['type'] = 'checkout_session.payment.failed';

        $this->sendSignedPayload($payload)
            ->assertOk()
            ->assertExactJson(['received' => true, 'processed' => false]);

        $this->assertDatabaseHas('paymongo_test_checkouts', [
            'id' => $checkout->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_fails_closed_without_its_signing_secret(): void
    {
        Config::set('services.paymongo.webhook_secret', null);

        $this->post(route('paymongo.test.webhook'), [], ['Paymongo-Signature' => ''])
            ->assertStatus(503);
    }

    private function configureWebhookSecret(): void
    {
        Config::set('services.paymongo.webhook_secret', 'whsk_test_example');
    }

    private function createPendingTestCheckout(): PayMongoTestCheckout
    {
        return PayMongoTestCheckout::query()->create([
            'reference_number' => 'DENTALCARE-TEST-test-reference',
            'checkout_session_id' => 'cs_test_checkout_123',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paidWebhookPayload(PayMongoTestCheckout $checkout): array
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
                                'id' => 'pay_test_payment_123',
                                'attributes' => [
                                    'amount' => 10000,
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

    private function configureTestKey(): void
    {
        Config::set('services.paymongo.secret', 'sk_test_example');
        Config::set('services.paymongo.base_url', 'https://api.paymongo.com/v2');
    }
}
