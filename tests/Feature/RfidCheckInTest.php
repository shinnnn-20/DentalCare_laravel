<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\RfidCard;
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
            ->assertSee('No active patient card matches UID')
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
