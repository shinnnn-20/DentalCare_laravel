<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use App\Services\PayMongoBillPaymentReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_payment_does_not_complete_an_unfinished_visit(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        [$appointment, $bill] = $this->createBillableVisit('in_consultation', false);

        $this->actingAs($staff)
            ->post(route('clinic.payments.store', $bill), [
                'method' => 'cash',
                'amount' => '500.00',
                'applied_amount' => '500.00',
            ])
            ->assertRedirect();

        $this->assertSame('paid', $bill->fresh()->payment_status);
        $this->assertSame('in_consultation', $appointment->fresh()->status);
    }

    public function test_treatment_with_an_outstanding_balance_remains_billed(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        [$appointment, $bill] = $this->createBillableVisit();

        $this->actingAs($staff)
            ->post(route('clinic.payments.store', $bill), [
                'method' => 'cash',
                'amount' => '200.00',
                'applied_amount' => '200.00',
            ])
            ->assertRedirect();

        $this->assertSame('partially_paid', $bill->fresh()->payment_status);
        $this->assertSame('billed', $appointment->fresh()->status);
    }

    public function test_full_payment_after_treatment_automatically_completes_the_appointment_once(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        [$appointment, $bill] = $this->createBillableVisit();
        $queueEntry = QueueEntry::factory()->for($appointment)->create([
            'status' => 'in_consultation',
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.payments.store', $bill), [
                'method' => 'cash',
                'amount' => '500.00',
                'applied_amount' => '500.00',
            ])
            ->assertRedirect();

        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertSame('completed', $queueEntry->fresh()->status);
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('audit_logs', [
            'role' => 'system',
            'action' => 'appointment_completed',
            'record_id' => (string) $appointment->id,
        ]);

        app(AppointmentCompletionService::class)->sync($bill);

        $this->assertSame(1, AuditLog::query()->where('action', 'appointment_completed')->count());
    }

    #[DataProvider('terminalStatuses')]
    public function test_full_payment_does_not_change_terminal_appointment_statuses(string $status): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        [$appointment, $bill] = $this->createBillableVisit($status);

        $this->actingAs($staff)
            ->post(route('clinic.payments.store', $bill), [
                'method' => 'cash',
                'amount' => '500.00',
                'applied_amount' => '500.00',
            ])
            ->assertRedirect();

        $this->assertSame($status, $appointment->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'appointment_completed',
            'record_id' => (string) $appointment->id,
        ]);
    }

    public function test_appointment_completes_only_after_paymongo_confirmation_is_recorded(): void
    {
        [$appointment, $bill] = $this->createBillableVisit();
        $checkout = $bill->payMongoBillCheckouts()->create([
            'reference_number' => 'DENTALCARE-BILL-TEST-completion',
            'checkout_session_id' => 'cs_test_completion',
            'amount_centavos' => 50000,
            'status' => 'pending',
        ]);
        $reconciler = app(PayMongoBillPaymentReconciler::class);

        $this->assertNull($reconciler->paidPayment([
            [
                'id' => 'pay_pending_completion',
                'attributes' => [
                    'status' => 'pending',
                    'currency' => 'PHP',
                    'amount' => 50000,
                    'paid_at' => null,
                    'livemode' => false,
                    'source' => ['type' => 'gcash'],
                ],
            ],
        ]));
        $this->assertSame('billed', $appointment->fresh()->status);
        $this->assertSame(0, $bill->payments()->count());

        $paidPayment = $reconciler->paidPayment([
            [
                'id' => 'pay_confirmed_completion',
                'attributes' => [
                    'status' => 'paid',
                    'currency' => 'PHP',
                    'amount' => 50000,
                    'paid_at' => null,
                    'livemode' => false,
                    'source' => ['type' => 'gcash'],
                ],
            ],
        ]);
        $this->assertNotNull($paidPayment);

        $this->assertSame(
            'processed',
            $reconciler->process($checkout->reference_number, $checkout->checkout_session_id, $paidPayment),
        );

        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertSame(1, $bill->payments()->count());
        $this->assertSame('paid', $bill->fresh()->payment_status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function terminalStatuses(): array
    {
        return [
            'rejected' => ['rejected'],
            'cancelled' => ['cancelled'],
            'no show' => ['no_show'],
        ];
    }

    /**
     * @return array{Appointment, Bill}
     */
    private function createBillableVisit(string $status = 'billed', bool $includeDentalRecord = true): array
    {
        $patient = Patient::factory()->create();
        $service = Service::factory()->create([
            'name' => 'Dental Treatment',
            'price' => 500,
        ]);
        $appointment = Appointment::factory()->for($patient)->for($service)->create([
            'status' => $status,
        ]);

        if ($includeDentalRecord) {
            DentalRecord::factory()->for($patient)->for($appointment)->create();
        }

        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => '500.00',
            'total' => '500.00',
            'payment_status' => 'unpaid',
        ]);
        $bill->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'quantity' => 1,
            'unit_price' => '500.00',
            'subtotal' => '500.00',
        ]);

        return [$appointment, $bill];
    }
}
