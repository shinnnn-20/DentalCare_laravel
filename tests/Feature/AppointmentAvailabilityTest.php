<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentAvailabilityBlock;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_form_keeps_the_selected_time_while_refreshing_availability_before_submission(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($patient->user)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('const selectedTime = preferredTime || timeSelect.value;', false)
            ->assertSee('const availableTimes = availability[dateInput.value] || [];', false)
            ->assertDontSee('if (dateInput.value && !availability[dateInput.value])', false);
    }

    #[DataProvider('availabilityManagerRoles')]
    public function test_authorized_clinic_users_can_create_and_view_a_branch_availability_block(string $role): void
    {
        $this->travelTo('2026-10-25 09:00:00');
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $user = User::factory()->create([
            'role' => $role,
            'branch_id' => $role === 'doctor' ? $branch->id : null,
        ]);

        $this->actingAs($user)
            ->post(route('clinic.availability.store'), [
                'branch_id' => $branch->id,
                'date' => '2026-10-26',
                'starts_at' => '10:00',
                'ends_at' => '12:00',
                'reason' => 'Long dental procedure',
                'description' => 'Reserve the treatment room.',
                'confirm_existing_appointments' => '1',
            ])
            ->assertRedirectToRoute('clinic.availability.index')
            ->assertSessionHas('status', 'Appointment availability blocked.');

        $block = AppointmentAvailabilityBlock::query()->firstOrFail();
        $this->assertSame($branch->id, $block->branch_id);
        $this->assertSame($user->id, $block->created_by);
        $this->assertSame('2026-10-26 10:00:00', $block->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-26 12:00:00', $block->ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'appointment_availability_blocked',
            'record_id' => (string) $block->id,
        ]);

        $response = $this->get(route('clinic.availability.index'));

        $response
            ->assertOk()
            ->assertSee($branch->name)
            ->assertSee('Long dental procedure')
            ->assertSee('data-modal-variant="success"', false)
            ->assertSee('Appointment availability blocked.')
            ->assertDontSee('onsubmit="return confirm(', false)
            ->assertSee('Enable time again')
            ->assertSee('data-availability-form', false)
            ->assertSee('data-availability-time="start"', false)
            ->assertSee('data-availability-time="end"', false)
            ->assertSee('value="10:00"', false)
            ->assertSee('10:00 AM')
            ->assertDontSee('value="12:00"', false)
            ->assertDontSee('value="12:30"', false)
            ->assertSee('value="17:00"', false)
            ->assertDontSee("interval.textContent = '30 min';", false)
            ->assertSee('5:00 PM')
            ->assertSee("label.textContent = selectedOption?.textContent || 'Select time';", false)
            ->assertSee('Choose an end time after the start time.')
            ->assertSeeInOrder(['<!DOCTYPE html>', 'data-availability-form', "document.querySelectorAll('[data-availability-time]')"], false);

        if ($role !== 'doctor') {
            $response->assertSee('data-confirm="Permanently delete this availability block?"', false);
        }
    }

    #[DataProvider('availabilityBlockingRoles')]
    public function test_staff_and_admin_can_block_future_times_today(string $role): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 10:30:00', config('clinic.timezone')));
        $user = User::factory()->create(['role' => $role]);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();

        $this->actingAs($user)
            ->post(route('clinic.availability.store'), [
                'branch_id' => $branch->id,
                'date' => '2026-10-30',
                'starts_at' => '11:00',
                'ends_at' => '12:00',
                'reason' => 'Other',
                'confirm_existing_appointments' => '1',
            ])
            ->assertRedirectToRoute('clinic.availability.index')
            ->assertSessionHas('status', 'Appointment availability blocked.');

        $this->assertDatabaseHas('appointment_availability_blocks', [
            'branch_id' => $branch->id,
            'starts_at' => '2026-10-30 11:00:00',
            'ends_at' => '2026-10-30 12:00:00',
        ]);
    }

    public static function availabilityBlockingRoles(): array
    {
        return [
            'staff' => ['staff'],
            'admin' => ['admin'],
        ];
    }

    public static function availabilityManagerRoles(): array
    {
        return [
            'staff' => ['staff'],
            'doctor' => ['doctor'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('blockingConflictStatuses')]
    public function test_blocking_over_an_existing_appointment_requires_confirmation_and_preserves_it(string $status): void
    {
        $isOngoing = $status === 'in_consultation';
        $this->travelTo(Carbon::parse(
            $isOngoing ? '2026-10-26 10:30:00' : '2026-10-25 09:00:00',
            config('clinic.timezone'),
        ));
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $appointment = Appointment::factory()->for($patient)->create([
            'branch_id' => $branch->id,
            'starts_at' => $isOngoing ? '2026-10-26 10:00:00' : '2026-10-26 10:30:00',
            'duration_minutes' => $isOngoing ? 120 : 60,
            'status' => $status,
        ]);
        $blockData = [
            'branch_id' => $branch->id,
            'date' => '2026-10-26',
            'starts_at' => '11:00',
            'ends_at' => '11:30',
            'reason' => 'Equipment maintenance',
        ];

        $this->actingAs($staff)
            ->from(route('clinic.availability.index'))
            ->post(route('clinic.availability.store'), $blockData)
            ->assertSessionHasErrors('confirm_existing_appointments');

        $this->assertDatabaseCount('appointment_availability_blocks', 0);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => $status]);

        $this->post(route('clinic.availability.store'), [
            ...$blockData,
            'confirm_existing_appointments' => '1',
        ])->assertRedirectToRoute('clinic.availability.index');

        $this->assertDatabaseCount('appointment_availability_blocks', 1);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => $status]);
    }

    public static function blockingConflictStatuses(): array
    {
        return [
            'approved appointment' => ['approved'],
            'ongoing appointment' => ['in_consultation'],
        ];
    }

    public function test_blocked_time_is_unavailable_to_patients_only_at_its_branch(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->for(User::factory()->create(['role' => 'patient']))->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $dasmarinas = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();

        $this->actingAs($staff)->post(route('clinic.availability.store'), [
            'branch_id' => $generalTrias->id,
            'date' => '2026-10-26',
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'reason' => 'Dentist unavailable',
            'confirm_existing_appointments' => '1',
        ])->assertRedirectToRoute('clinic.availability.index');

        $generalAvailability = $this->actingAs($patient->user)->getJson(route('appointments.availability', [
            'month' => '2026-10',
            'branch_id' => $generalTrias->id,
            'service_id' => $service->id,
        ]));
        $otherBranchAvailability = $this->getJson(route('appointments.availability', [
            'month' => '2026-10',
            'branch_id' => $dasmarinas->id,
            'service_id' => $service->id,
        ]));

        $generalAvailability->assertOk();
        $otherBranchAvailability->assertOk();
        $this->assertNotContains('10:00', $generalAvailability->json('days')['2026-10-26']);
        $this->assertNotContains('10:30', $generalAvailability->json('days')['2026-10-26']);
        $this->assertContains('10:00', $otherBranchAvailability->json('days')['2026-10-26']);

        $this->post(route('appointments.store'), [
            'branch_id' => $generalTrias->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-10-26',
            'appointment_time' => '10:00',
        ])->assertSessionHasErrors([
            'starts_at' => 'This time is blocked for the selected branch. Choose another available time.',
        ]);

        $this->assertDatabaseCount('appointments', 0);

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

    public function test_disabling_and_reenabling_a_block_updates_booking_availability_without_changing_existing_appointments(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->for(User::factory()->create(['role' => 'patient']))->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $block = AppointmentAvailabilityBlock::factory()->create([
            'branch_id' => $branch->id,
            'created_by' => $staff->id,
            'starts_at' => '2026-10-26 10:00:00',
            'ends_at' => '2026-10-26 11:00:00',
        ]);

        $this->actingAs($staff)
            ->patch(route('clinic.availability.update', $block), ['is_active' => '0'])
            ->assertRedirectToRoute('clinic.availability.index');
        $this->assertDatabaseHas('appointment_availability_blocks', ['id' => $block->id, 'is_active' => false]);

        $this->actingAs($patient->user)->post(route('appointments.store'), [
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-10-26',
            'appointment_time' => '10:00',
        ])->assertRedirectToRoute('appointments.index');
        $appointment = Appointment::query()->firstOrFail();
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'branch_id' => $branch->id,
            'starts_at' => '2026-10-26 10:00:00',
            'duration_minutes' => 30,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($staff)
            ->patch(route('clinic.availability.update', $block), ['is_active' => '1']);
        $response->assertSessionHasErrors('confirm_existing_appointments');

        $this->patch(route('clinic.availability.update', $block), [
            'is_active' => '1',
            'confirm_existing_appointments' => '1',
        ])->assertRedirectToRoute('clinic.availability.index');

        $this->assertDatabaseHas('appointment_availability_blocks', ['id' => $block->id, 'is_active' => true]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'pending']);
    }

    public function test_only_authorized_roles_can_manage_appointment_availability(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $doctor = User::factory()->create(['role' => 'doctor', 'branch_id' => $branch->id]);
        $unassignedDoctor = User::factory()->create(['role' => 'doctor']);

        $this->actingAs($patient)
            ->get(route('clinic.availability.index'))
            ->assertForbidden();

        $this->actingAs($doctor)
            ->get(route('clinic.availability.index'))
            ->assertOk();

        $this->actingAs($unassignedDoctor)
            ->get(route('clinic.availability.index'))
            ->assertForbidden();
    }

    #[DataProvider('availabilityBlockingRoles')]
    public function test_staff_and_admin_can_delete_an_availability_block(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $block = AppointmentAvailabilityBlock::factory()->create([
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'is_active' => false,
            'reason' => 'Long dental procedure',
        ]);

        $this->actingAs($user)
            ->get(route('clinic.availability.index'))
            ->assertSee('Delete block');

        $this->actingAs($user)
            ->delete(route('clinic.availability.destroy', $block))
            ->assertRedirectToRoute('clinic.availability.index')
            ->assertSessionHas('status', 'Appointment availability block deleted.');

        $this->assertDatabaseMissing('appointment_availability_blocks', ['id' => $block->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'appointment_availability_deleted',
            'record_id' => (string) $block->id,
        ]);
    }

    public function test_doctor_cannot_delete_an_availability_block(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $block = AppointmentAvailabilityBlock::factory()->create();

        $this->actingAs($doctor)
            ->get(route('clinic.availability.index'))
            ->assertDontSee('Delete block');

        $this->actingAs($doctor)
            ->delete(route('clinic.availability.destroy', $block))
            ->assertForbidden();

        $this->assertModelExists($block);
    }

    public function test_blocking_rejects_non_interval_times_and_sundays(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();

        $this->travelTo('2026-10-25 09:00:00');
        $this->actingAs($staff)->post(route('clinic.availability.store'), [
            'branch_id' => $branch->id,
            'date' => '2026-10-26',
            'starts_at' => '10:15',
            'ends_at' => '10:45',
            'reason' => 'Other',
            'confirm_existing_appointments' => '1',
        ])->assertSessionHasErrors('starts_at');

        $this->post(route('clinic.availability.store'), [
            'branch_id' => $branch->id,
            'date' => '2026-11-01',
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'reason' => 'Other',
            'confirm_existing_appointments' => '1',
        ])->assertSessionHasErrors([
            'date' => 'The Dental Clinic is closed on Sundays. Choose another date.',
        ]);

        $this->assertDatabaseCount('appointment_availability_blocks', 0);
    }

    public function test_blocking_rejects_past_dates_and_elapsed_times_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 10:30:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $validData = [
            'branch_id' => $branch->id,
            'reason' => 'Other',
            'starts_at' => '10:00',
            'ends_at' => '11:00',
        ];

        $this->actingAs($staff)
            ->from(route('clinic.availability.index'))
            ->post(route('clinic.availability.store'), [
                ...$validData,
                'date' => '2026-10-29',
                'confirm_existing_appointments' => '1',
            ])
            ->assertSessionHasErrors([
                'date' => 'Choose today or a future date to block availability.',
            ]);

        $this->post(route('clinic.availability.store'), [
            ...$validData,
            'date' => '2026-10-30',
            'confirm_existing_appointments' => '1',
        ])->assertSessionHasErrors([
            'starts_at' => 'Choose a future time to block.',
        ]);

        $this->assertDatabaseCount('appointment_availability_blocks', 0);
    }

    #[DataProvider('uncheckedConfirmationValues')]
    public function test_blocking_does_not_create_a_block_without_confirmation(array $confirmationData): void
    {
        $this->travelTo('2026-10-25 09:00:00');
        $staff = User::factory()->create(['role' => 'staff']);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('clinic.availability.store'), [
                'branch_id' => $branch->id,
                'date' => '2026-10-26',
                'starts_at' => '10:00',
                'ends_at' => '11:00',
                'reason' => 'Other',
                ...$confirmationData,
            ])
            ->assertSessionHasErrors('confirm_existing_appointments');

        $this->assertDatabaseCount('appointment_availability_blocks', 0);
    }

    public static function uncheckedConfirmationValues(): array
    {
        return [
            'checkbox omitted' => [[]],
            'checkbox unchecked' => [['confirm_existing_appointments' => '0']],
        ];
    }

    public function test_availability_picker_uses_clinic_date_and_disables_elapsed_slots(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 10:30:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('clinic.availability.index'))
            ->assertSee('min="2026-10-30"', false)
            ->assertSee('data-clinic-date="2026-10-30"', false)
            ->assertSee('data-clinic-time="10:30"', false)
            ->assertSee('option.disabled = isPastTime(option.value)', false);
    }
}
