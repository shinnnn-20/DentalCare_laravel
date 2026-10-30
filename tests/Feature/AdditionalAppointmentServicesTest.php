<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdditionalAppointmentServicesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('clinicStaffRoles')]
    public function test_admin_and_staff_add_services_to_an_existing_bill_without_changing_original_items_or_payments(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $patient = Patient::factory()->create();
        $originalService = Service::factory()->create([
            'name' => 'Original Cleaning',
            'price' => 1000,
        ]);
        $appointment = Appointment::factory()->for($patient)->for($originalService)->create([
            'status' => 'completed',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => 1000,
            'discount' => 100,
            'total' => 900,
            'payment_status' => 'paid',
        ]);
        $originalItem = BillItem::factory()->for($bill)->for($originalService)->create([
            'service_name' => 'Original Cleaning',
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);
        $payment = Payment::factory()->for($bill)->create([
            'amount' => 900,
            'applied_amount' => 900,
        ]);
        $additionalService = Service::factory()->create([
            'name' => 'Additional X-ray',
            'price' => 250.50,
        ]);
        $anotherService = Service::factory()->create([
            'name' => 'Additional Fluoride',
            'price' => 150,
        ]);

        $this->actingAs($actor)
            ->get(route('clinic.bills.additional-services.create', $bill))
            ->assertOk()
            ->assertSee('Original Cleaning')
            ->assertSee('Additional X-ray')
            ->assertSee('name="service_ids[]"', false);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [$additionalService->id, $anotherService->id],
        ])->assertRedirect(route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id)
            ->assertSessionHas('status', 'Additional services added to the bill.');

        $this->assertSame(3, $bill->items()->count());
        $this->assertDatabaseHas('bill_items', [
            'id' => $originalItem->id,
            'bill_id' => $bill->id,
            'service_id' => $originalService->id,
            'service_name' => 'Original Cleaning',
            'subtotal' => '1000.00',
        ]);
        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->id,
            'service_id' => $additionalService->id,
            'service_name' => 'Additional X-ray',
            'is_add_on' => true,
            'unit_price' => '250.50',
            'subtotal' => '250.50',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'subtotal' => '1400.50',
            'discount' => '100.00',
            'total' => '1300.50',
            'payment_status' => 'partially_paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'bill_id' => $bill->id,
            'applied_amount' => '900.00',
        ]);
        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'additional_services_added',
            'module' => 'billing',
            'record_type' => 'Bill',
            'record_id' => (string) $bill->id,
        ]);

        $audit = AuditLog::query()->where('action', 'additional_services_added')->firstOrFail();
        $this->assertStringContainsString('Additional X-ray (#'.$additionalService->id.') at ₱250.50', $audit->description);
        $this->assertStringContainsString('Additional Fluoride (#'.$anotherService->id.') at ₱150.00', $audit->description);
    }

    public function test_add_on_services_are_identified_in_staff_patient_and_receipt_views(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = Patient::factory()->create();
        $originalService = Service::factory()->create(['name' => 'Original Cleaning', 'price' => 1000]);
        $appointment = Appointment::factory()->for($patient)->for($originalService)->create([
            'status' => 'completed',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        BillItem::factory()->for($bill)->for($originalService)->create([
            'service_name' => 'Original Cleaning',
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);
        $payment = Payment::factory()->for($bill)->create([
            'amount' => 400,
            'applied_amount' => 400,
        ]);
        $receipt = Receipt::factory()->for($payment)->create([
            'receipt_number' => 'AR-ADDON-0001',
        ]);
        $additionalService = Service::factory()->create([
            'name' => 'Additional X-ray',
            'price' => 250.50,
        ]);

        $this->actingAs($admin)
            ->post(route('clinic.bills.additional-services.store', $bill), [
                'service_ids' => [$additionalService->id],
            ])
            ->assertRedirect(route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id);

        $this->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertSee('Original Services')
            ->assertSee('Add-on Services')
            ->assertSee('Add-on Service')
            ->assertSee('Additional X-ray')
            ->assertSee('250.50')
            ->assertSee('1,250.50')
            ->assertSee('400.00')
            ->assertSee('850.50');

        $this->get(route('receipts.show', $receipt))
            ->assertOk()
            ->assertSee('Original Services')
            ->assertSee('Add-on Services')
            ->assertSee('Add-on Service')
            ->assertSee('Additional X-ray')
            ->assertSee('250.50')
            ->assertSee('1,250.50')
            ->assertSee('400.00')
            ->assertSee('850.50');

        $this->actingAs($patient->user)
            ->get(route('patient.billing'))
            ->assertOk()
            ->assertSee('Original Services')
            ->assertSee('Add-on Services')
            ->assertSee('Add-on Service')
            ->assertSee('Additional X-ray')
            ->assertSee('250.50')
            ->assertSee('1,250.50')
            ->assertSee('400.00')
            ->assertSee('850.50');

        $this->get(route('patient.billing.receipts.preview', ['bill' => $bill, 'receipt' => $receipt]))
            ->assertOk()
            ->assertSee('Original Services')
            ->assertSee('Add-on Services')
            ->assertSee('Add-on Service')
            ->assertSee('Additional X-ray')
            ->assertSee('250.50')
            ->assertSee('1,250.50')
            ->assertSee('400.00')
            ->assertSee('850.50')
            ->assertSee('ACKNOWLEDGEMENT RECEIPT (AR) ONLY');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'bill_id' => $bill->id,
            'applied_amount' => '400.00',
        ]);
        $this->assertSame('1250.50', $bill->fresh()->total);
    }

    public function test_adding_a_service_already_on_the_bill_is_rejected_without_changing_the_bill(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $appointment = Appointment::factory()->for($patient)->for($service)->create(['status' => 'billed']);
        $bill = Bill::factory()->for($patient)->for($appointment)->create();
        BillItem::factory()->for($bill)->for($service)->create();
        $snapshotService = Service::factory()->create(['name' => 'Archived X-ray']);
        BillItem::factory()->for($bill)->create([
            'service_id' => null,
            'service_name' => $snapshotService->name,
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.bills.additional-services.store', $bill), [
                'service_ids' => [$service->id, $snapshotService->id],
            ])
            ->assertSessionHasErrors('service_ids');

        $this->assertSame(2, $bill->items()->count());
        $this->assertSame('1000.00', $bill->fresh()->total);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'additional_services_added']);
    }

    public function test_fixed_price_add_on_cannot_be_overridden_and_inactive_services_are_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->create(['status' => 'billed']);
        $bill = Bill::factory()->for($patient)->for($appointment)->create();
        BillItem::factory()->for($bill)->create();
        $service = Service::factory()->create(['price' => 325]);

        $this->actingAs($staff)
            ->post(route('clinic.bills.additional-services.store', $bill), [
                'service_ids' => [$service->id],
                'items' => [
                    $service->id => [
                        'final_price' => '1.00',
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.'.$service->id.'.final_price');

        $service->update(['is_active' => false]);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [$service->id],
        ])->assertSessionHasErrors('service_ids');

        $this->assertSame(1, $bill->items()->count());
        $this->assertSame('1000.00', $bill->fresh()->total);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'additional_services_added']);
    }

    public function test_variable_fee_additional_service_requires_staff_confirmation(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->create(['status' => 'billed']);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        BillItem::factory()->for($bill)->create();
        $service = Service::create([
            'name' => 'Variable Add-on',
            'category' => 'Other Services',
            'price' => 0,
            'price_display' => '₱500–₱800',
            'requires_price_confirmation' => true,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('clinic.bills.additional-services.create', $bill))
            ->assertOk()
            ->assertSee('₱500–₱800')
            ->assertSee('name="items['.$service->id.'][final_price]"', false);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [$service->id],
        ])->assertSessionHasErrors([
            'items.'.$service->id.'.final_price',
            'items.'.$service->id.'.confirm_final_price',
        ]);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [$service->id],
            'items' => [
                $service->id => [
                    'final_price' => '650.25',
                    'confirm_final_price' => '1',
                ],
            ],
        ])->assertRedirect(route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id);

        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->id,
            'service_id' => $service->id,
            'unit_price' => '650.25',
            'subtotal' => '650.25',
        ]);
        $this->assertSame('1650.25', $bill->fresh()->total);
    }

    public function test_only_staff_and_admin_can_add_services_and_cancelled_appointments_are_not_eligible(): void
    {
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::factory()->for($patientUser)->create();
        $appointment = Appointment::factory()->for($patient)->create(['status' => 'cancelled']);
        $bill = Bill::factory()->for($patient)->for($appointment)->create();

        $this->actingAs($patientUser)
            ->get(route('clinic.bills.additional-services.create', $bill))
            ->assertForbidden();

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('clinic.bills.additional-services.create', $bill))
            ->assertStatus(422);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [Service::factory()->create()->id],
        ])->assertStatus(422);

        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame(0, $bill->items()->count());
    }

    public static function clinicStaffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'staff' => ['staff'],
        ];
    }
}
