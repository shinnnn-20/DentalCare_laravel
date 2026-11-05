<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\RfidCard;
use App\Models\Service;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorPortalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_branch_scoped_patients_services_rfid_and_appointment_records(): void
    {
        $branch = $this->branch('dasmarinas');
        $otherBranch = $this->branch('general-trias');
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $service = Service::factory()->create([
            'name' => 'Shared Clinic Service',
            'is_active' => true,
        ]);
        $patient = $this->patient('Branch Patient', [
            'date_of_birth' => '1990-01-15',
            'gender' => 'female',
            'address' => '123 Clinic Street',
            'emergency_contact' => '09171234567',
        ]);
        $otherPatient = $this->patient('Other Branch Patient');
        $appointment = $this->appointment($patient, $service, $branch);
        $this->appointment($otherPatient, $service, $otherBranch);
        RfidCard::factory()->for($patient)->create(['uid' => 'BRANCH-DOCTOR-RFID']);
        RfidCard::factory()->for($otherPatient)->create(['uid' => 'OTHER-DOCTOR-RFID']);

        $this->actingAs($doctor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("Today's revenue")
            ->assertDontSee('Reports')
            ->assertDontSee('Audit logs')
            ->assertSee(route('clinic.patients.index'))
            ->assertSee(route('clinic.services.index'))
            ->assertSee(route('clinic.rfid.index'))
            ->assertSee(route('clinic.appointment-records'))
            ->assertSee(route('clinic.billing.index'))
            ->assertSee(route('admin.sms-logs'));
        $this->get(route('admin.reports'))->assertForbidden();
        $this->get(route('admin.audit-logs'))->assertForbidden();
        $this->get(route('dashboard.details', ['metric' => 'today_revenue']))->assertForbidden();

        $this->get(route('clinic.patients.index'))
            ->assertOk()
            ->assertSee('Branch Patient')
            ->assertDontSee('Other Branch Patient')
            ->assertSee('Register patient');

        $this->get(route('clinic.patients.index', ['search' => 'Other Branch Patient']))
            ->assertOk()
            ->assertDontSee(route('clinic.patients.show', $otherPatient));

        $this->get(route('clinic.patients.show', $patient))
            ->assertOk()
            ->assertSee('Branch Patient')
            ->assertSee('Jan 15, 1990')
            ->assertSee('Female')
            ->assertSee('123 Clinic Street')
            ->assertSee('09171234567')
            ->assertSee('Save patient details')
            ->assertDontSee('Remove patient access')
            ->assertDontSee('Reset patient password');

        $this->get(route('clinic.patients.show', $otherPatient))
            ->assertNotFound();

        $this->get(route('clinic.services.index'))
            ->assertOk()
            ->assertSee('Shared Clinic Service')
            ->assertDontSee('Add a service')
            ->assertDontSee('Edit service details');

        $this->get(route('clinic.rfid.index'))
            ->assertOk()
            ->assertSee('BRANCH-DOCTOR-RFID')
            ->assertDontSee('OTHER-DOCTOR-RFID')
            ->assertDontSee('Save card settings')
            ->assertDontSee('Assign / update card')
            ->assertDontSee('name="uid"', false)
            ->assertDontSee('name="valid_from"', false)
            ->assertDontSee('name="valid_until"', false)
            ->assertDontSee('name="is_active"', false);

        $this->get(route('clinic.appointment-records'))
            ->assertOk()
            ->assertSee('Branch Patient')
            ->assertDontSee('Other Branch Patient')
            ->assertSee('Appointment Records');

        $this->get(route('clinic.appointment-records', ['branch_id' => $otherBranch->id]))
            ->assertSessionHasErrors('branch_id');
    }

    public function test_doctor_can_view_only_their_branch_billing_and_communication_logs(): void
    {
        $branch = $this->branch('trece');
        $otherBranch = $this->branch('general-trias');
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();
        $patient = $this->patient('Trece Billing Patient');
        $otherPatient = $this->patient('General Trias Billing Patient');
        $appointment = $this->appointment($patient, $service, $branch);
        $otherAppointment = $this->appointment($otherPatient, $service, $otherBranch);
        $bill = Bill::factory()->for($patient)->for($appointment)->create();
        $otherBill = Bill::factory()->for($otherPatient)->for($otherAppointment)->create();
        AuditLog::factory()->create([
            'user_id' => $admin->id,
            'role' => 'admin',
            'action' => 'branch_appointment_updated',
            'module' => 'appointments',
            'record_type' => 'Appointment',
            'record_id' => (string) $appointment->id,
            'description' => 'Branch appointment activity',
        ]);
        AuditLog::factory()->create([
            'user_id' => $admin->id,
            'role' => 'admin',
            'action' => 'other_appointment_updated',
            'module' => 'appointments',
            'record_type' => 'Appointment',
            'record_id' => (string) $otherAppointment->id,
            'description' => 'Other branch activity must remain private',
        ]);
        AuditLog::factory()->create([
            'user_id' => $doctor->id,
            'role' => 'doctor',
            'action' => 'cross_branch_appointment_updated',
            'module' => 'appointments',
            'record_type' => 'Appointment',
            'record_id' => (string) $otherAppointment->id,
            'description' => 'Cross-branch activity must remain private',
        ]);
        SmsLog::factory()->for($patient)->for($appointment)->create([
            'type' => 'appointment_approve',
            'message' => 'Branch appointment message',
        ]);
        SmsLog::factory()->for($otherPatient)->for($otherAppointment)->create([
            'type' => 'appointment_reject',
            'message' => 'Other branch message must remain private',
        ]);

        $this->actingAs($doctor)
            ->get(route('clinic.billing.index'))
            ->assertOk()
            ->assertSee($bill->bill_number)
            ->assertSee('Trece Billing Patient')
            ->assertDontSee('General Trias Billing Patient')
            ->assertSee('Record payment')
            ->assertSee('Add services');

        $this->get(route('clinic.billing.index', ['bill' => $otherBill->id]))
            ->assertOk()
            ->assertDontSee('General Trias Billing Patient');

        $this->get(route('admin.audit-logs'))->assertForbidden();

        $this->get(route('admin.sms-logs'))
            ->assertOk()
            ->assertSee('Branch appointment message')
            ->assertDontSee('Other branch message must remain private');
    }

    public function test_doctor_can_register_a_patient_for_their_assigned_branch(): void
    {
        $branch = $this->branch('general-trias');
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($doctor)
            ->get(route('clinic.patients.create'))
            ->assertOk()
            ->assertSee('Create patient account');

        $this->post(route('clinic.patients.store'), [
            'name' => 'New Branch Patient',
            'date_of_birth' => '1990-01-15',
            'gender' => 'female',
            'phone' => '09171234567',
            'email' => 'new.branch.patient@example.test',
            'address' => 'Branch Clinic Road',
            'password' => 'Patient-account-password!',
            'password_confirmation' => 'Patient-account-password!',
        ])->assertRedirect();

        $patient = Patient::query()->whereHas('user', fn ($query) => $query
            ->where('email', 'new.branch.patient@example.test'))->firstOrFail();

        $this->assertSame($branch->id, (int) $patient->branch_id);
        $this->get(route('clinic.patients.show', $patient))
            ->assertOk()
            ->assertSee('New Branch Patient')
            ->assertSee('Branch Clinic Road');
    }

    public function test_doctor_can_edit_patient_details_but_cannot_manage_patient_access_services_or_rfid(): void
    {
        $branch = $this->branch('dasmarinas');
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $patient = $this->patient('Read Only Patient');
        $service = Service::factory()->create();
        $appointment = $this->appointment($patient, $service, $branch);
        $bill = Bill::factory()->for($patient)->for($appointment)->create();

        $this->actingAs($doctor)
            ->put(route('clinic.patients.update', $patient), [
                'name' => 'Updated Patient',
                'email' => $patient->user->email,
                'phone' => '09170000000',
                'date_of_birth' => '1990-01-01',
                'gender' => 'other',
                'address' => 'Updated address',
            ])->assertRedirect();

        $this->put(route('clinic.patients.update', $this->patient('Other Branch Patient')), [
            'name' => 'Cross-branch Update',
            'email' => 'other.branch@example.test',
            'phone' => '09170000001',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Other address',
        ])->assertNotFound();

        $originalPassword = $patient->user->password;
        $this->put(route('clinic.patients.password', $patient), [
            'password' => 'Doctor-reset-password!',
            'password_confirmation' => 'Doctor-reset-password!',
        ])->assertForbidden();

        $this->delete(route('clinic.patients.destroy', $patient), [
            'confirmation' => $patient->patient_number,
        ])->assertForbidden();

        $this->post(route('admin.services.store'), [
            'name' => 'Doctor Managed Service',
            'price' => 100,
            'requires_price_confirmation' => false,
            'duration_minutes' => 30,
        ])->assertForbidden();

        $this->put(route('admin.services.update', $service), [
            'name' => 'Changed by Doctor',
            'price' => 150,
            'requires_price_confirmation' => false,
            'duration_minutes' => 30,
            'is_active' => true,
        ])->assertForbidden();

        $this->post(route('clinic.rfid.assign', $patient), [
            'uid' => 'DOCTOR-ASSIGNED-RFID',
        ])->assertForbidden();

        $this->post(route('clinic.payments.store', $bill), [
            'method' => 'cash',
            'amount' => 1000,
            'applied_amount' => 1000,
        ])->assertRedirect();

        $this->assertSame('Updated Patient', $patient->user->fresh()->name);
        $this->assertSame($originalPassword, $patient->user->fresh()->password);
        $this->assertTrue($patient->user->fresh()->is_active);
        $this->assertDatabaseMissing('services', ['name' => 'Doctor Managed Service']);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'name' => $service->name]);
        $this->assertDatabaseMissing('rfid_cards', ['uid' => 'DOCTOR-ASSIGNED-RFID']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient_access_removed']);
        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'applied_amount' => '1000.00',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('clinic.services.index'))
            ->assertSee('Add a service')
            ->assertSee('Edit service details');

        $this->post(route('admin.services.store'), [
            'name' => 'Admin Managed Service',
            'price' => 100,
            'requires_price_confirmation' => false,
            'duration_minutes' => 30,
        ])->assertRedirect();

        $adminService = Service::query()->where('name', 'Admin Managed Service')->firstOrFail();
        $this->put(route('admin.services.update', $adminService), [
            'name' => 'Updated Admin Service',
            'price' => 150,
            'requires_price_confirmation' => false,
            'duration_minutes' => 30,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('services', [
            'id' => $adminService->id,
            'name' => 'Updated Admin Service',
            'price' => '150.00',
        ]);
    }

    public function test_doctor_without_a_branch_cannot_open_branch_scoped_portal_features(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $this->actingAs($doctor)
            ->get(route('clinic.patients.index'))
            ->assertForbidden();

        $this->get(route('clinic.rfid.index'))->assertForbidden();
        $this->get(route('clinic.appointment-records'))->assertForbidden();
        $this->get(route('clinic.billing.index'))->assertForbidden();
        $this->get(route('admin.audit-logs'))->assertForbidden();
        $this->get(route('admin.sms-logs'))->assertForbidden();
    }

    private function branch(string $slug): Branch
    {
        return Branch::query()->where('slug', $slug)->firstOrFail();
    }

    private function patient(string $name, array $attributes = []): Patient
    {
        return Patient::factory()
            ->for(User::factory()->state([
                'role' => 'patient',
                'name' => $name,
            ]))
            ->create($attributes);
    }

    private function appointment(Patient $patient, Service $service, Branch $branch): Appointment
    {
        return Appointment::factory()
            ->for($patient)
            ->for($service)
            ->for($branch)
            ->create([
                'starts_at' => now()->subDay(),
                'status' => 'completed',
            ]);
    }
}
