<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientReceiptPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_billing_links_to_persisted_receipt_previews_for_each_bill(): void
    {
        $patient = Patient::factory()->create();
        [$firstBill, $firstReceipt] = $this->createBillReceipt($patient, 'AR-2026-000001');
        [$secondBill, $secondReceipt] = $this->createBillReceipt($patient, 'AR-2026-000002');
        [$otherPatientBill, $otherPatientReceipt] = $this->createBillReceipt(
            Patient::factory()->create(),
            'AR-2026-000003',
        );

        $this->actingAs($patient->user)
            ->get(route('patient.billing'))
            ->assertSee('Preview Payment Receipt')
            ->assertSee(route('patient.billing.receipts.preview', ['bill' => $firstBill, 'receipt' => $firstReceipt]), false)
            ->assertSee(route('patient.billing.receipts.preview', ['bill' => $secondBill, 'receipt' => $secondReceipt]), false)
            ->assertDontSee(route('patient.billing.receipts.preview', ['bill' => $otherPatientBill, 'receipt' => $otherPatientReceipt]), false);

        $this->assertDatabaseCount('receipts', 3);
    }

    public function test_patient_can_preview_their_receipt_without_technical_payment_identifiers_or_print_controls(): void
    {
        $patient = Patient::factory()->create();
        [$bill, $receipt] = $this->createBillReceipt($patient, 'AR-2026-000004');

        $this->actingAs($patient->user)
            ->get(route('patient.billing.receipts.preview', ['bill' => $bill, 'receipt' => $receipt]))
            ->assertOk()
            ->assertSee('Payment Receipt Preview')
            ->assertSee('AR-2026-000004')
            ->assertSee('ACKNOWLEDGEMENT RECEIPT (AR) ONLY')
            ->assertSee('ACKNOWLEDGEMENT RECEIPT ONLY – This document acknowledges receipt of payment and is not an Official Receipt or a BIR-compliant Sales Invoice.')
            ->assertDontSee('TXN-AR-2026-000004')
            ->assertDontSee('paymongo-AR-2026-000004')
            ->assertDontSee('checkout-AR-2026-000004')
            ->assertDontSee('data-print-receipt', false)
            ->assertDontSee('Print / save PDF');
    }

    public function test_staff_receipt_uses_the_billing_print_layout_and_shows_payment_details(): void
    {
        $patient = Patient::factory()->create();
        [$bill, $receipt] = $this->createBillReceipt($patient, 'AR-2026-000009');
        BillItem::factory()->for($bill)->create([
            'service_name' => 'Consultation',
            'unit_price' => 500,
            'subtotal' => 500,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('receipts.show', $receipt))
            ->assertSee('bill-print-header', false)
            ->assertSee('bill-print-details', false)
            ->assertSee('bill-print-summary grid w-full', false)
            ->assertSee('data-print-receipt', false)
            ->assertSee('Services / Items')
            ->assertSee('Original Services')
            ->assertSee('Total Amount')
            ->assertSee('Amount Paid')
            ->assertSee('Payment details')
            ->assertSee('Payment method / provider')
            ->assertSee('AR-2026-000009');
    }

    public function test_patient_receipt_preview_returns_not_found_for_another_patients_bill(): void
    {
        $patient = Patient::factory()->create();
        [$bill, $receipt] = $this->createBillReceipt(Patient::factory()->create(), 'AR-2026-000005');

        $this->actingAs($patient->user)
            ->get(route('patient.billing.receipts.preview', ['bill' => $bill, 'receipt' => $receipt]))
            ->assertNotFound();
    }

    public function test_patient_receipt_preview_returns_not_found_when_receipt_belongs_to_a_different_bill(): void
    {
        $patient = Patient::factory()->create();
        [$requestedBill] = $this->createBillReceipt($patient, 'AR-2026-000006');
        [, $receipt] = $this->createBillReceipt($patient, 'AR-2026-000007');

        $this->actingAs($patient->user)
            ->get(route('patient.billing.receipts.preview', ['bill' => $requestedBill, 'receipt' => $receipt]))
            ->assertNotFound();
    }

    public function test_patient_cannot_use_the_unscoped_staff_receipt_route(): void
    {
        $patient = Patient::factory()->create();
        [, $receipt] = $this->createBillReceipt($patient, 'AR-2026-000008');

        $this->actingAs($patient->user)
            ->get(route('receipts.show', $receipt))
            ->assertNotFound();
    }

    /**
     * @return array{Bill, Receipt}
     */
    private function createBillReceipt(Patient $patient, string $receiptNumber): array
    {
        $bill = Bill::factory()->for($patient)->create();
        $payment = Payment::factory()->for($bill)->create([
            'transaction_id' => 'TXN-'.$receiptNumber,
            'provider' => 'paymongo',
            'provider_transaction_id' => 'paymongo-'.$receiptNumber,
            'provider_checkout_session_id' => 'checkout-'.$receiptNumber,
            'is_test' => true,
        ]);
        $receipt = Receipt::factory()->for($payment)->create([
            'receipt_number' => $receiptNumber,
        ]);

        return [$bill, $receipt];
    }
}
