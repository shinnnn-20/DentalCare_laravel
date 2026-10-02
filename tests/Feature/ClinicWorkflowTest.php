<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Receipt;
use App\Models\RfidCard;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClinicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_a_patient_account_only(): void
    {
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

        $response->assertRedirectToRoute('dashboard');
        $this->assertSame('patient', $user->role);
        $this->assertMatchesRegularExpression('/^P-\d{4}-\d{5}$/', $user->patient->patient_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient_registered']);
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

    public function test_staff_cannot_create_staff_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('admin.staff.index'))
            ->assertForbidden();
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

    public function test_staff_rescheduling_persists_a_date_and_ten_minute_time_selection(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(8, 0));

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
        $this->travelTo(now()->startOfDay()->setTime(8, 0));

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

    public function test_patient_booking_is_limited_to_monday_through_saturday_from_ten_to_five(): void
    {
        $this->travelTo('2026-10-04 08:00:00');

        $patient = $this->createPatient('P-2026-00015');
        $patientUser = $patient->user;
        $service = Service::create(['name' => 'Availability test', 'price' => 500, 'is_active' => true]);

        $this->actingAs($patientUser)
            ->get(route('appointments.index'))
            ->assertSee('The Dental Clinic is closed on Sundays. Please select another date.')
            ->assertSee('value="10:00"', false)
            ->assertSee('value="17:00"', false)
            ->assertDontSee('value="09:50"', false)
            ->assertDontSee('value="17:10"', false);

        foreach (range(5, 10) as $day) {
            foreach (['10:00', '17:00'] as $time) {
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
            ['appointment_date' => '2026-10-05', 'appointment_time' => '09:50', 'error' => 'appointment_time'],
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
            'starts_at' => '2026-10-05T09:30',
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
