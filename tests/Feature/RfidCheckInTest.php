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

    public function test_scan_shows_only_the_matched_patient_with_newest_records_and_upcoming_appointments_first(): void
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
                'Newer diagnosis',
                'Older diagnosis',
                'Oct 29, 2026 11:00 AM',
                'Upcoming Cleaning',
                'Upcoming X-Ray',
                'RFID Test Branch',
                'Status: approved',
                'Oct 27, 2026 12:00 PM',
                'Past Filling',
                'Status: completed',
            ])
            ->assertDontSee('Other Patient')
            ->assertDontSee('Other patient confidential diagnosis')
            ->assertDontSee('Private target address')
            ->assertDontSee('Private emergency contact')
            ->assertDontSee('1988-01-02')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->pluck('id')->all() === [$upcomingAppointment->id, $pastAppointment->id]);
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

    public function test_staff_can_update_rfid_validity_and_disable_a_card_without_deleting_it(): void
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
                'valid_from' => '2026-10-29T10:00',
                'valid_until' => '2026-10-30T10:00',
                'is_active' => '0',
            ])
            ->assertSessionHas('status', 'RFID card assigned.');

        $this->assertDatabaseHas('rfid_cards', [
            'id' => $card->id,
            'patient_id' => $patient->id,
            'uid' => 'UPDATED-UID',
            'is_active' => false,
            'valid_from' => '2026-10-29 10:00:00',
            'valid_until' => '2026-10-30 10:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'action' => 'rfid_card_updated',
            'record_id' => (string) $card->id,
        ]);
        $this->assertModelExists($historicalLog);
        $this->get(route('clinic.rfid.index'))
            ->assertSee('Oct 29, 2026 10:00 AM')
            ->assertSee('Oct 30, 2026 10:00 AM')
            ->assertSee('disabled');
    }

    public function test_rfid_validity_fields_render_custom_calendar_and_thirty_minute_time_picker(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 21:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $this->createPatient('P-2026-00057', 'RFID Picker Patient', '09170000057');

        $this->actingAs($staff)
            ->get(route('clinic.rfid.index'))
            ->assertSee('data-rfid-validity-picker', false)
            ->assertSee('data-clinic-today="2026-10-30"', false)
            ->assertSee('Validity time')
            ->assertSee('Choose validity time')
            ->assertSee('minutes += 30', false)
            ->assertSee('minutes < 24 * 60', false)
            ->assertSee('Validity times in 30-minute intervals')
            ->assertSee('interval.textContent = \'30 min\'', false)
            ->assertSee('const displayHour = hour % 12 || 12;', false)
            ->assertSee('Clear limit')
            ->assertSee('Today')
            ->assertSee('Done')
            ->assertSee('rfid-validity-day-selected')
            ->assertSee('name="valid_from"', false)
            ->assertSee('name="valid_until"', false);
    }

    public function test_rfid_valid_until_must_follow_valid_from(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = $this->createPatient('P-2026-00056', 'Invalid RFID Period Patient', '09170000056');

        $this->actingAs($staff)
            ->post(route('clinic.rfid.assign', $patient), [
                'uid' => 'INVALID-PERIOD',
                'valid_from' => '2026-10-30T10:00',
                'valid_until' => '2026-10-29T10:00',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('valid_until');

        $this->assertDatabaseCount('rfid_cards', 0);
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
            ->assertSee('Scanning a card only identifies the patient.');

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
            ->assertSee('No dental records found for this patient.')
            ->assertSee('No booked appointments found for this patient.');
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
            ->assertSee('Diagnosis &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Treatment &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Prescription &lt;script&gt;alert(1)&lt;/script&gt;', false)
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
            'doctor' => ['doctor'],
        ];
    }

    #[DataProvider('clinicStaffRoles')]
    public function test_admin_and_staff_can_open_the_rfid_scan_page(string $role): void
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
