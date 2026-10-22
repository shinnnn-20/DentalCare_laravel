<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PayMongoBillCheckout;
use App\Models\Receipt;
use App\Models\RfidCard;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MultipleTreatmentsAndPayMongoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test A — Multiple treatments:
     * - Book an appointment with three different treatments.
     * - Confirm that all three treatments appear in the appointment summary.
     * - Confirm that the estimated total equals the sum of the selected treatment prices.
     * - Confirm that staff can create one bill containing all applicable treatments.
     */
    public function test_a_multiple_treatments_appointment_booking_and_billing(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00', config('clinic.timezone')));

        $patientUser = User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'role' => 'patient',
        ]);
        $patient = Patient::factory()->for($patientUser)->create([
            'patient_number' => 'P-2026-00099',
            'emergency_contact' => '09171234567',
        ]);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Create the three treatments as specified in the example:
        // Consultation: ₱500, Dental Cleaning: ₱1,000, Dental Filling: ₱1,800
        $consultation = Service::create([
            'name' => 'Consultation',
            'category' => 'Consultation and Basic Services',
            'price' => 500.00,
            'duration_minutes' => 30,
            'requires_price_confirmation' => false,
            'is_active' => true,
        ]);
        $cleaning = Service::create([
            'name' => 'Dental Cleaning',
            'category' => 'Oral Prophylaxis and Preventive Treatments',
            'price' => 1000.00,
            'duration_minutes' => 30,
            'requires_price_confirmation' => false,
            'is_active' => true,
        ]);
        $filling = Service::create([
            'name' => 'Dental Filling',
            'category' => 'Restorative Treatments and Fillings',
            'price' => 1800.00,
            'duration_minutes' => 30,
            'requires_price_confirmation' => false,
            'is_active' => true,
        ]);
        Service::create([
            'name' => 'Complete Denture',
            'category' => 'Complete Dentures',
            'price' => 0,
            'price_display' => '₱45,000 and up',
            'duration_minutes' => 30,
            'requires_price_confirmation' => true,
            'is_active' => true,
        ]);

        // Patient visits appointments index and sees category options and summary container
        $this->actingAs($patientUser)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('data-appointment-summary-box', false)
            ->assertSee('data-selected-treatments-list', false)
            ->assertSee('data-service-category-card', false)
            ->assertSee('data-category-selections', false)
            ->assertSee('data-price-display="₱45,000 and up"', false)
            ->assertSee('data-combined-estimated-cost', false)
            ->assertSee('Consultation — ₱500.00')
            ->assertSee('Dental Cleaning — ₱1,000.00')
            ->assertSee('Dental Filling — ₱1,800.00');

        // Book appointment with all 3 treatments
        $response = $this->actingAs($patientUser)->post(route('appointments.store'), [
            'branch_id' => Branch::query()->where('slug', 'general-trias')->firstOrFail()->id,
            'service_ids' => [$consultation->id, $consultation->id, $cleaning->id, $filling->id],
            'appointment_date' => '2026-10-05',
            'appointment_time' => '10:00',
            'notes' => 'Need consultation, cleaning, and filling.',
        ]);

        $response->assertRedirectToRoute('appointments.index');
        $appointment = Appointment::query()->sole();

        // Confirm appointment services relation contains all 3 treatments
        $this->assertCount(3, $appointment->services);
        $this->assertTrue($appointment->services->contains('id', $consultation->id));
        $this->assertTrue($appointment->services->contains('id', $cleaning->id));
        $this->assertTrue($appointment->services->contains('id', $filling->id));

        // Confirm estimated total equals ₱500 + ₱1,000 + ₱1,800 = ₱3,300
        $this->assertSame(3300.00, $appointment->estimatedTotal());
        $unloadedAppointment = Appointment::query()->findOrFail($appointment->id);
        $this->assertSame('Consultation, Dental Cleaning, Dental Filling', $unloadedAppointment->treatmentNames());
        $this->assertSame(3300.00, $unloadedAppointment->estimatedTotal());

        // Confirm appointments index displays all three treatments
        $this->actingAs($patientUser)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Consultation')
            ->assertSee('Dental Cleaning')
            ->assertSee('Dental Filling');
        $this->actingAs($patientUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Consultation, Dental Cleaning, Dental Filling');

        // Advance through workflow: staff approves, checks in, starts consult, doctor adds dental record
        $this->actingAs($staff)->post(route('appointments.transition', [$appointment, 'approve']))->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->assertSame('approved', $appointment->status);
        $rfidCard = RfidCard::factory()->for($patient)->create(['uid' => 'MARIA-TEST-001']);
        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $rfidCard->uid])
            ->assertOk()
            ->assertSee('Consultation, Dental Cleaning, Dental Filling');

        $this->actingAs($staff)->post(route('clinic.queue.check-in', $appointment))->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->assertSame('checked_in', $appointment->status);

        $this->actingAs($staff)->post(route('appointments.transition', [$appointment, 'call']))->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('appointments.transition', [$appointment, 'consult']))->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->assertSame('in_consultation', $appointment->status);

        $this->actingAs($admin)->post(route('clinic.records.store', $appointment), [
            'diagnosis' => 'Multiple dental needs',
            'treatment' => 'Consultation, prophylaxis, composite restoration',
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)
            ->get(route('clinic.patients.show', $patient))
            ->assertSee('Consultation, Dental Cleaning, Dental Filling')
            ->assertSee('90 minutes');

        // Staff opens bill creation form
        $this->actingAs($staff)
            ->get(route('clinic.bills.create', $appointment))
            ->assertOk()
            ->assertSee('Selected Treatments (3)')
            ->assertSee('Consultation')
            ->assertSee('Dental Cleaning')
            ->assertSee('Dental Filling')
            ->assertSee('₱500.00')
            ->assertSee('₱1,000.00')
            ->assertSee('₱1,800.00');

        // Staff creates one bill containing all applicable treatments with ₱100 discount
        $this->actingAs($staff)->post(route('clinic.bills.store', $appointment), [
            'discount' => '100.00',
        ])->assertRedirectToRoute('clinic.billing.index');

        $bill = Bill::query()->sole();
        $this->assertSame($appointment->id, $bill->appointment_id);
        $this->assertSame($patient->id, $bill->patient_id);
        $this->assertSame('3300.00', $bill->subtotal);
        $this->assertSame('100.00', $bill->discount);
        $this->assertSame('3200.00', $bill->total);
        $this->assertSame('unpaid', $bill->payment_status);

        // Confirm 3 separate bill items exist with correct unit prices and subtotals
        $this->assertCount(3, $bill->items);
        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->id,
            'service_id' => $consultation->id,
            'service_name' => 'Consultation',
            'quantity' => 1,
            'unit_price' => '500.00',
            'subtotal' => '500.00',
        ]);
        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->id,
            'service_id' => $cleaning->id,
            'service_name' => 'Dental Cleaning',
            'quantity' => 1,
            'unit_price' => '1000.00',
            'subtotal' => '1000.00',
        ]);
        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->id,
            'service_id' => $filling->id,
            'service_name' => 'Dental Filling',
            'quantity' => 1,
            'unit_price' => '1800.00',
            'subtotal' => '1800.00',
        ]);

        // Confirm patient portal displays the three bill items
        $this->actingAs($patientUser)
            ->get(route('patient.billing'))
            ->assertOk()
            ->assertSee('Consultation × 1')
            ->assertSee('Dental Cleaning × 1')
            ->assertSee('Dental Filling × 1')
            ->assertSee('₱3,200.00');
    }

    public function test_staff_can_assign_an_rfid_card_to_a_patient(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::factory()->for($patientUser)->create();

        $this->actingAs($staff)
            ->from(route('clinic.rfid.index'))
            ->post(route('clinic.rfid.assign', $patient), ['uid' => 'maria-test-001'])
            ->assertRedirect(route('clinic.rfid.index'))
            ->assertSessionHas('status', 'RFID card assigned.');

        $this->assertDatabaseHas('rfid_cards', [
            'patient_id' => $patient->id,
            'uid' => 'MARIA-TEST-001',
            'is_active' => true,
        ]);
    }

    /**
     * Test B — Different patients:
     * - Initiate a test checkout for Patient A.
     * - Initiate a separate test checkout for Patient B.
     * - Confirm that each checkout uses the correct patient's information and bill reference.
     * - Confirm that a successful payment for one patient does not change another patient's bill.
     */
    public function test_b_different_patients_receive_dynamic_checkout_sessions_and_separate_bills(): void
    {
        $this->configurePayMongo();
        Http::preventStrayRequests();

        $patientAUser = User::factory()->create(['name' => 'Patient Alice Johnson', 'email' => 'alice@example.com', 'role' => 'patient']);
        $patientA = Patient::factory()->for($patientAUser)->create(['emergency_contact' => '09181111111']);
        $billA = Bill::factory()->for($patientA)->create([
            'bill_number' => 'B-2026-000101',
            'subtotal' => '3300.00',
            'discount' => '0.00',
            'total' => '3300.00',
            'payment_status' => 'unpaid',
        ]);

        $patientBUser = User::factory()->create(['name' => 'Patient Bob Smith', 'email' => 'bob@example.com', 'role' => 'patient']);
        $patientB = Patient::factory()->for($patientBUser)->create(['emergency_contact' => '09182222222']);
        $billB = Bill::factory()->for($patientB)->create([
            'bill_number' => 'B-2026-000202',
            'subtotal' => '1500.00',
            'discount' => '0.00',
            'total' => '1500.00',
            'payment_status' => 'unpaid',
        ]);

        $staff = User::factory()->create(['role' => 'staff']);

        // Mock PayMongo responses
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::sequence()
                ->push([
                    'data' => [
                        'id' => 'cs_alice_test_session',
                        'attributes' => [
                            'checkout_url' => 'https://checkout.paymongo.com/alice-session',
                        ],
                    ],
                ])
                ->push([
                    'data' => [
                        'id' => 'cs_bob_test_session',
                        'attributes' => [
                            'checkout_url' => 'https://checkout.paymongo.com/bob-session',
                        ],
                    ],
                ]),
        ]);

        // 1. Initiate checkout for Bill A (Patient Alice)
        $responseA = $this->actingAs($staff)->post(route('clinic.billing.paymongo.store', $billA));
        $responseA->assertRedirect('https://checkout.paymongo.com/alice-session');

        // 2. Initiate checkout for Bill B (Patient Bob)
        $responseB = $this->actingAs($staff)->post(route('clinic.billing.paymongo.store', $billB));
        $responseB->assertRedirect('https://checkout.paymongo.com/bob-session');

        $checkoutA = $billA->payMongoBillCheckouts()->sole();
        $checkoutB = $billB->payMongoBillCheckouts()->sole();

        // Verify sent requests
        Http::assertSent(function (Request $request) use ($billA, $checkoutA): bool {
            $data = $request->data()['data']['attributes'] ?? [];

            return ($data['billing']['name'] ?? null) === 'Patient Alice Johnson'
                && ($data['billing']['email'] ?? null) === 'alice@example.com'
                && ($data['billing']['phone'] ?? null) === '09181111111'
                && ($data['description'] ?? null) === 'Payment for Bill '.$billA->bill_number.' - Patient Alice Johnson'
                && ($data['reference_number'] ?? null) === $checkoutA->reference_number
                && ($data['line_items'][0]['amount'] ?? null) === 330000;
        });

        Http::assertSent(function (Request $request) use ($billB, $checkoutB): bool {
            $data = $request->data()['data']['attributes'] ?? [];

            return ($data['billing']['name'] ?? null) === 'Patient Bob Smith'
                && ($data['billing']['email'] ?? null) === 'bob@example.com'
                && ($data['billing']['phone'] ?? null) === '09182222222'
                && ($data['description'] ?? null) === 'Payment for Bill '.$billB->bill_number.' - Patient Bob Smith'
                && ($data['reference_number'] ?? null) === $checkoutB->reference_number
                && ($data['line_items'][0]['amount'] ?? null) === 150000;
        });

        $this->assertNotSame($checkoutA->reference_number, $checkoutB->reference_number);
        $this->assertSame('cs_alice_test_session', $checkoutA->checkout_session_id);
        $this->assertSame('cs_bob_test_session', $checkoutB->checkout_session_id);

        // 3. Confirm that a successful payment for Patient A does not change Patient B's bill
        $webhookPayloadA = $this->paidWebhookPayload($checkoutA, 'pay_alice_123');
        $this->sendSignedPayload($webhookPayloadA)->assertOk();

        // Bill A should now be paid
        $billA->refresh();
        $this->assertSame('paid', $billA->payment_status);
        $this->assertDatabaseHas('payments', [
            'bill_id' => $billA->id,
            'provider_transaction_id' => 'pay_alice_123',
            'amount' => '3300.00',
        ]);

        // Bill B MUST remain unpaid and untouched
        $billB->refresh();
        $this->assertSame('unpaid', $billB->payment_status);
        $this->assertSame(0, $billB->payments()->count());
    }

    /**
     * Test C — GCash-only checkout:
     * - Open the online payment page.
     * - Confirm that GCash is the only requested online payment method.
     * - Confirm that card payment options are not offered through the website's checkout configuration.
     * - Confirm that the existing PayMongo test checkout continues working.
     */
    public function test_c_gcash_only_checkout_configuration_and_no_card_payment_options(): void
    {
        $this->configurePayMongo();
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::factory()->for($patientUser)->create();
        $bill = Bill::factory()->for($patient)->create([
            'total' => '2000.00',
            'payment_status' => 'unpaid',
        ]);

        // Check the billing index page
        $response = $this->actingAs($staff)->get(route('clinic.billing.index'));
        $response->assertOk();

        // Confirm manual card option is removed from the payment methods select
        $response->assertDontSee('value="card"', false);
        $response->assertDontSee('Card (manual)');
        $response->assertSee('value="cash"', false);
        $response->assertSee('value="gcash"', false);
        $response->assertSee('value="other"', false);

        // Confirm PayMongo checkout session creation requests ONLY GCash
        Http::fake([
            'api.paymongo.com/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_gcash_only_test',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/gcash-only-session',
                    ],
                ],
            ]),
        ]);

        $this->post(route('clinic.billing.paymongo.store', $bill))
            ->assertRedirect('https://checkout.paymongo.com/gcash-only-session');

        Http::assertSent(function (Request $request): bool {
            $paymentMethodTypes = $request->data()['data']['attributes']['payment_method_types'] ?? [];

            return $paymentMethodTypes === ['gcash'];
        });

        // Confirm existing PayMongo admin standalone test checkout continues working
        $adminCheckoutResponse = $this->actingAs($admin)->post(route('paymongo.test.store'));
        $adminCheckoutResponse->assertRedirect('https://checkout.paymongo.com/gcash-only-session');
    }

    /**
     * Test D — Payment recording:
     * - Complete a successful test payment for a bill.
     * - Confirm that the payment is recorded against the correct patient and bill.
     * - Confirm that the outstanding balance decreases correctly.
     * - Confirm that the bill becomes Paid only when its confirmed outstanding balance reaches zero.
     * - Confirm that duplicate webhook events do not create duplicate payment records or reduce the balance twice.
     */
    public function test_d_payment_recording_updates_balance_and_prevents_duplicate_webhooks(): void
    {
        $this->configurePayMongo();
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::factory()->for($patientUser)->create();
        $bill = Bill::factory()->for($patient)->create([
            'subtotal' => '3300.00',
            'discount' => '0.00',
            'total' => '3300.00',
            'payment_status' => 'unpaid',
        ]);

        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-d-scenario',
            'checkout_session_id' => 'cs_test_d_session',
            'checkout_url' => 'https://checkout.paymongo.com/d-session',
            'amount_centavos' => 330000,
            'status' => 'pending',
        ]);

        // Send successful payment webhook
        $payload = $this->paidWebhookPayload($checkout, 'pay_d_test_999');
        $this->sendSignedPayload($payload)->assertOk();

        // Confirm payment recorded against correct bill and patient
        $this->assertDatabaseCount('payments', 1);
        $payment = Payment::query()->sole();
        $this->assertSame($bill->id, $payment->bill_id);
        $this->assertSame('gcash', $payment->method);
        $this->assertSame('paymongo', $payment->provider);
        $this->assertSame('pay_d_test_999', $payment->provider_transaction_id);
        $this->assertSame('3300.00', $payment->amount);
        $this->assertSame('3300.00', $payment->applied_amount);
        $this->assertTrue($payment->is_test);

        // Confirm receipt created
        $this->assertDatabaseCount('receipts', 1);
        $this->assertSame($payment->id, Receipt::query()->sole()->payment_id);

        // Confirm bill status is paid (balance is 0)
        $bill->refresh();
        $this->assertSame('paid', $bill->payment_status);
        $paidTotal = (float) $bill->payments()->sum('applied_amount');
        $this->assertSame(3300.00, $paidTotal);
        $balance = (float) $bill->total - $paidTotal;
        $this->assertSame(0.00, $balance);

        // Confirm checkout session marked paid
        $checkout->refresh();
        $this->assertSame('paid', $checkout->status);
        $this->assertSame('pay_d_test_999', $checkout->paid_payment_id);

        // Send duplicate webhook event
        $duplicateResponse = $this->sendSignedPayload($payload);
        $duplicateResponse->assertOk();

        // Confirm duplicate webhook DOES NOT create duplicate payment record or reduce balance twice
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipts', 1);
        $bill->refresh();
        $this->assertSame('paid', $bill->payment_status);
        $this->assertSame(3300.00, (float) $bill->payments()->sum('applied_amount'));
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
    private function paidWebhookPayload(PayMongoBillCheckout $checkout, string $paymentId = 'pay_bill_test_123'): array
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
                                'id' => $paymentId,
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

        return $this->call(
            'POST',
            route('paymongo.test.webhook'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$this->webhookSignature($timestamp, $rawPayload)},li=",
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
