<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentBranchSupportTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('clinicStaffRoles')]
    public function test_admin_and_staff_can_filter_appointments_by_branch_with_other_filters(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $patient = Patient::factory()->for(User::factory()->create(['name' => 'Branch Search Patient']))->create();
        $service = Service::factory()->create(['name' => 'Branch Search Cleaning']);
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $dasmarinas = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();
        $matching = Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => $generalTrias->id,
            'starts_at' => '2026-10-26 10:00:00',
            'status' => 'pending',
        ]);
        Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => $dasmarinas->id,
            'starts_at' => '2026-10-26 10:30:00',
            'status' => 'pending',
        ]);
        $unassigned = Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => null,
            'starts_at' => '2026-10-26 11:00:00',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('appointments.index', [
                'branch_id' => $generalTrias->id,
                'date' => '2026-10-26',
                'status' => 'pending',
                'search' => 'Branch Search Patient',
            ]))
            ->assertOk()
            ->assertSee('Filter by Branch')
            ->assertSee('data-label="Branch"', false)
            ->assertSee('General Trias Branch')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 1
                && $appointments->getCollection()->sole()->is($matching));

        $this->actingAs($user)
            ->get(route('appointments.index', ['branch_id' => 'unassigned']))
            ->assertOk()
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 1
                && $appointments->getCollection()->sole()->is($unassigned));

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 3);
    }

    public static function clinicStaffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'staff' => ['staff'],
        ];
    }

    public function test_branch_availability_is_independent_and_new_bookings_persist_the_branch(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', config('clinic.timezone')));
        $patient = Patient::factory()->for(User::factory()->create(['role' => 'patient']))->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $dasmarinas = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();

        Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => $generalTrias->id,
            'starts_at' => '2026-10-26 10:00:00',
            'duration_minutes' => 30,
            'status' => 'approved',
        ]);

        $generalAvailability = $this->actingAs($patient->user)->getJson(route('appointments.availability', [
            'month' => '2026-10',
            'branch_id' => $generalTrias->id,
        ]));
        $otherBranchAvailability = $this->getJson(route('appointments.availability', [
            'month' => '2026-10',
            'branch_id' => $dasmarinas->id,
        ]));

        $generalAvailability->assertOk();
        $otherBranchAvailability->assertOk();
        $this->assertNotContains('10:00', $generalAvailability->json('days')['2026-10-26']);
        $this->assertContains('10:00', $otherBranchAvailability->json('days')['2026-10-26']);

        $this->post(route('appointments.store'), [
            'branch_id' => $dasmarinas->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-10-26',
            'appointment_time' => '10:00',
        ])->assertRedirectToRoute('appointments.index');

        $this->assertDatabaseHas('appointments', [
            'branch_id' => $dasmarinas->id,
            'starts_at' => '2026-10-26 10:00:00',
        ]);
    }

    #[DataProvider('clinicStaffRoles')]
    public function test_admin_and_staff_can_change_branch_only_when_destination_time_is_available(string $role): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', config('clinic.timezone')));
        $user = User::factory()->create(['role' => $role]);
        $patient = Patient::factory()->for(User::factory()->create(['role' => 'patient']))->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $dasmarinas = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();
        $trece = Branch::query()->where('slug', 'trece')->firstOrFail();
        $appointment = Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => $generalTrias->id,
            'starts_at' => '2026-10-26 10:00:00',
            'duration_minutes' => 30,
            'status' => 'pending',
        ]);
        Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => $trece->id,
            'starts_at' => '2026-10-26 10:00:00',
            'duration_minutes' => 30,
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->patch(route('clinic.appointments.reschedule', $appointment), [
                'branch_id' => $dasmarinas->id,
                'appointment_date' => '2026-10-26',
                'appointment_time' => '10:00',
            ])
            ->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame($dasmarinas->id, $appointment->branch_id);
        $this->assertSame('2026-10-26 10:00:00', $appointment->starts_at->format('Y-m-d H:i:s'));

        $this->patch(route('clinic.appointments.reschedule', $appointment), [
            'branch_id' => $trece->id,
            'appointment_date' => '2026-10-26',
            'appointment_time' => '10:00',
        ])->assertSessionHasErrors([
            'starts_at' => 'That time is no longer available. Choose another time.',
        ]);

        $this->assertSame($dasmarinas->id, $appointment->fresh()->branch_id);
    }

    public function test_doctors_cannot_change_an_appointment_branch(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', config('clinic.timezone')));
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->for(User::factory()->create(['role' => 'patient']))->create();
        $service = Service::factory()->create();
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $trece = Branch::query()->where('slug', 'trece')->firstOrFail();
        $appointment = Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => $generalTrias->id,
            'starts_at' => '2026-10-26 10:00:00',
            'status' => 'approved',
        ]);

        $this->actingAs($doctor)
            ->patch(route('clinic.appointments.reschedule', $appointment), [
                'branch_id' => $trece->id,
                'appointment_date' => '2026-10-26',
                'appointment_time' => '10:30',
            ])
            ->assertForbidden();

        $this->assertSame($generalTrias->id, $appointment->fresh()->branch_id);
        $this->assertSame('2026-10-26 10:00:00', $appointment->fresh()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_appointment_form_displays_branch_choices_and_legacy_assignment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        Appointment::factory()->for($patient)->for($service)->create([
            'branch_id' => null,
            'starts_at' => now()->addDays(3)->setTime(10, 0),
        ]);

        $this->actingAs($admin)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('General Trias Branch')
            ->assertSee('Dasmariñas Branch')
            ->assertSee('Trece Branch')
            ->assertSee('Not Assigned')
            ->assertSee('name="branch_id"', false);
    }

    public function test_booking_rejects_a_branch_that_does_not_exist(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00', config('clinic.timezone')));
        $patient = Patient::factory()->for(User::factory()->create(['role' => 'patient']))->create();
        $service = Service::factory()->create();

        $this->actingAs($patient->user)
            ->post(route('appointments.store'), [
                'branch_id' => 999999,
                'service_id' => $service->id,
                'appointment_date' => '2026-10-26',
                'appointment_time' => '10:00',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseCount('appointments', 0);
    }
}
