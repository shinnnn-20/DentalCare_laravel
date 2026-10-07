<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\Receipt;
use App\Models\RfidCard;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClinicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_a_patient_account_only(): void
    {
        Mail::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Jamie Patient',
            'date_of_birth' => '1990-03-10',
            'gender' => 'prefer_not_to_say',
            'phone' => '09170000000',
            'email' => 'jamie@example.test',
            'address' => 'Clinic Street',
            'emergency_contact' => '09171111111',
            'role' => 'admin',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ]);

        $user = User::where('email', 'jamie@example.test')->firstOrFail();

        $response->assertRedirectToRoute('verification.notice')
            ->assertSessionHas('verification_user_id', $user->id);
        $this->get(route('verification.notice'))
            ->assertSee('Verify Your Email')
            ->assertSee('jamie@example.test');
        $this->assertSame('patient', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertMatchesRegularExpression('/^P-\d{4}-\d{5}$/', $user->patient->patient_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_registered']);
        $this->assertGuest();
    }

    public function test_patient_cannot_open_clinic_patient_records(): void
    {
        $patientUser = User::factory()->create(['role' => 'patient']);
        Patient::create([
            'user_id' => $patientUser->id,
            'patient_number' => 'P-2026-00001',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);

        $this->actingAs($patientUser)
            ->get(route('clinic.patients.index'))
            ->assertForbidden();
    }

    #[DataProvider('clinicStaffRoles')]
    public function test_admin_and_staff_can_remove_patient_access_while_retaining_clinic_records(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $patient = $this->createPatient('P-2026-00030');
        $patientUser = $patient->user;
        $appointment = Appointment::factory()->for($patient)->create();
        $dentalRecord = DentalRecord::factory()
            ->for($patient)
            ->for($appointment)
            ->create();
        DB::table('email_verification_tokens')->insert([
            'user_id' => $patientUser->id,
            'token_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'patient-session-'.$patientUser->id,
            'user_id' => $patientUser->id,
            'payload' => 'session-payload',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAs($actor)->delete(route('clinic.patients.destroy', $patient), [
            'confirmation' => $patient->patient_number,
        ]);

        $response->assertRedirectToRoute('clinic.patients.index')
            ->assertSessionHas('status', 'Patient access removed. Profile details were pseudonymized; clinic records were retained.');
        $this->assertFalse($patientUser->fresh()->is_active);
        $this->assertSame('Removed Patient '.$patientUser->id, $patientUser->fresh()->name);
        $this->assertSame('removed-patient-'.$patientUser->id.'@example.invalid', $patientUser->fresh()->email);
        $this->assertNull($patientUser->fresh()->phone);
        $this->assertNull($patient->fresh()->date_of_birth);
        $this->assertNull($patient->fresh()->gender);
        $this->assertNull($patient->fresh()->address);
        $this->assertNull($patient->fresh()->emergency_contact);
        $this->assertModelExists($patient->fresh());
        $this->assertModelExists($appointment->fresh());
        $this->assertModelExists($dentalRecord->fresh());
        $this->assertDatabaseMissing('email_verification_tokens', ['user_id' => $patientUser->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'patient-session-'.$patientUser->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'action' => 'patient_access_removed',
            'module' => 'patients',
            'record_type' => 'Patient',
            'record_id' => (string) $patient->id,
        ]);
    }

    public static function clinicStaffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'staff doctor' => ['staff'],
        ];
    }

    public function test_patient_cannot_remove_a_patient_account(): void
    {
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = $this->createPatient('P-2026-00031');

        $this->actingAs($patientUser)
            ->delete(route('clinic.patients.destroy', $patient), [
                'confirmation' => $patient->patient_number,
            ])
            ->assertForbidden();

        $this->assertTrue($patient->user->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient_access_removed']);
    }

    public function test_patient_access_removal_requires_exact_patient_number_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = $this->createPatient('P-2026-00032');
        $originalEmail = $patient->user->email;

        $this->actingAs($admin)
            ->delete(route('clinic.patients.destroy', $patient), [
                'confirmation' => 'P-2026-99999',
            ])
            ->assertSessionHasErrors([
                'confirmation' => 'Enter the patient number exactly to confirm account removal.',
            ]);

        $this->assertTrue($patient->user->fresh()->is_active);
        $this->assertSame($originalEmail, $patient->user->fresh()->email);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient_access_removed']);
    }

    #[DataProvider('clinicStaffRoles')]
    public function test_admin_and_staff_can_see_patient_access_removal_form(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $patient = $this->createPatient('P-2026-00033');

        $this->actingAs($actor)
            ->get(route('clinic.patients.show', $patient))
            ->assertSee('Remove patient access')
            ->assertSee('to confirm')
            ->assertSee($patient->patient_number);
    }

    public function test_staff_cannot_create_staff_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.staff.index'))
            ->assertForbidden();
    }

    public function test_admin_can_reset_a_patient_password_and_the_reset_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = $this->createPatient('P-2026-00015');
        $patientUser = $patient->user;

        $response = $this->actingAs($admin)->put(route('clinic.patients.password', $patient), [
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ]);

        $response->assertRedirectToRoute('clinic.patients.show', $patient);
        $this->assertTrue(Hash::check('new-secure-password-456', $patientUser->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'patient_password_reset',
            'module' => 'patients',
            'record_type' => 'Patient',
            'record_id' => $patient->id,
        ]);
    }

    public function test_guest_cannot_reset_a_patient_password(): void
    {
        $patient = $this->createPatient('P-2026-00019');
        $originalPassword = $patient->user->password;

        $this->put(route('clinic.patients.password', $patient), [
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ])->assertRedirectToRoute('login');

        $this->assertSame($originalPassword, $patient->user->fresh()->password);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient_password_reset']);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_reset_a_patient_password(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $patient = $this->createPatient('P-2026-00016');

        $this->actingAs($user)
            ->put(route('clinic.patients.password', $patient), [
                'password' => 'new-secure-password-456',
                'password_confirmation' => 'new-secure-password-456',
            ])
            ->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [
            'staff' => ['staff'],
            'patient' => ['patient'],
        ];
    }

    public function test_patient_password_reset_requires_matching_password_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = $this->createPatient('P-2026-00017');
        $originalPassword = $patient->user->password;

        $this->actingAs($admin)
            ->put(route('clinic.patients.password', $patient), [
                'password' => 'new-secure-password-456',
                'password_confirmation' => 'different-secure-password-789',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($originalPassword, $patient->user->fresh()->password);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient_password_reset']);
    }

    public function test_patient_password_reset_form_is_only_shown_to_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00018');

        $this->actingAs($admin)
            ->get(route('clinic.patients.show', $patient))
            ->assertSee('Reset patient password');
        $this->actingAs($staff)
            ->get(route('clinic.patients.show', $patient))
            ->assertDontSee('Reset patient password');
    }

    public function test_rfid_uid_cannot_be_assigned_to_two_patients(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patientOne = $this->createPatient('P-2026-00007');
        $patientTwo = $this->createPatient('P-2026-00008');

        $this->actingAs($staff)
            ->post(route('clinic.rfid.assign', $patientOne), ['uid' => '04-a7-b2'])
            ->assertSessionHasNoErrors();
        $this->post(route('clinic.rfid.assign', $patientTwo), ['uid' => '04-A7-B2'])
            ->assertSessionHasErrors('uid');

        $this->assertSame(1, RfidCard::count());
    }

    public function test_disabled_accounts_cannot_sign_in(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.test',
            'password' => 'correct-horse-battery-staple',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => 'disabled@example.test',
            'password' => 'correct-horse-battery-staple',
        ])->assertSessionHasErrors('email');
    }

    public function test_public_appointment_booking_uses_the_authenticated_patient_profile(): void
    {
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'patient_number' => 'P-2026-00003',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);
        $otherUser = User::factory()->create(['role' => 'patient']);
        $otherPatient = Patient::create([
            'user_id' => $otherUser->id,
            'patient_number' => 'P-2026-00004',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Other address',
        ]);
        $service = Service::create(['name' => 'Consultation', 'price' => 500, 'is_active' => true]);
        $bookingStart = now()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->actingAs($patientUser)
            ->post(route('appointments.store'), [
                'patient_id' => $otherPatient->id,
                'service_id' => $service->id,
                'appointment_date' => $bookingStart->format('Y-m-d'),
                'appointment_time' => '10:00',
            ])
            ->assertRedirectToRoute('appointments.index');

        $appointment = Appointment::firstOrFail();
        $this->assertSame($patient->id, $appointment->patient_id);
        $this->assertSame('pending', $appointment->status);

        $this->post(route('appointments.transition', [$appointment, 'cancel']))
            ->assertSessionHasNoErrors();

        $otherAppointment = Appointment::create([
            'patient_id' => $otherPatient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => now()->addDays(3),
            'status' => 'pending',
        ]);

        $this->post(route('appointments.transition', [$otherAppointment, 'cancel']))
            ->assertNotFound();
    }

    public function test_patient_booking_reserves_a_slot_against_a_second_patient_request(): void
    {
        $this->travelTo('2026-10-04 08:00:00');

        $firstPatient = $this->createPatient('P-2026-00020');
        $secondPatient = $this->createPatient('P-2026-00021');
        $service = Service::create(['name' => 'Slot reservation test', 'price' => 500, 'is_active' => true]);
        $booking = [
            'service_id' => $service->id,
            'appointment_date' => '2026-10-05',
            'appointment_time' => '10:00',
        ];

        $this->actingAs($firstPatient->user)
            ->post(route('appointments.store'), $booking)
            ->assertRedirectToRoute('appointments.index');
        $appointment = Appointment::firstOrFail();
        $this->assertDatabaseHas('appointment_slots', [
            'starts_at' => '2026-10-05 10:00:00',
            'appointment_id' => $appointment->id,
        ]);
        $this->assertSame(0, DB::table('appointment_slots')->insertOrIgnore([
            'starts_at' => '2026-10-05 10:00:00',
            'appointment_id' => null,
        ]));

        $availability = $this->actingAs($secondPatient->user)
            ->getJson(route('appointments.availability', ['month' => '2026-10']));
        $availability->assertOk();
        $this->assertNotContains('10:00', $availability->json('days')['2026-10-05']);

        $this->actingAs($secondPatient->user)
            ->post(route('appointments.store'), $booking)
            ->assertSessionHasErrors([
                'starts_at' => 'That time is no longer available. Choose another time.',
            ]);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_availability_endpoint_marks_taken_and_closed_slots_unavailable(): void
    {
        $this->travelTo('2026-10-04 08:00:00');

        $patient = $this->createPatient('P-2026-00022');
        $service = Service::create(['name' => 'Availability endpoint test', 'price' => 500, 'is_active' => true]);
        Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => '2026-10-05 10:00:00',
            'status' => 'pending',
        ]);
        foreach (range(0, 480, 10) as $minutes) {
            Appointment::create([
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'type' => 'online',
                'starts_at' => Carbon::parse('2026-10-06 09:00:00')->addMinutes($minutes),
                'status' => 'pending',
            ]);
        }

        $response = $this->actingAs($patient->user)
            ->getJson(route('appointments.availability', ['month' => '2026-10']));

        $response->assertOk();
        $this->assertContains('09:00', $response->json('days')['2026-10-05']);
        $this->assertNotContains('10:00', $response->json('days')['2026-10-05']);
        $this->assertContains('10:10', $response->json('days')['2026-10-05']);
        $this->assertContains('17:00', $response->json('days')['2026-10-05']);
        $this->assertSame([], $response->json('days')['2026-10-06']);
        $this->assertSame([], $response->json('days')['2026-10-11']);

        $this->get(route('appointments.index'))
            ->assertSee('data-availability-calendar', false)
            ->assertSee('Appointment Time')
            ->assertSee('Available')
            ->assertSee('Unavailable');
    }

    public function test_cancelling_an_appointment_releases_its_slot_for_rebooking(): void
    {
        $this->travelTo('2026-10-04 08:00:00');

        $firstPatient = $this->createPatient('P-2026-00023');
        $secondPatient = $this->createPatient('P-2026-00024');
        $service = Service::create(['name' => 'Released slot test', 'price' => 500, 'is_active' => true]);
        $booking = [
            'service_id' => $service->id,
            'appointment_date' => '2026-10-05',
            'appointment_time' => '10:00',
        ];

        $this->actingAs($firstPatient->user)
            ->post(route('appointments.store'), $booking)
            ->assertRedirectToRoute('appointments.index');
        $appointment = Appointment::firstOrFail();
        $this->post(route('appointments.transition', [$appointment, 'cancel']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('appointment_slots', [
            'starts_at' => '2026-10-05 10:00:00',
        ]);

        $this->actingAs($secondPatient->user)
            ->post(route('appointments.store'), $booking)
            ->assertRedirectToRoute('appointments.index');
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseHas('appointment_slots', ['starts_at' => '2026-10-05 10:00:00']);
        $this->assertDatabaseCount('appointment_slots', 1);
    }

    public function test_staff_rescheduling_moves_the_slot_reservation_atomically(): void
    {
        $this->travelTo('2026-10-04 08:00:00');

        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00025');
        $service = Service::create(['name' => 'Rescheduled slot test', 'price' => 500, 'is_active' => true]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => '2026-10-05 10:00:00',
            'status' => 'pending',
        ]);

        $availability = $this->actingAs($staff)
            ->getJson(route('appointments.availability', [
                'month' => '2026-10',
                'exclude_appointment_id' => $appointment->id,
            ]));
        $availability->assertOk();
        $this->assertContains('10:00', $availability->json('days')['2026-10-05']);

        $this->patch(route('clinic.appointments.reschedule', $appointment), [
            'appointment_date' => '2026-10-06',
            'appointment_time' => '10:10',
        ])->assertRedirect();

        $this->assertDatabaseHas('appointment_slots', [
            'starts_at' => '2026-10-06 10:10:00',
            'appointment_id' => $appointment->id,
        ]);
        $this->assertDatabaseMissing('appointment_slots', ['starts_at' => '2026-10-05 10:00:00']);
        $this->assertSame('2026-10-06 10:10:00', $appointment->fresh()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_staff_rescheduling_persists_a_date_and_ten_minute_time_selection(): void
    {
        $this->travelTo('2026-10-01 08:00:00');

        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00009');
        $service = Service::create(['name' => 'Checkup', 'price' => 500, 'is_active' => true]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'status' => 'approved',
        ]);

        $newStart = now()->addDays(4)->setTime(15, 40);
        $this->actingAs($staff)
            ->patch(route('clinic.appointments.reschedule', $appointment), [
                'appointment_date' => $newStart->format('Y-m-d'),
                'appointment_time' => $newStart->format('H:i'),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Appointment rescheduled to '.$newStart->format('F j, Y \a\t g:i A').'.');

        $this->assertSame(
            $newStart->format('Y-m-d H:i:s'),
            $appointment->fresh()->starts_at->format('Y-m-d H:i:s'),
        );
        $this->assertDatabaseHas('appointment_slots', [
            'starts_at' => $newStart->format('Y-m-d H:i:s'),
            'appointment_id' => $appointment->id,
        ]);
        $this->actingAs($patient->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($newStart->format('l, F j, Y · g:i A'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appointment_rescheduled',
            'record_id' => (string) $appointment->id,
        ]);
    }

    public function test_rescheduling_rejects_a_taken_time_without_changing_the_appointment(): void
    {
        $this->travelTo('2026-10-01 08:00:00');

        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00010');
        $service = Service::create(['name' => 'Dental Exam', 'price' => 500, 'is_active' => true]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'status' => 'pending',
        ]);
        $takenStart = now()->addDays(4)->setTime(15, 40);
        Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => $takenStart,
            'status' => 'approved',
        ]);

        $this->actingAs($staff)
            ->patch(route('clinic.appointments.reschedule', $appointment), [
                'starts_at' => $takenStart->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(
            now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
            $appointment->fresh()->starts_at->format('Y-m-d H:i:s'),
        );
    }

    #[DataProvider('clinicStaffRoles')]
    public function test_admin_and_staff_can_print_the_billing_page_and_an_individual_bill(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $bill = Bill::factory()->create([
            'bill_number' => 'B-2026-PRINT01',
            'subtotal' => 6500,
            'total' => 6500,
            'payment_status' => 'paid',
        ]);
        $payment = Payment::factory()->for($bill)->create([
            'amount' => 6500,
            'applied_amount' => 6500,
        ]);
        Receipt::factory()->for($payment)->create(['receipt_number' => 'OR-2026-PRINT01']);
        BillItem::factory()->for($bill)->create([
            'service_name' => 'Dental Cleaning',
            'quantity' => 1,
            'unit_price' => 6500,
            'subtotal' => 6500,
        ]);
        $otherBill = Bill::factory()->create(['bill_number' => 'B-2026-PRINT02']);

        $this->actingAs($actor)
            ->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertSee('🖨 Print All')
            ->assertSee('🖨 Print Bill')
            ->assertSee('data-billing-print-report', false)
            ->assertSee('Bill Number')
            ->assertSee('Bill Date')
            ->assertSee('Patient Name')
            ->assertSee('Patient ID')
            ->assertSee('Total Amount')
            ->assertSee('Amount Paid')
            ->assertSee('Remaining Balance')
            ->assertSee('Payment Status')
            ->assertSee('OR / Receipt Number')
            ->assertSee('B-2026-PRINT01')
            ->assertSee('B-2026-PRINT02')
            ->assertSee('data-print-bill="bill-print-'.$bill->id.'"', false)
            ->assertSee('data-print-bill="bill-print-'.$otherBill->id.'"', false)
            ->assertSee('id="bill-print-'.$bill->id.'"', false)
            ->assertSee('id="bill-print-'.$otherBill->id.'"', false)
            ->assertSee($bill->patient->user->name)
            ->assertSee($bill->patient->patient_number)
            ->assertSee('Dental Cleaning')
            ->assertSee('CASH')
            ->assertSee('OR-2026-PRINT01')
            ->assertSee('₱6,500.00');
    }

    public function test_clinic_walk_in_can_be_completed_billed_and_paid_with_receipt(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'patient_number' => 'P-2026-00002',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);
        $service = Service::create(['name' => 'Dental Cleaning', 'price' => 1800, 'is_active' => true]);

        $this->actingAs($staff)
            ->post(route('appointments.store'), [
                'patient_id' => $patient->id,
                'service_id' => $service->id,
            ])
            ->assertRedirectToRoute('appointments.index');

        $appointment = Appointment::firstOrFail();
        $this->assertSame('checked_in', $appointment->status);
        $this->assertDatabaseHas('queue_entries', ['appointment_id' => $appointment->id]);

        $this->post(route('appointments.transition', [$appointment, 'call']))->assertSessionHasNoErrors();
        $this->post(route('appointments.transition', [$appointment, 'consult']))->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('clinic.records.store', $appointment), [
                'diagnosis' => 'Routine examination',
                'treatment' => 'Cleaning completed',
            ])
            ->assertRedirectToRoute('clinic.patients.show', $patient);

        $this->post(route('clinic.bills.store', $appointment), ['discount' => '0.00'])
            ->assertRedirectToRoute('clinic.billing.index');

        $bill = Bill::firstOrFail();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'billed']);
        $this->actingAs($admin)
            ->post(route('appointments.transition', [$appointment, 'complete']))
            ->assertSessionHasErrors('status');
        $this->actingAs($staff)
            ->post(route('appointments.transition', [$appointment, 'complete']))
            ->assertSessionHasErrors('status');

        $this->post(route('clinic.payments.store', $bill), [
            'method' => 'cash',
            'amount' => '500.00',
            'applied_amount' => '1800.00',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);

        $this->post(route('clinic.payments.store', $bill), [
            'method' => 'cash',
            'amount' => '2000.00',
            'applied_amount' => '1800.00',
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'amount' => '2000.00',
            'applied_amount' => '1800.00',
            'change_amount' => '200.00',
        ]);
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'billed']);
        $this->actingAs($staff)
            ->get(route('appointments.index'))
            ->assertSee('Complete');
        $this->post(route('appointments.transition', [$appointment, 'complete']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'completed']);
        $this->actingAs($admin)
            ->post(route('appointments.transition', [$appointment, 'complete']))
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)
            ->get(route('appointments.index'))
            ->assertSee('View bill')
            ->assertSee(route('clinic.billing.index', ['bill' => $bill->id]).'#bill-'.$bill->id, false);
        $otherBill = Bill::factory()->create();
        $this->get(route('clinic.billing.index', ['bill' => $bill->id]))
            ->assertSee($bill->bill_number)
            ->assertSee($otherBill->bill_number)
            ->assertSee('selected-bill', false);
        $receipt = Receipt::firstOrFail();
        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
        $this->assertDatabaseHas('sms_logs', ['type' => 'payment', 'status' => 'failed']);

        $this->actingAs($patientUser)->get(route('receipts.show', $receipt))->assertOk();
        $otherPatient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($otherPatient)->get(route('receipts.show', $receipt))->assertNotFound();
    }

    public function test_booking_and_rescheduling_require_ten_minute_time_slots(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(8, 0));

        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'patient_number' => 'P-2026-00011',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);
        $service = Service::create(['name' => 'Checkup', 'price' => 500, 'is_active' => true]);
        $invalidStart = now()->next(Carbon::MONDAY)->setTime(10, 15);

        $this->actingAs($patientUser)->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('name="appointment_time"', false)
            ->assertDontSee('type="datetime-local"', false)
            ->assertDontSee('value="00:01"', false)
            ->assertDontSee('value="00:09"', false)
            ->assertSee('value="10:10"', false)
            ->assertDontSee('value="paid"', false);
        $this->actingAs($patientUser)
            ->post(route('appointments.store'), [
                'service_id' => $service->id,
                'appointment_date' => $invalidStart->format('Y-m-d'),
                'appointment_time' => $invalidStart->format('H:i'),
            ])
            ->assertSessionHasErrors('appointment_time');
        $this->assertDatabaseCount('appointments', 0);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'status' => 'approved',
        ]);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->patch(route('clinic.appointments.reschedule', $appointment), [
                'appointment_date' => $invalidStart->format('Y-m-d'),
                'appointment_time' => $invalidStart->format('H:i'),
            ])
            ->assertSessionHasErrors('appointment_time');
        $this->assertSame(
            now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
            $appointment->fresh()->starts_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_patient_booking_is_limited_to_monday_through_saturday_from_nine_to_five(): void
    {
        $this->travelTo('2026-10-04 08:00:00');

        $patient = $this->createPatient('P-2026-00015');
        $patientUser = $patient->user;
        $service = Service::create(['name' => 'Availability test', 'price' => 500, 'is_active' => true]);

        $this->actingAs($patientUser)
            ->get(route('appointments.index'))
            ->assertSee('The Dental Clinic is closed on Sundays. Please select another date.')
            ->assertSee('value="09:00"', false)
            ->assertSee('value="10:00"', false)
            ->assertSee('value="17:00"', false)
            ->assertDontSee('value="08:50"', false)
            ->assertDontSee('value="17:10"', false);

        foreach (range(5, 10) as $day) {
            foreach (['09:00', '17:00'] as $time) {
                $this->post(route('appointments.store'), [
                    'service_id' => $service->id,
                    'appointment_date' => sprintf('2026-10-%02d', $day),
                    'appointment_time' => $time,
                ])->assertRedirectToRoute('appointments.index')
                    ->assertSessionHasNoErrors();
            }
        }

        $this->assertDatabaseCount('appointments', 12);

        foreach ([
            ['appointment_date' => '2026-10-05', 'appointment_time' => '08:50', 'error' => 'appointment_time'],
            ['appointment_date' => '2026-10-07', 'appointment_time' => '17:10', 'error' => 'appointment_time'],
            ['appointment_date' => '2026-10-11', 'appointment_time' => '10:00', 'error' => 'appointment_date'],
        ] as $invalidSlot) {
            $response = $this->post(route('appointments.store'), [
                'service_id' => $service->id,
                'appointment_date' => $invalidSlot['appointment_date'],
                'appointment_time' => $invalidSlot['appointment_time'],
            ])->assertSessionHasErrors($invalidSlot['error']);

            if ($invalidSlot['appointment_date'] === '2026-10-11') {
                $response->assertSessionHasErrors([
                    'appointment_date' => 'The Dental Clinic is closed on Sundays. Please select another date.',
                ]);
            }
        }

        $this->post(route('appointments.store'), [
            'service_id' => $service->id,
            'starts_at' => '2026-10-11T10:00',
        ])->assertSessionHasErrors([
            'starts_at' => 'The Dental Clinic is closed on Sundays. Please select another date.',
        ]);
        $this->post(route('appointments.store'), [
            'service_id' => $service->id,
            'starts_at' => '2026-10-05T08:50',
        ])->assertSessionHasErrors('starts_at');
        $this->post(route('appointments.store'), [
            'service_id' => $service->id,
            'starts_at' => '2026-10-07T17:10',
        ])->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('appointments', 12);
    }

    public function test_full_schedule_shows_all_historical_appointments_until_a_date_is_selected(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00016');
        $service = Service::create(['name' => 'Historical schedule test', 'price' => 500, 'is_active' => true]);

        foreach ([
            '2026-09-13 09:00:00',
            '2026-10-01 15:30:00',
            '2026-10-02 13:00:00',
            '2026-10-03 16:50:00',
        ] as $startsAt) {
            Appointment::create([
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'type' => 'online',
                'starts_at' => $startsAt,
                'status' => 'pending',
            ]);
        }

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertSee('href="'.route('appointments.index').'"', false);

        $this->get(route('appointments.index'))
            ->assertSee('value=""', false)
            ->assertSee('Sep 13, 2026')
            ->assertSee('Oct 1, 2026')
            ->assertSee('Oct 2, 2026')
            ->assertSee('Oct 3, 2026');

        $this->get(route('appointments.index').'?date=')
            ->assertSee('value=""', false)
            ->assertSee('Sep 13, 2026')
            ->assertSee('Oct 1, 2026')
            ->assertSee('Oct 2, 2026')
            ->assertSee('Oct 3, 2026');

        $this->get(route('appointments.index', ['date' => '2026-10-01']))
            ->assertSee('Oct 1, 2026')
            ->assertDontSee('Sep 13, 2026')
            ->assertDontSee('Oct 2, 2026');

        $this->assertDatabaseCount('appointments', 4);
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'starts_at' => '2026-09-13 09:00:00',
        ]);
    }

    public function test_only_a_finalized_paid_bill_allows_admin_to_complete_an_appointment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = $this->createPatient('P-2026-00012');
        $service = Service::create(['name' => 'Consultation', 'price' => 500, 'is_active' => true]);
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'status' => 'billed',
        ]);
        $bill = Bill::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'payment_status' => 'paid',
            'total' => 500,
        ]);

        $this->actingAs($admin)
            ->post(route('appointments.transition', [$appointment, 'complete']))
            ->assertSessionHasErrors('status');
        $this->assertSame('billed', $appointment->fresh()->status);

        $bill->items()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'quantity' => 1,
            'unit_price' => 500,
            'subtotal' => 500,
        ]);
        $this->post(route('appointments.transition', [$appointment, 'complete']))
            ->assertSessionHasErrors('status');
        $this->assertSame('billed', $appointment->fresh()->status);
    }

    public function test_zero_balance_bill_is_paid_and_can_be_completed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = $this->createPatient('P-2026-00014');
        $service = Service::create(['name' => 'Complimentary consultation', 'price' => 0, 'is_active' => true]);
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'status' => 'in_consultation',
        ]);

        $this->actingAs($admin)
            ->post(route('clinic.records.store', $appointment), [
                'diagnosis' => 'Routine check',
                'treatment' => 'No charge',
            ])
            ->assertRedirectToRoute('clinic.patients.show', $patient);
        $this->post(route('clinic.bills.store', $appointment), ['discount' => '0.00'])
            ->assertRedirectToRoute('clinic.billing.index');

        $bill = Bill::firstOrFail();
        $this->assertSame('paid', $bill->payment_status);
        $this->assertSame('0.00', $bill->total);
        $this->post(route('appointments.transition', [$appointment, 'complete']))
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', $appointment->fresh()->status);
    }

    public function test_call_and_no_show_are_available_only_from_patient_queue(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00013');
        $service = Service::create(['name' => 'Dental Exam', 'price' => 500, 'is_active' => true]);
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'walk_in',
            'status' => 'checked_in',
            'starts_at' => now(),
        ]);
        $queueEntry = QueueEntry::factory()->create([
            'appointment_id' => $appointment->id,
            'queue_date' => today(),
            'queue_number' => 'Q-001',
            'status' => 'waiting',
        ]);

        $this->actingAs($staff)->get(route('clinic.queue.index'))
            ->assertOk()
            ->assertSee('Walk-in appointment')
            ->assertSee('Call patient')
            ->assertSee('No show');
        $this->get(route('appointments.index'))
            ->assertDontSee('Call patient')
            ->assertDontSee(route('appointments.transition', [$appointment, 'no-show']));

        $this->post(route('appointments.transition', [$appointment, 'call']))->assertSessionHasNoErrors();
        $this->assertSame('called', $queueEntry->fresh()->status);
        $this->get(route('appointments.index'))->assertDontSee('Start visit');
        $this->get(route('clinic.queue.index'))->assertSee('Start visit')->assertSee('No show');
        $this->post(route('appointments.transition', [$appointment, 'no-show']))->assertSessionHasNoErrors();
        $this->assertSame('no_show', $appointment->fresh()->status);
        $this->assertDatabaseCount('queue_entries', 1);
    }

    public function test_admin_and_patient_portal_pages_render(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patientUser = User::factory()->create(['role' => 'patient']);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'patient_number' => 'P-2026-00005',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);

        $this->actingAs($admin);
        foreach ([
            'dashboard',
            'appointments.index',
            'clinic.patients.index',
            'clinic.patients.show',
            'clinic.queue.index',
            'clinic.services.index',
            'clinic.billing.index',
            'clinic.rfid.index',
            'clinic.rfid.scan',
            'admin.staff.index',
            'admin.reports',
            'admin.audit-logs',
            'admin.sms-logs',
        ] as $routeName) {
            $parameters = $routeName === 'clinic.patients.show' ? [$patient] : [];
            $this->get(route($routeName, $parameters))->assertOk();
        }

        $this->actingAs($patientUser);
        foreach (['dashboard', 'appointments.index', 'patient.records', 'patient.billing', 'patient.notifications'] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    private function createPatient(string $patientNumber): Patient
    {
        $user = User::factory()->create(['role' => 'patient']);

        return Patient::create([
            'user_id' => $user->id,
            'patient_number' => $patientNumber,
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);
    }

    public function test_approved_appointments_receive_one_scheduled_reminder(): void
    {
        $this->travelTo(now()->startOfMinute());
        Config::set('services.clinic_sms.endpoint', 'https://sms.example.test/send');
        Config::set('services.clinic_sms.token', 'test-token');
        Http::fake(['sms.example.test/*' => Http::response(['ok' => true], 200)]);

        $patientUser = User::factory()->create(['role' => 'patient', 'phone' => '09170000000']);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'patient_number' => 'P-2026-00006',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);
        $service = Service::create(['name' => 'X-Ray', 'price' => 500, 'is_active' => true]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'type' => 'online',
            'starts_at' => now()->addHours(24)->addMinutes(5),
            'status' => 'approved',
        ]);

        $this->artisan('clinic:send-appointment-reminders')->assertSuccessful();
        $this->artisan('clinic:send-appointment-reminders')->assertSuccessful();

        $this->assertDatabaseHas('sms_logs', [
            'appointment_id' => $appointment->id,
            'type' => 'appointment_reminder',
            'status' => 'sent',
        ]);
        $this->assertDatabaseCount('sms_logs', 1);
        Http::assertSentCount(1);
    }
}
