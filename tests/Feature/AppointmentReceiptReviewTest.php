<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentReceiptReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_submit_a_gcash_receipt_without_a_preset_payment_amount(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 08:00:00', config('clinic.timezone')));
        Storage::fake('local');
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->create(['price' => '1001.01']);

        $this->actingAs($patient->user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('name="payment_method"', false)
            ->assertSee('name="payment_proof"', false)
            ->assertSee('data-gcash-receipt-panel', false)
            ->assertSee('images/gcash-deposit-qr.png')
            ->assertSee('No preset payment amount is required when booking.')
            ->assertDontSee('50%')
            ->assertSeeInOrder(['Notes', 'GCash receipt for appointment review']);

        $this->actingAs($patient->user)->post(route('appointments.store'), [
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-10-05',
            'payment_method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('receipt.png'),
            'notes' => 'Please confirm this booking.',
        ])->assertRedirectToRoute('appointments.index');

        $appointment = Appointment::query()->sole();

        $this->assertNull($appointment->deposit_amount);
        $this->assertSame('pending_review', $appointment->payment_proof_status);
        $this->assertSame('pending', $appointment->status);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'payment_method' => 'gcash',
            'notes' => 'Please confirm this booking.',
        ]);
        Storage::disk('local')->assertExists($appointment->payment_proof_path);
    }

    public function test_gcash_requires_a_receipt_but_not_a_positive_price_estimate(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 08:00:00', config('clinic.timezone')));
        Storage::fake('local');
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->create(['price' => '0.00']);
        $booking = [
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-10-05',
            'payment_method' => 'gcash',
        ];

        $this->actingAs($patient->user)
            ->post(route('appointments.store'), $booking)
            ->assertSessionHasErrors('payment_proof');

        $this->actingAs($patient->user)
            ->post(route('appointments.store'), [
                ...$booking,
                'payment_proof' => UploadedFile::fake()->image('receipt.png'),
            ])
            ->assertRedirectToRoute('appointments.index');

        $appointment = Appointment::query()->sole();
        $this->assertNull($appointment->deposit_amount);
        $this->assertSame('pending_review', $appointment->payment_proof_status);
        Storage::disk('local')->assertExists($appointment->payment_proof_path);
    }

    public function test_staff_must_verify_a_private_receipt_before_manually_approving_the_appointment(): void
    {
        Storage::fake('local');
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $proofPath = UploadedFile::fake()->image('receipt.png')->store('appointment-payment-proofs', 'local');
        $appointment = $this->createGcashAppointment($patient, $branch, $service, [
            'payment_proof_path' => $proofPath,
            'payment_proof_status' => 'pending_review',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => '900.00',
            'total' => '900.00',
            'payment_status' => 'unpaid',
        ]);
        $otherStaff = User::factory()->create(['role' => 'staff', 'branch_id' => $otherBranch->id]);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $this->actingAs($staff)
            ->post(route('clinic.appointment-capacities.store'), [
                'branch_id' => $branch->id,
                'appointment_date' => $appointment->starts_at->toDateString(),
                'daily_limit' => 10,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($otherStaff)
            ->get(route('appointments.payment-proof', $appointment))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get(route('clinic.appointment-records', [
                'branch_id' => $branch->id,
                'date' => $appointment->starts_at->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Payment: GCash')
            ->assertSee('Pending staff review')
            ->assertSee('View receipt screenshot')
            ->assertSee('Verify receipt')
            ->assertSee('Reject receipt');

        $this->actingAs($staff)
            ->get(route('appointments.payment-proof', $appointment))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->actingAs($staff)
            ->post(route('appointments.transition', [$appointment, 'approve']))
            ->assertSessionHasErrors('status');

        $this->actingAs($staff)
            ->patch(route('clinic.appointments.payment-proof.review', $appointment), [
                'status' => 'verified',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'payment_proof_status' => 'verified',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($staff)
            ->post(route('appointments.transition', [$appointment, 'approve']))
            ->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'approved',
        ]);
    }

    public function test_receipt_verification_does_not_create_a_payment_when_staff_creates_the_bill(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->create(['price' => '1000.00']);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);
        $dentist = User::factory()->create(['role' => 'doctor', 'branch_id' => $branch->id]);
        $appointment = $this->createGcashAppointment($patient, $branch, $service, [
            'status' => 'in_consultation',
            'payment_proof_status' => 'verified',
            'payment_proof_reviewed_by' => $staff->id,
            'payment_proof_reviewed_at' => now(),
        ]);
        DentalRecord::factory()->for($patient)->for($appointment)->create([
            'treating_dentist_id' => $dentist->id,
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.bills.store', $appointment), ['discount_value' => '0'])
            ->assertRedirectToRoute('clinic.billing.index');

        $bill = Bill::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'deposit_payment_id' => null,
            'status' => 'billed',
        ]);
        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_staff_rejection_allows_a_patient_to_submit_replacement_receipt(): void
    {
        Storage::fake('local');
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $oldPath = UploadedFile::fake()->image('old-receipt.png')->store('appointment-payment-proofs', 'local');
        $appointment = $this->createGcashAppointment($patient, $branch, $service, [
            'payment_proof_path' => $oldPath,
            'payment_proof_status' => 'pending_review',
        ]);
        $staff = User::factory()->create(['role' => 'staff', 'branch_id' => $branch->id]);

        $this->actingAs($staff)
            ->patch(route('clinic.appointments.payment-proof.review', $appointment), [
                'status' => 'rejected',
            ])
            ->assertRedirect();

        Storage::disk('local')->assertExists($oldPath);
        $this->actingAs($patient->user)
            ->post(route('appointments.payment-proof.resubmit', $appointment), [
                'payment_proof' => UploadedFile::fake()->image('replacement.png'),
            ])
            ->assertRedirectToRoute('appointments.index');

        $appointment->refresh();
        $this->assertSame('pending_review', $appointment->payment_proof_status);
        $this->assertNotSame($oldPath, $appointment->payment_proof_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($appointment->payment_proof_path);
    }

    #[DataProvider('inactiveAppointmentStates')]
    public function test_rejected_receipt_cannot_be_resubmitted_for_an_inactive_appointment(string $status): void
    {
        Storage::fake('local');
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $proofPath = UploadedFile::fake()->image('old-receipt.png')->store('appointment-payment-proofs', 'local');
        $appointment = $this->createGcashAppointment($patient, $branch, $service, [
            'status' => $status,
            'payment_proof_path' => $proofPath,
            'payment_proof_status' => 'rejected',
        ]);

        $this->actingAs($patient->user)
            ->post(route('appointments.payment-proof.resubmit', $appointment), [
                'payment_proof' => UploadedFile::fake()->image('replacement.png'),
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(1, count(Storage::disk('local')->files('appointment-payment-proofs')));
        Storage::disk('local')->assertExists($proofPath);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function inactiveAppointmentStates(): array
    {
        return [
            'cancelled' => ['cancelled'],
            'completed' => ['completed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createGcashAppointment(
        Patient $patient,
        Branch $branch,
        Service $service,
        array $attributes = [],
    ): Appointment {
        return Appointment::factory()
            ->for($patient)
            ->for($service)
            ->create([
                'branch_id' => $branch->id,
                'payment_method' => 'gcash',
                ...$attributes,
            ]);
    }
}
