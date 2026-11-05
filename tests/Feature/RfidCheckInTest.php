<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\RfidCard;
use App\Models\RfidLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RfidCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_view_card_details_without_edit_controls(): void
    {
        $patient = $this->createPatient('P-2026-00061', 'RFID Portal Patient', '09170000061');
        RfidCard::factory()->for($patient)->create([
            'uid' => 'PATIENT-CARD-61',
            'is_active' => false,
            'valid_until' => '2026-12-31 23:59:00',
        ]);

        $this->actingAs($patient->user)
            ->get(route('patient.rfid.index'))
            ->assertOk()
            ->assertSee('PATIENT-CARD-61')
            ->assertSee('Status: disabled')
            ->assertSee('Dec 31, 2026 11:59 PM')
            ->assertDontSee('name="uid"', false)
            ->assertDontSee('Register RFID card')
            ->assertDontSee('Update registered card');
    }

    public function test_patient_dashboard_notifies_when_an_active_rfid_card_expires_within_thirty_days(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 09:00:00', config('clinic.timezone')));
        $patient = $this->createPatient('P-2026-00101', 'RFID Expiration Patient', '09170000101');
        RfidCard::factory()->for($patient)->create([
            'uid' => 'EXPIRING-CARD-101',
            'is_active' => true,
            'valid_until' => '2026-11-30 00:00:00',
        ]);

        $this->actingAs($patient->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('RFID card expiration reminder')
            ->assertSee('Your RFID card expires soon')
            ->assertSee('Nov 30, 2026')
            ->assertSee(route('patient.rfid.index'), false);
    }

    public function test_patient_dashboard_hides_rfid_expiration_notice_for_disabled_or_distant_expiry_cards(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 09:00:00', config('clinic.timezone')));
        $patient = $this->createPatient('P-2026-00102', 'RFID Distant Expiration Patient', '09170000102');
        RfidCard::factory()->for($patient)->create([
            'uid' => 'DISTANT-CARD-102',
            'is_active' => true,
            'valid_until' => '2026-12-07 00:00:00',
        ]);

        $this->actingAs($patient->user)
            ->get(route('dashboard'))
            ->assertDontSee('RFID card expiration reminder');

        $patient->rfidCard->update([
            'is_active' => false,
            'valid_until' => '2026-11-05 00:00:00',
        ]);

        $this->get(route('dashboard'))
            ->assertDontSee('RFID card expiration reminder');
    }

    public function test_patient_cannot_replace_their_registered_card(): void
    {
        $patient = $this->createPatient('P-2026-00063', 'RFID Replacement Patient', '09170000063');
        $card = RfidCard::factory()->for($patient)->create([
            'uid' => 'OLD-PATIENT-CARD',
            'is_active' => false,
        ]);

        $this->actingAs($patient->user)
            ->post('/my/rfid', ['uid' => 'NEW-PATIENT-CARD'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('rfid_cards', [
            'id' => $card->id,
            'patient_id' => $patient->id,
            'uid' => 'OLD-PATIENT-CARD',
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['user_id' => $patient->user_id, 'module' => 'rfid']);
    }

    public function test_patient_cannot_register_a_card_from_their_portal(): void
    {
        $patient = $this->createPatient('P-2026-00064', 'RFID New Patient', '09170000064');

        $this->actingAs($patient->user)
            ->post('/my/rfid', ['uid' => 'NEW-PATIENT-CARD'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseMissing('rfid_cards', ['patient_id' => $patient->id]);
        $this->assertDatabaseMissing('audit_logs', ['user_id' => $patient->user_id, 'module' => 'rfid']);
    }

    public function test_rfid_patient_page_requires_an_authenticated_patient(): void
    {
        $this->get(route('patient.rfid.index'))
            ->assertRedirectToRoute('login');

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('patient.rfid.index'))
            ->assertForbidden();

        $this->assertDatabaseCount('rfid_cards', 0);
    }

    public function test_scan_shows_only_the_matched_patient_with_newest_records_and_all_appointments_upcoming_first(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00001', 'RFID Target Patient', '09170000001', [
            'date_of_birth' => '1988-01-02',
            'address' => 'Private target address',
            'emergency_contact' => 'Private emergency contact',
        ]);
        $otherPatient = $this->createPatient('P-2026-00002', 'Other Patient', '09170000002');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'TARGET-CARD']);
        DentalRecord::factory()->for($patient)->create([
            'diagnosis' => 'Older diagnosis',
            'treatment' => 'Older treatment',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        DentalRecord::factory()->for($patient)->create([
            'diagnosis' => 'Newer diagnosis',
            'treatment' => 'Newer treatment',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DentalRecord::factory()->for($otherPatient)->create([
            'diagnosis' => 'Other patient confidential diagnosis',
        ]);

        $branch = Branch::factory()->create(['name' => 'RFID Test Branch']);
        $upcomingService = Service::factory()->create(['name' => 'Upcoming Cleaning']);
        $additionalService = Service::factory()->create(['name' => 'Upcoming X-Ray']);
        $pastService = Service::factory()->create(['name' => 'Past Filling']);
        $upcomingAppointment = Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for($upcomingService)
            ->create([
                'starts_at' => now()->addDay()->setTime(11, 0),
                'status' => 'approved',
            ]);
        $upcomingAppointment->services()->attach([$upcomingService->id, $additionalService->id]);
        $pastAppointment = Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for($pastService)
            ->create([
                'starts_at' => now()->subDay()->setTime(12, 0),
                'status' => 'completed',
            ]);

        $response = $this->actingAs($staff)->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid]);

        $response->assertOk()
            ->assertSee('RFID Target Patient')
            ->assertSee('P-2026-00001')
            ->assertSee('09170000001')
            ->assertSeeInOrder([
                'Patient identified',
                'Next appointment',
                'Upcoming Cleaning',
                'approved',
                'Oct 29, 2026 11:00 AM',
                'RFID Test Branch',
            ])
            ->assertSee('Booked appointments')
            ->assertSee('Past Filling')
            ->assertSee('Dental records')
            ->assertDontSee('Newer diagnosis')
            ->assertDontSee('Private target address')
            ->assertDontSee('Private emergency contact')
            ->assertDontSee('1988-01-02')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->getCollection()->pluck('id')->all() === [
                $upcomingAppointment->id,
                $pastAppointment->id,
            ])
            ->assertViewHas('nextAppointment', fn ($appointment): bool => $appointment->id === $upcomingAppointment->id);

        $this->get(route('clinic.rfid.scan', ['uid' => $card->uid, 'section' => 'appointments']))
            ->assertSeeInOrder([
                'Oct 29, 2026 11:00 AM',
                'Upcoming Cleaning',
                'Upcoming X-Ray',
                'RFID Test Branch',
                'Status: approved',
            ])
            ->assertSee('Past Filling')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->getCollection()->pluck('id')->all() === [
                $upcomingAppointment->id,
                $pastAppointment->id,
            ]);

        $this->get(route('clinic.rfid.scan', [
            'uid' => $card->uid,
            'section' => 'appointments',
            'appointment_from' => '2026-10-27',
            'appointment_until' => '2026-10-27',
        ]))
            ->assertSee('Past Filling')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->getCollection()->pluck('id')->all() === [$pastAppointment->id]);

        $this->get(route('clinic.rfid.scan', ['uid' => $card->uid, 'section' => 'records']))
            ->assertSeeInOrder(['Newer diagnosis', 'Older diagnosis'])
            ->assertDontSee('Upcoming Cleaning');
    }

    public function test_next_appointment_skips_future_appointments_already_completed(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 08:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00076', 'Next Appointment Patient', '09170000076');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'NEXT-APPOINTMENT-CARD']);
        $branch = Branch::factory()->create(['name' => 'Next Appointment Branch']);
        $completedService = Service::factory()->create(['name' => 'Already completed treatment']);
        $approvedService = Service::factory()->create(['name' => 'November consultation']);

        Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for($completedService)
            ->create([
                'starts_at' => Carbon::parse('2026-11-04 10:30:00', config('clinic.timezone')),
                'status' => 'completed',
            ]);
        $approvedAppointment = Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for($approvedService)
            ->create([
                'starts_at' => Carbon::parse('2026-11-13 11:00:00', config('clinic.timezone')),
                'status' => 'approved',
            ]);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid])
            ->assertOk()
            ->assertSee('November consultation')
            ->assertSee('Nov 13, 2026 11:00 AM')
            ->assertViewHas('nextAppointment', fn ($appointment): bool => $appointment->id === $approvedAppointment->id);
    }

    public function test_scan_filters_and_paginates_dental_records_while_preserving_the_uid(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00071', 'Dental Record Filter Patient', '09170000071');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'RECORD-FILTER-CARD']);

        foreach (range(1, 7) as $daysAgo) {
            DentalRecord::factory()->for($patient)->create([
                'diagnosis' => "Dental filter diagnosis {$daysAgo}",
                'treatment' => 'Dental filter treatment',
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid])
            ->assertOk();

        $filters = [
            'uid' => $card->uid,
            'section' => 'records',
            'record_search' => 'Dental filter diagnosis',
            'record_from' => now()->subDays(6)->toDateString(),
        ];

        $this->get(route('clinic.rfid.scan', $filters))
            ->assertOk()
            ->assertSee('section=records', false)
            ->assertSee('records_page=2', false)
            ->assertSee('uid=RECORD-FILTER-CARD', false);

        $this->get(route('clinic.rfid.scan', [...$filters, 'records_page' => 2]))
            ->assertOk()
            ->assertSee('Dental filter diagnosis 6')
            ->assertDontSee('Dental filter diagnosis 7')
            ->assertViewHas('dentalRecords', fn ($records): bool => $records->total() === 6
                && $records->currentPage() === 2
                && $records->getCollection()->last()->diagnosis === 'Dental filter diagnosis 6')
            ->assertSee('record_search')
            ->assertSee('RECORD-FILTER-CARD');

        $this->assertDatabaseCount('rfid_logs', 1);
    }

    public function test_record_search_finds_performed_treatments_and_add_on_services(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00074', 'Treatment Search Patient', '09170000074');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'TREATMENT-SEARCH-CARD']);
        $primaryService = Service::factory()->create(['name' => 'Primary cleaning service']);
        $addOnService = Service::factory()->create(['name' => 'Fluoride add-on service']);
        $appointment = Appointment::factory()
            ->for($patient)
            ->for($primaryService)
            ->create([
                'starts_at' => now()->subDay(),
                'status' => 'completed',
            ]);
        $appointment->services()->attach([$primaryService->id, $addOnService->id]);
        $record = DentalRecord::factory()->for($patient)->for($appointment)->create([
            'diagnosis' => 'General dental diagnosis',
            'treatment' => 'Composite restoration performed',
        ]);
        DentalRecord::factory()->for($patient)->create([
            'diagnosis' => 'Unrelated diagnosis',
            'treatment' => 'Unrelated treatment',
        ]);

        $this->actingAs($staff)
            ->get(route('clinic.rfid.scan', [
                'uid' => $card->uid,
                'section' => 'records',
                'record_search' => 'Fluoride add-on',
            ]))
            ->assertSee('Search by service or treatment performed')
            ->assertSee('Service, add-on service, or treatment')
            ->assertSee('Composite restoration performed')
            ->assertSee('Services performed: Primary cleaning service, Fluoride add-on service')
            ->assertDontSee('Unrelated treatment')
            ->assertViewHas('dentalRecords', fn ($records): bool => $records->total() === 1
                && $records->first()->id === $record->id);

        $this->get(route('clinic.rfid.scan', [
            'uid' => $card->uid,
            'section' => 'records',
            'record_search' => 'Composite restoration',
        ]))
            ->assertSee('Composite restoration performed')
            ->assertDontSee('Unrelated treatment')
            ->assertViewHas('dentalRecords', fn ($records): bool => $records->total() === 1
                && $records->first()->id === $record->id);
    }

    #[DataProvider('rfidCheckInRoles')]
    public function test_staff_and_doctors_can_check_in_approved_appointments_from_rfid_scan(string $role): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', config('clinic.timezone')));
        $branch = Branch::factory()->create(['name' => 'RFID Check-in Branch']);
        $user = User::factory()->create([
            'role' => $role,
            'branch_id' => $branch->id,
        ]);
        $patient = $this->createPatient('P-2026-00075', 'RFID Check-in Patient', '09170000075');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'RFID-CHECK-IN-CARD']);
        $service = Service::factory()->create(['name' => 'Approved RFID service']);
        $appointment = Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for($service)
            ->create([
                'starts_at' => now(config('clinic.timezone'))->addHour(),
                'status' => 'approved',
            ]);
        $otherBranchAppointment = null;
        if ($role === 'doctor') {
            $otherBranch = Branch::factory()->create(['name' => 'Other RFID Check-in Branch']);
            $otherBranchAppointment = Appointment::factory()
                ->for($patient)
                ->for($otherBranch)
                ->create([
                    'starts_at' => now(config('clinic.timezone'))->addHours(2),
                    'status' => 'approved',
                ]);
        }
        $pendingAppointment = Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->create([
                'starts_at' => now(config('clinic.timezone'))->addHours(2),
                'status' => 'pending',
            ]);

        $response = $this->actingAs($user)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid]);

        $this->assertContains(
            $appointment->id,
            $response->viewData('appointments')->getCollection()->modelKeys(),
        );

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'Next appointment',
                'Approved RFID service',
                'Approved',
                'Branch: RFID Check-in Branch',
                'Check in',
            ])
            ->assertSee('pending')
            ->assertSee(route('clinic.queue.check-in', $appointment))
            ->assertDontSee(route('clinic.queue.check-in', $pendingAppointment));

        $this->post(route('clinic.queue.check-in', $appointment))
            ->assertRedirectToRoute('clinic.queue.index')
            ->assertSessionHas('status', 'Appointment checked in and added to the queue.');

        $this->assertSame('checked_in', $appointment->fresh()->status);
        $this->assertDatabaseHas('queue_entries', [
            'appointment_id' => $appointment->id,
            'status' => 'waiting',
        ]);
        $this->assertDatabaseCount('queue_entries', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appointment_check_in',
            'record_id' => $appointment->id,
        ]);

        $this->get(route('clinic.queue.index'))
            ->assertSee('RFID Check-in Patient')
            ->assertSee('Call patient')
            ->assertSee('Appointment checked in and added to the queue.');

        $this->post(route('appointments.transition', [$appointment, 'call']))
            ->assertRedirect()
            ->assertSessionHas('status', 'Appointment updated to waiting.');

        $this->assertSame('waiting', $appointment->fresh()->status);
        $this->assertDatabaseHas('queue_entries', [
            'appointment_id' => $appointment->id,
            'status' => 'called',
        ]);

        if ($otherBranchAppointment !== null) {
            $this->post(route('clinic.queue.check-in', $otherBranchAppointment), ['rfid_uid' => $card->uid])
                ->assertNotFound();

            $this->assertSame('approved', $otherBranchAppointment->fresh()->status);
        }
    }

    public static function rfidCheckInRoles(): array
    {
        return [
            'staff' => ['staff'],
            'doctor' => ['doctor'],
        ];
    }

    public function test_scan_filters_and_paginates_appointments_by_service_status_and_date(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00072', 'Appointment Filter Patient', '09170000072');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'APPOINTMENT-FILTER-CARD']);

        foreach (range(1, 7) as $daysAhead) {
            $service = Service::factory()->create(['name' => "Filtered service {$daysAhead}"]);
            Appointment::factory()
                ->for($patient)
                ->for($service)
                ->create([
                    'starts_at' => now()->addDays($daysAhead),
                    'status' => 'approved',
                ]);
        }

        $pendingService = Service::factory()->create(['name' => 'Filtered service pending']);
        Appointment::factory()
            ->for($patient)
            ->for($pendingService)
            ->create([
                'starts_at' => now()->addDays(4),
                'status' => 'pending',
            ]);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid])
            ->assertOk();

        $this->get(route('clinic.rfid.scan', ['uid' => $card->uid, 'section' => 'appointments']))
            ->assertSee('appointments_page=2', false)
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 8
                && $appointments->currentPage() === 1);

        $filters = [
            'uid' => $card->uid,
            'section' => 'appointments',
            'appointment_search' => 'Filtered service',
            'appointment_status' => 'approved',
            'appointment_from' => now()->addDays(2)->toDateString(),
            'appointment_until' => now()->addDays(7)->toDateString(),
        ];

        $this->get(route('clinic.rfid.scan', $filters))
            ->assertOk()
            ->assertSee('section=appointments', false)
            ->assertSee('appointments_page=2', false)
            ->assertSee('uid=APPOINTMENT-FILTER-CARD', false);

        $this->get(route('clinic.rfid.scan', [...$filters, 'appointments_page' => 2]))
            ->assertOk()
            ->assertSee('Filtered service 7')
            ->assertDontSee('Filtered service pending')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 6
                && $appointments->currentPage() === 2
                && $appointments->getCollection()->last()->service->name === 'Filtered service 7')
            ->assertSee('appointment_status')
            ->assertSee('APPOINTMENT-FILTER-CARD');

        $this->assertDatabaseCount('rfid_logs', 1);
    }

    public function test_consultation_search_finds_related_records_and_appointments_in_separate_sections(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00073', 'Consultation Search Patient', '09170000073');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'CONSULTATION-SEARCH-CARD']);
        $service = Service::factory()->create(['name' => 'Dental examination']);
        $consultation = Appointment::factory()
            ->for($patient)
            ->for($service)
            ->create([
                'starts_at' => now()->addDay(),
                'status' => 'in_consultation',
            ]);
        DentalRecord::factory()->for($patient)->for($consultation)->create([
            'diagnosis' => 'Consultation diagnosis',
            'treatment' => 'Examination treatment',
        ]);
        $followUpConsultation = Appointment::factory()
            ->for($patient)
            ->for($service)
            ->create([
                'starts_at' => now()->addDays(3),
                'status' => 'in_consultation',
            ]);
        DentalRecord::factory()->for($patient)->for($followUpConsultation)->create([
            'diagnosis' => 'Consultation follow-up diagnosis',
            'treatment' => 'Follow-up treatment',
        ]);
        $otherAppointment = Appointment::factory()
            ->for($patient)
            ->create([
                'starts_at' => now()->addDays(2),
                'status' => 'approved',
            ]);
        DentalRecord::factory()->for($patient)->for($otherAppointment)->create([
            'diagnosis' => 'Routine diagnosis',
            'treatment' => 'Routine treatment',
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid])
            ->assertOk();

        $this->get(route('clinic.rfid.scan', [
            'uid' => $card->uid,
            'section' => 'records',
            'record_search' => 'consultation',
        ]))
            ->assertOk()
            ->assertSee('Consultation diagnosis')
            ->assertSee('Consultation follow-up diagnosis')
            ->assertDontSee('Routine diagnosis')
            ->assertSee('Services performed: Dental examination')
            ->assertViewHas('dentalRecords', fn ($records): bool => $records->total() === 2);

        $this->get(route('clinic.rfid.scan', [
            'uid' => $card->uid,
            'section' => 'appointments',
            'appointment_search' => 'consultation',
        ]))
            ->assertOk()
            ->assertSee('Dental examination')
            ->assertSee('Status: in consultation')
            ->assertDontSee('Routine treatment')
            ->assertDontSee('Consultation diagnosis');

        $this->assertDatabaseCount('rfid_logs', 1);
    }

    public function test_scan_rejects_rfid_cards_outside_their_validity_period_or_disabled(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $notYetValidPatient = $this->createPatient('P-2026-00051', 'Not Yet Valid Patient', '09170000051');
        $expiredPatient = $this->createPatient('P-2026-00052', 'Expired Patient', '09170000052');
        $disabledPatient = $this->createPatient('P-2026-00053', 'Disabled Patient', '09170000053');
        $validPatient = $this->createPatient('P-2026-00054', 'Currently Valid Patient', '09170000054');
        RfidCard::factory()->for($notYetValidPatient)->create([
            'uid' => 'NOT-YET-VALID',
            'valid_from' => now(config('clinic.timezone'))->addHour(),
        ]);
        RfidCard::factory()->for($expiredPatient)->create([
            'uid' => 'EXPIRED',
            'valid_until' => now(config('clinic.timezone')),
        ]);
        RfidCard::factory()->for($disabledPatient)->create([
            'uid' => 'DISABLED',
            'is_active' => false,
        ]);
        RfidCard::factory()->for($validPatient)->create([
            'uid' => 'CURRENTLY-VALID',
            'valid_from' => now(config('clinic.timezone'))->subHour(),
            'valid_until' => now(config('clinic.timezone'))->addHour(),
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => 'NOT-YET-VALID'])
            ->assertOk()
            ->assertSee('This RFID card is not yet valid.')
            ->assertDontSee('Not Yet Valid Patient')
            ->assertViewHas('patient', null);
        $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'EXPIRED'])
            ->assertOk()
            ->assertSee('This RFID card has expired.')
            ->assertDontSee('Expired Patient')
            ->assertViewHas('patient', null);
        $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'DISABLED'])
            ->assertOk()
            ->assertSee('This RFID card has been disabled.')
            ->assertDontSee('Disabled Patient')
            ->assertViewHas('patient', null);
        $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'CURRENTLY-VALID'])
            ->assertOk()
            ->assertSee('Currently Valid Patient')
            ->assertViewHas('patient', $validPatient);
        $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'NOT-REGISTERED'])
            ->assertOk()
            ->assertSee('No registered RFID card matches this UID.')
            ->assertViewHas('patient', null);

        $this->assertDatabaseHas('rfid_logs', ['uid' => 'NOT-YET-VALID', 'result' => 'not_yet_valid']);
        $this->assertDatabaseHas('rfid_logs', ['uid' => 'EXPIRED', 'result' => 'expired']);
        $this->assertDatabaseHas('rfid_logs', ['uid' => 'DISABLED', 'result' => 'disabled']);
        $this->assertDatabaseHas('rfid_logs', ['uid' => 'CURRENTLY-VALID', 'result' => 'identified']);
        $this->assertDatabaseHas('rfid_logs', ['uid' => 'NOT-REGISTERED', 'result' => 'unknown']);
    }

    public function test_rfid_card_expires_at_midnight_philippine_time_on_its_valid_until_date(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00059', 'RFID Midnight Expiry Patient', '09170000059');
        RfidCard::factory()->for($patient)->create([
            'uid' => 'MIDNIGHT-EXPIRY',
            'valid_from' => Carbon::parse('2026-11-03 00:00:00', config('clinic.timezone')),
            'valid_until' => Carbon::parse('2026-11-20 00:00:00', config('clinic.timezone')),
        ]);

        $this->travelTo(Carbon::parse('2026-11-19 23:59:59', config('clinic.timezone')));
        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => 'MIDNIGHT-EXPIRY'])
            ->assertSee('RFID Midnight Expiry Patient');

        $this->travelTo(Carbon::parse('2026-11-20 00:00:00', config('clinic.timezone')));
        $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'MIDNIGHT-EXPIRY'])
            ->assertSee('This RFID card has expired.')
            ->assertDontSee('RFID Midnight Expiry Patient');
    }

    public function test_staff_can_update_rfid_validity_by_date_and_disable_a_card_without_deleting_it(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00055', 'RFID Settings Patient', '09170000055');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'ORIGINAL-UID']);
        $historicalLog = RfidLog::factory()->for($patient)->for($card, 'card')->create([
            'uid' => 'ORIGINAL-UID',
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.assign', $patient), [
                'uid' => 'UPDATED-UID',
                'valid_from' => '2026-11-03',
                'valid_until' => '2026-11-20',
                'is_active' => '0',
            ])
            ->assertSessionHas('status', 'RFID card assigned.');

        $this->assertDatabaseHas('rfid_cards', [
            'id' => $card->id,
            'patient_id' => $patient->id,
            'uid' => 'UPDATED-UID',
            'is_active' => false,
            'valid_from' => '2026-11-03 00:00:00',
            'valid_until' => '2026-11-20 00:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'action' => 'rfid_card_updated',
            'record_id' => (string) $card->id,
        ]);
        $this->assertModelExists($historicalLog);
        $this->get(route('clinic.rfid.index'))
            ->assertSee('Nov 3, 2026')
            ->assertSee('Nov 20, 2026 12:00 AM')
            ->assertSee('disabled');
    }

    public function test_rfid_validity_fields_render_a_date_only_calendar_picker(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 21:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $this->createPatient('P-2026-00057', 'RFID Picker Patient', '09170000057');

        $this->actingAs($staff)
            ->get(route('clinic.rfid.index'))
            ->assertSee('data-rfid-validity-picker', false)
            ->assertSee('data-clinic-today="2026-10-30"', false)
            ->assertSee('type="date"', false)
            ->assertSee('Choose date')
            ->assertSee('Clear limit')
            ->assertSee('Today')
            ->assertSee('Done')
            ->assertSee('rfid-validity-day-selected')
            ->assertSee('name="valid_from"', false)
            ->assertSee('name="valid_until"', false)
            ->assertDontSee('Validity time')
            ->assertDontSee('datetime-local');
    }

    public function test_rfid_valid_until_must_follow_valid_from(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00056', 'Invalid RFID Period Patient', '09170000056');

        $this->actingAs($staff)
            ->post(route('clinic.rfid.assign', $patient), [
                'uid' => 'INVALID-PERIOD',
                'valid_from' => '2026-10-30',
                'valid_until' => '2026-10-29',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('valid_until');

        $this->assertDatabaseCount('rfid_cards', 0);
    }

    public function test_rfid_validity_rejects_time_values(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00058', 'RFID Date Format Patient', '09170000058');

        $this->actingAs($staff)
            ->post(route('clinic.rfid.assign', $patient), [
                'uid' => 'DATE-ONLY-CARD',
                'valid_from' => '2026-11-03T10:00',
                'valid_until' => '2026-11-20T10:00',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors(['valid_from', 'valid_until']);

        $this->assertDatabaseMissing('rfid_cards', ['patient_id' => $patient->id]);
    }

    public function test_new_scan_replaces_the_displayed_patient_and_unknown_card_clears_it(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $firstPatient = $this->createPatient('P-2026-00011', 'First Scan Patient', '09170000011');
        $secondPatient = $this->createPatient('P-2026-00012', 'Second Scan Patient', '09170000012');
        RfidCard::factory()->for($firstPatient)->create(['uid' => 'FIRST-SCAN']);
        RfidCard::factory()->for($secondPatient)->create(['uid' => 'SECOND-SCAN']);

        $firstResponse = $this->actingAs($staff)->post(route('clinic.rfid.scan.submit'), ['uid' => 'FIRST-SCAN']);
        $firstResponse->assertOk()
            ->assertSee('First Scan Patient')
            ->assertDontSee('Second Scan Patient');

        $secondResponse = $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'SECOND-SCAN']);
        $secondResponse->assertOk()
            ->assertSee('Second Scan Patient')
            ->assertDontSee('First Scan Patient');

        $unknownResponse = $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'UNKNOWN-SCAN']);
        $unknownResponse->assertOk()
            ->assertSee('No registered RFID card matches this UID.')
            ->assertSee('UNKNOWN-SCAN')
            ->assertDontSee('First Scan Patient')
            ->assertDontSee('Second Scan Patient')
            ->assertViewHas('patient', null);

        $this->assertDatabaseCount('patients', 2);
        $this->assertDatabaseHas('rfid_logs', ['uid' => 'UNKNOWN-SCAN', 'result' => 'unknown']);
    }

    public function test_scanning_a_patient_does_not_create_patients_or_check_in_appointments(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00021', 'No Side Effects Patient', '09170000021');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'IDENTIFY-ONLY']);
        $appointment = Appointment::factory()->for($patient)->create([
            'starts_at' => now()->addDay(),
            'status' => 'approved',
        ]);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid])
            ->assertOk()
            ->assertSee('Use Check in on an approved appointment to add the patient to the clinic queue.');

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('queue_entries', 0);
        $this->assertSame('approved', $appointment->fresh()->status);
        $this->assertDatabaseHas('rfid_logs', [
            'uid' => 'IDENTIFY-ONLY',
            'patient_id' => $patient->id,
            'result' => 'identified',
        ]);
    }

    public function test_scan_shows_empty_messages_when_patient_has_no_records_or_appointments(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00031', 'Empty History Patient', '09170000031');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'EMPTY-HISTORY']);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid])
            ->assertOk()
            ->assertSee('No upcoming appointments')
            ->assertSee('No booked appointments found for this patient.')
            ->assertDontSee('No dental records found for this patient.');

        $this->get(route('clinic.rfid.scan', [
            'uid' => $card->uid,
            'section' => 'records',
        ]))
            ->assertOk()
            ->assertSee('No dental records found for this patient.');
    }

    public function test_scan_escapes_patient_and_clinic_content_in_the_result(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $unsafeContent = '<script>alert(1)</script>';
        $patient = $this->createPatient('P-2026-00041', 'Security '.$unsafeContent.' Patient', '09170000041');
        $card = RfidCard::factory()->for($patient)->create(['uid' => 'ESCAPE-CONTENT']);
        DentalRecord::factory()->for($patient)->create([
            'diagnosis' => 'Diagnosis '.$unsafeContent,
            'treatment' => 'Treatment '.$unsafeContent,
            'prescription' => 'Prescription '.$unsafeContent,
        ]);
        $branch = Branch::factory()->create(['name' => 'Branch '.$unsafeContent]);
        $service = Service::factory()->create(['name' => 'Service '.$unsafeContent]);
        Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for($service)
            ->create([
                'starts_at' => now()->addDay(),
                'status' => 'approved',
            ]);

        $this->actingAs($staff)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => $card->uid])
            ->assertOk()
            ->assertSee('Security &lt;script&gt;alert(1)&lt;/script&gt; Patient', false)
            ->assertDontSee($unsafeContent, false);

        $this->get(route('clinic.rfid.scan', [
            'uid' => $card->uid,
            'section' => 'records',
        ]))
            ->assertOk()
            ->assertSee('Diagnosis &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Treatment &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Prescription &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee($unsafeContent, false);

        $this->get(route('clinic.rfid.scan', [
            'uid' => $card->uid,
            'section' => 'appointments',
        ]))
            ->assertOk()
            ->assertSee('Branch &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Service &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee($unsafeContent, false);
    }

    public function test_guest_is_redirected_from_rfid_scan(): void
    {
        $this->post(route('clinic.rfid.scan.submit'), ['uid' => 'ANY-CARD'])
            ->assertRedirectToRoute('login');
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_rfid_scan_forbids_unprivileged_roles(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->post(route('clinic.rfid.scan.submit'), ['uid' => 'ANY-CARD'])
            ->assertForbidden();
    }

    public static function unprivilegedRoles(): array
    {
        return [
            'patient' => ['patient'],
        ];
    }

    #[DataProvider('clinicStaffRoles')]
    public function test_clinic_roles_can_open_the_rfid_scan_page(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('clinic.rfid.scan'))
            ->assertOk()
            ->assertSee('RFID check-in');
    }

    public static function clinicStaffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'staff' => ['staff'],
            'doctor' => ['doctor'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPatient(string $patientNumber, string $name, string $phone, array $attributes = []): Patient
    {
        $user = User::factory()->create([
            'name' => $name,
            'phone' => $phone,
            'role' => 'patient',
        ]);

        return Patient::factory()
            ->for($user)
            ->create(array_merge(['patient_number' => $patientNumber], $attributes));
    }
}
