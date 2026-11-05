<?php

namespace Tests\Feature;

use App\Models\Appointment;
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
    public function test_admin_and_staff_cannot_add_services_to_a_paid_bill(string $role): void
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
            ->assertSee('This bill is fully paid.')
            ->assertDontSee('name="service_ids[]"', false);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [$additionalService->id, $anotherService->id],
            'confirm_add_ons' => '1',
        ])->assertSessionHasErrors([
            'service_ids' => 'Additional services cannot be added to a paid bill.',
        ]);

        $this->assertSame(1, $bill->items()->count());
        $this->assertDatabaseHas('bill_items', [
            'id' => $originalItem->id,
            'bill_id' => $bill->id,
            'service_id' => $originalService->id,
            'service_name' => 'Original Cleaning',
            'subtotal' => '1000.00',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'subtotal' => '1000.00',
            'discount' => '100.00',
            'total' => '900.00',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'bill_id' => $bill->id,
            'applied_amount' => '900.00',
        ]);
        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'additional_services_added']);

        $this->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertSee('Add services · Paid')
            ->assertDontSee(route('clinic.bills.additional-services.create', $bill));
    }

    public function test_add_on_service_keeps_the_original_percentage_discount_and_full_price_without_changing_payment_history(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $service = Service::factory()->create(['name' => 'Original Cleaning', 'price' => 1000]);
        $appointment = Appointment::factory()->for($patient)->for($service)->create([
            'status' => 'completed',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => '1000.00',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'discount' => '100.00',
            'total' => '900.00',
            'payment_status' => 'partially_paid',
        ]);
        $payment = Payment::factory()->for($bill)->create([
            'amount' => '400.00',
            'applied_amount' => '400.00',
        ]);
        $addOn = Service::factory()->create(['name' => 'Additional X-ray', 'price' => 250.50]);

        $this->actingAs($staff)
            ->post(route('clinic.bills.additional-services.store', $bill), [
                'service_ids' => [$addOn->id],
                'confirm_add_ons' => '1',
            ])
            ->assertRedirect(route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id);

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'subtotal' => '1250.50',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'discount' => '100.00',
            'total' => '1150.50',
            'payment_status' => 'partially_paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'bill_id' => $bill->id,
            'applied_amount' => '400.00',
        ]);
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
            'paid_at' => '2026-11-01 12:19:00',
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
                'confirm_add_ons' => '1',
            ])
            ->assertRedirect(route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id);

        $this->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertSee('Original Services')
            ->assertSee('Add-on Services')
            ->assertSee('Add-on Service')
            ->assertSee('Additional X-ray')
            ->assertSee('Nov 1, 2026 8:19 PM')
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
            ->assertSee('November 1, 2026 · 8:19 PM')
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
            ->assertSee('850.50')
            ->assertSee('Nov 1, 2026 8:19 PM');

        $this->get(route('patient.billing.receipts.preview', ['bill' => $bill, 'receipt' => $receipt]))
            ->assertOk()
            ->assertSee('Original Services')
            ->assertSee('Add-on Services')
            ->assertSee('Add-on Service')
            ->assertSee('Additional X-ray')
            ->assertSee('November 1, 2026 · 8:19 PM')
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

    public function test_appointment_records_show_flagged_add_ons_and_a_clear_empty_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = Patient::factory()->create();
        $bookedService = Service::factory()->create(['name' => 'Booked Cleaning']);
        $appointmentWithAddOns = Appointment::factory()
            ->for($patient)
            ->for($bookedService)
            ->create(['status' => 'completed']);
        $billWithAddOns = Bill::factory()->for($patient)->for($appointmentWithAddOns)->create();
        BillItem::factory()->for($billWithAddOns)->for($bookedService)->create([
            'service_name' => 'Booked Cleaning',
            'is_add_on' => false,
        ]);
        BillItem::factory()->for($billWithAddOns)->create([
            'service_name' => 'Add-on X-ray',
            'is_add_on' => true,
        ]);
        BillItem::factory()->for($billWithAddOns)->create([
            'service_name' => 'Add-on Fluoride',
            'is_add_on' => true,
        ]);
        $appointmentWithoutAddOns = Appointment::factory()
            ->for($patient)
            ->for(Service::factory()->create(['name' => 'Booked Checkup']))
            ->create(['status' => 'completed']);
        $billWithoutAddOns = Bill::factory()->for($patient)->for($appointmentWithoutAddOns)->create();
        BillItem::factory()->for($billWithoutAddOns)->create([
            'service_name' => 'Booked Checkup',
            'is_add_on' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('clinic.appointment-records', ['appointment_id' => $appointmentWithAddOns->id]))
            ->assertOk()
            ->assertSee('Original booked services')
            ->assertSee('Booked Cleaning')
            ->assertSee('Add-on Services')
            ->assertSee('Add-on X-ray')
            ->assertSee('Add-on Fluoride')
            ->assertDontSee('No add-on services.');

        $this->get(route('clinic.appointment-records', ['appointment_id' => $appointmentWithoutAddOns->id]))
            ->assertOk()
            ->assertSee('Booked Checkup')
            ->assertSee('Add-on Services')
            ->assertSee('No add-on services.');
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
                'confirm_add_ons' => '1',
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
                'confirm_add_ons' => '1',
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
            'confirm_add_ons' => '1',
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
            'category' => 'Cosmetic Treatments',
            'price' => 0,
            'price_display' => '₱500–₱800',
            'requires_price_confirmation' => true,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('clinic.bills.additional-services.create', $bill))
            ->assertOk()
            ->assertSee('₱500–₱800')
            ->assertSee('data-addon-category="Cosmetic Treatments"', false)
            ->assertSee('data-addon-price-panel="'.$service->id.'"', false)
            ->assertSee('name="items['.$service->id.'][final_price]"', false);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [$service->id],
            'confirm_add_ons' => '1',
        ])->assertSessionHasErrors([
            'items.'.$service->id.'.final_price',
            'items.'.$service->id.'.confirm_final_price',
        ]);

        $this->post(route('clinic.bills.additional-services.store', $bill), [
            'service_ids' => [$service->id],
            'confirm_add_ons' => '1',
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

    public function test_add_on_services_require_confirmation_before_updating_the_existing_bill(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->create(['status' => 'billed']);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => '1000.00',
            'total' => '1000.00',
        ]);
        BillItem::factory()->for($bill)->create();
        $service = Service::factory()->create(['price' => 250]);

        $this->actingAs($staff)
            ->post(route('clinic.bills.additional-services.store', $bill), [
                'service_ids' => [$service->id],
            ])
            ->assertSessionHasErrors('confirm_add_ons');

        $this->assertSame(1, $bill->items()->count());
        $this->assertSame('1000.00', $bill->fresh()->total);
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
            'confirm_add_ons' => '1',
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
