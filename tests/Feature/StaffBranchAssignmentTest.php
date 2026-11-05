<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffBranchAssignmentTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidPasswords')]
    public function test_admin_cannot_create_clinic_accounts_with_weak_passwords(
        string $password,
        string $errorMessage,
    ): void {
        $admin = User::factory()->create(['role' => 'admin']);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Weak Password Account',
                'email' => 'weak.password@example.test',
                'role' => 'staff',
                'branch_id' => $branch->id,
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertSessionHasErrors(['password' => $errorMessage]);

        $this->assertDatabaseMissing('users', ['email' => 'weak.password@example.test']);
    }

    public static function invalidPasswords(): array
    {
        return [
            'short password' => ['Abc1!xy', 'The password field must be at least 8 characters.'],
            'no special character' => ['Abcdef12', 'The password field must contain at least one symbol.'],
            'no capital letter' => ['abcdef1!', 'The password field format is invalid.'],
            'space is not a special character' => ['Abcdef 1', 'The password field format is invalid.'],
        ];
    }

    public function test_admin_must_assign_a_branch_when_creating_staff_or_doctor_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('minlength="8"', false)
            ->assertSee('At least 8 characters, one capital letter, and one special character.')
            ->assertSee('grid items-start gap-4 sm:grid-cols-2 lg:col-span-2', false)
            ->assertSee('self-start lg:mt-6', false)
            ->assertSee('Assigned branch')
            ->assertSee('General Trias Branch')
            ->assertSee('Dasmariñas Branch')
            ->assertSee('Trece Branch')
            ->assertSee('name="branch_id"', false)
            ->assertSee('required', false);

        $this->post(route('admin.staff.store'), [
            'name' => 'Unassigned Staff',
            'email' => 'unassigned.staff@example.test',
            'role' => 'staff',
            'password' => 'Temporary-staff-password!',
            'password_confirmation' => 'Temporary-staff-password!',
        ])->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('users', ['email' => 'unassigned.staff@example.test']);
    }

    public function test_admin_can_create_and_update_a_clinic_account_branch_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $dasmarinas = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Assigned Doctor',
                'email' => 'assigned.doctor@example.test',
                'role' => 'doctor',
                'branch_id' => $generalTrias->id,
                'password' => 'Temporary-doctor-password!',
                'password_confirmation' => 'Temporary-doctor-password!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Doctor account created.');

        $doctor = User::query()->where('email', 'assigned.doctor@example.test')->firstOrFail();
        $this->assertSame($generalTrias->id, (int) $doctor->branch_id);
        $this->assertSame($generalTrias->name, $doctor->branch->name);

        $this->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Assigned Doctor')
            ->assertSee('Assigned branch')
            ->assertSee('data-table table-fixed', false)
            ->assertSee('w-[12%]', false)
            ->assertSee('w-[10%]', false)
            ->assertSee('w-[36%]', false)
            ->assertSee('grid-cols-1', false)
            ->assertSee('sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]', false)
            ->assertSee('items-center', false)
            ->assertSee('min-h-11', false);

        $this->patch(route('admin.staff.branch.update', $doctor), [
            'branch_id' => $dasmarinas->id,
        ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Doctor branch assignment updated.');

        $this->assertSame($dasmarinas->id, (int) $doctor->fresh()->branch_id);
        $this->assertSame($dasmarinas->name, $doctor->fresh()->branch->name);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'doctor_branch_updated',
            'record_id' => (string) $doctor->id,
        ]);
    }

    public function test_staff_appointment_records_are_filtered_to_the_assigned_branch(): void
    {
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $dasmarinas = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();
        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $generalTrias->id,
        ]);
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $assignedAppointment = Appointment::factory()
            ->for($patient)
            ->for($service)
            ->for($generalTrias)
            ->create();
        Appointment::factory()
            ->for($patient)
            ->for($service)
            ->for($dasmarinas)
            ->create();

        $this->actingAs($staff)
            ->get(route('clinic.appointment-records'))
            ->assertOk()
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 1
                && $appointments->getCollection()->sole()->id === $assignedAppointment->id);

        $this->get(route('clinic.appointment-records', ['branch_id' => $dasmarinas->id]))
            ->assertSessionHasErrors('branch_id');
    }

    public function test_assigned_staff_queue_and_check_in_are_limited_to_their_branch(): void
    {
        $generalTrias = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $dasmarinas = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();
        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $generalTrias->id,
        ]);
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $appointmentStart = today(config('clinic.timezone'))->addHour();
        $assignedAppointment = Appointment::factory()
            ->for($patient)
            ->for($service)
            ->for($generalTrias)
            ->create([
                'status' => 'approved',
                'starts_at' => $appointmentStart,
            ]);
        $otherBranchAppointment = Appointment::factory()
            ->for($patient)
            ->for($service)
            ->for($dasmarinas)
            ->create([
                'status' => 'approved',
                'starts_at' => $appointmentStart,
            ]);

        $this->actingAs($staff)
            ->get(route('clinic.queue.index'))
            ->assertOk()
            ->assertViewHas('checkInAppointments', fn ($appointments): bool => $appointments->sole()->id === $assignedAppointment->id);

        $this->post(route('clinic.queue.check-in', $otherBranchAppointment))
            ->assertNotFound();
        $this->assertSame('approved', $otherBranchAppointment->fresh()->status);

        $this->post(route('clinic.queue.check-in', $assignedAppointment))
            ->assertRedirect(route('clinic.queue.index'));
        $this->assertSame('checked_in', $assignedAppointment->fresh()->status);
    }
}
