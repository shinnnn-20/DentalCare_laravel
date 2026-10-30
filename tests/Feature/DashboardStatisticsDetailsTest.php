<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardStatisticsDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_statistic_details_match_the_records_for_each_card(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', config('clinic.timezone')));
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Active Staff']);
        $doctor = User::factory()->create(['role' => 'doctor', 'name' => 'Active Doctor']);
        User::factory()->create(['role' => 'staff', 'is_active' => false, 'name' => 'Inactive Staff']);
        $patient = Patient::factory()->create([
            'user_id' => User::factory()->create([
                'role' => 'patient',
                'name' => 'Dashboard Patient',
                'email' => 'dashboard.patient@example.test',
                'phone' => '09170000000',
            ])->id,
            'patient_number' => 'P-DETAIL-0001',
        ]);
        $branch = Branch::query()->where('slug', 'general-trias')->firstOrFail();
        $service = Service::factory()->create(['name' => 'Dental Cleaning']);
        $pendingAppointment = Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for($service)
            ->create([
                'created_by' => $admin->id,
                'starts_at' => '2026-10-30 13:00:00',
                'status' => 'pending',
                'type' => 'online',
            ]);
        QueueEntry::factory()->for($pendingAppointment)->create([
            'queue_date' => '2026-10-30',
            'queue_number' => 'Q-100',
            'status' => 'waiting',
        ]);
        $completedAppointment = Appointment::factory()
            ->for($patient)
            ->for($branch)
            ->for(Service::factory()->create(['name' => 'Tooth Filling']))
            ->create([
                'created_by' => $doctor->id,
                'starts_at' => '2026-10-30 10:00:00',
                'status' => 'completed',
                'type' => 'walk_in',
            ]);
        $bill = Bill::factory()->for($patient)->for($pendingAppointment)->create([
            'bill_number' => 'B-DETAIL-0001',
            'total' => '1000.00',
            'payment_status' => 'partially_paid',
            'created_by' => $admin->id,
        ]);
        Payment::factory()->for($bill)->create([
            'amount' => '250.00',
            'applied_amount' => '250.00',
            'processed_by' => $staff->id,
        ]);

        $this->actingAs($admin);

        $dashboard = $this->get(route('dashboard', [
            'branch_id' => $branch->id,
            'statistics_period' => 'week',
        ]));
        $dashboard->assertOk();
        $dashboardMetrics = $dashboard->viewData('metrics');
        $dashboardCounts = array_column($dashboardMetrics, 1, 0);
        $this->assertSame('1', $dashboardCounts['Patients']);
        $this->assertSame('2', $dashboardCounts["Today's appointments"]);
        $this->assertSame('1', $dashboardCounts['Pending approval']);
        $this->assertSame('1', $dashboardCounts['Patients in queue']);
        $this->assertSame('1', $dashboardCounts['Completed today']);
        $this->assertSame('1', $dashboardCounts['Unpaid bills']);
        $this->assertSame('2', $dashboardCounts['Active staff']);
        $this->assertSame("Today's revenue", $dashboardMetrics[5][0]);
        $this->assertSame('₱250.00', $dashboardMetrics[5][1]);
        $this->assertSame(null, $dashboardMetrics[5][3]);

        $this->get(route('dashboard.details', [
            'metric' => 'patients',
            'branch_id' => $branch->id,
            'statistics_period' => 'week',
        ]))
            ->assertOk()
            ->assertJsonPath('title', 'Patients')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.name', 'Dashboard Patient')
            ->assertJsonPath('records.0.patient_id', 'P-DETAIL-0001')
            ->assertJsonPath('records.0.email', 'dashboard.patient@example.test')
            ->assertJsonPath('records.0.phone', '09170000000')
            ->assertJsonPath('records.0.branches', 'General Trias Branch');

        $this->get(route('dashboard.details', [
            'metric' => 'today_appointments',
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('records.0.branch', 'General Trias Branch')
            ->assertJsonPath('records.0.services', 'Tooth Filling');

        $this->get(route('dashboard.details', [
            'metric' => 'pending_approval',
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.status', 'Pending')
            ->assertJsonPath('records.0.services', 'Dental Cleaning');

        $this->get(route('dashboard.details', [
            'metric' => 'queue',
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.queue_number', 'Q-100')
            ->assertJsonPath('records.0.name', 'Dashboard Patient')
            ->assertJsonPath('records.0.status', 'Waiting');

        $this->get(route('dashboard.details', [
            'metric' => 'completed_today',
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.doctor', 'Not recorded')
            ->assertJsonPath('records.0.status', 'Completed');

        $this->get(route('dashboard.details', [
            'metric' => 'unpaid_bills',
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.bill_number', 'B-DETAIL-0001')
            ->assertJsonPath('records.0.paid', '250.00')
            ->assertJsonPath('records.0.balance', '750.00')
            ->assertJsonPath('records.0.status', 'Partially paid');

        $this->get(route('dashboard.details', ['metric' => 'active_staff']))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('records.0.status', 'Active');
    }

    public function test_dashboard_details_respect_selected_branch_and_doctors_can_access_overview(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', config('clinic.timezone')));
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $branch = Branch::query()->where('slug', 'trece')->firstOrFail();
        $otherBranch = Branch::query()->where('slug', 'dasmarinas')->firstOrFail();
        Appointment::factory()->for($patient)->for($branch)->for($service)->create([
            'created_by' => $doctor->id,
            'starts_at' => '2026-10-30 10:00:00',
            'status' => 'pending',
        ]);
        Appointment::factory()->for($patient)->for($otherBranch)->for($service)->create([
            'created_by' => $doctor->id,
            'starts_at' => '2026-10-30 11:00:00',
            'status' => 'pending',
        ]);

        $this->actingAs($doctor)
            ->get(route('dashboard', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Clinic overview')
            ->assertDontSee('data-dashboard-detail="patients"', false)
            ->assertDontSee('data-dashboard-detail="unpaid_bills"', false)
            ->assertDontSee('data-dashboard-detail="active_staff"', false);

        $this->get(route('dashboard.details', [
            'metric' => 'today_appointments',
            'branch_id' => $branch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.branch', 'Trece Branch');

        $this->get(route('dashboard.details', ['metric' => 'patients']))
            ->assertForbidden();
        $this->get(route('dashboard.details', ['metric' => 'unpaid_bills']))
            ->assertForbidden();
        $this->get(route('dashboard.details', ['metric' => 'active_staff']))
            ->assertForbidden();
    }

    public function test_today_appointment_details_filter_by_actual_status_and_branch(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', config('clinic.timezone')));
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $service = Service::factory()->create(['name' => 'Appointment test service']);
        $branches = collect(['general-trias', 'dasmarinas', 'trece'])
            ->map(fn (string $slug): Branch => Branch::query()->where('slug', $slug)->firstOrFail())
            ->values();
        $expectedStatuses = [
            'pending' => 'Pending',
            'waiting' => 'Waiting',
            'approved' => 'Approved',
            'checked_in' => 'Checked In',
            'in_consultation' => 'In Consultation',
            'billed' => 'Billed',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
        ];
        $statusBranches = [];

        foreach (array_keys($expectedStatuses) as $index => $status) {
            $branch = $branches[$index % $branches->count()];
            $statusBranches[$status] = $branch;
            Appointment::factory()
                ->for($patient)
                ->for($branch)
                ->for($service)
                ->create([
                    'created_by' => $staff->id,
                    'starts_at' => sprintf('2026-10-30 09:%02d:00', $index),
                    'status' => $status,
                ]);
        }

        Appointment::factory()
            ->for($patient)
            ->for($branches[0])
            ->for($service)
            ->create([
                'created_by' => $staff->id,
                'starts_at' => '2026-10-31 09:00:00',
                'status' => 'approved',
            ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Filter by Status')
            ->assertSee('All Statuses')
            ->assertSee('In Consultation')
            ->assertSee('No Show')
            ->assertSee('Filter by Branch')
            ->assertSee('General Trias Branch')
            ->assertSee('Dasmariñas Branch')
            ->assertSee('Trece Branch');

        $this->get(route('dashboard.details', [
            'metric' => 'today_appointments',
            'appointment_branch_id' => 'all',
            'appointment_status' => 'all',
        ]))
            ->assertOk()
            ->assertJsonPath('total', count($expectedStatuses));

        foreach ($expectedStatuses as $status => $label) {
            $this->get(route('dashboard.details', [
                'metric' => 'today_appointments',
                'appointment_branch_id' => $statusBranches[$status]->id,
                'appointment_status' => $status,
            ]))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('records.0.status', $label)
                ->assertJsonPath('records.0.branch', $statusBranches[$status]->name);
        }

        $this->get(route('dashboard.details', [
            'metric' => 'today_appointments',
            'appointment_branch_id' => $branches[0]->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 4);

        $this->get(route('dashboard.details', [
            'metric' => 'today_appointments',
            'appointment_branch_id' => $branches[0]->id,
            'appointment_status' => 'approved',
        ]))
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->get(route('dashboard.details', [
            'metric' => 'today_appointments',
            'appointment_branch_id' => 'all',
            'appointment_status' => 'not-a-status',
        ]))
            ->assertSessionHasErrors('appointment_status');
    }

    public function test_dashboard_does_not_make_the_revenue_card_clickable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("Today's revenue")
            ->assertDontSee('data-dashboard-detail="revenue"', false);
    }

    public function test_patients_cannot_access_dashboard_statistic_details(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($patient->user)
            ->get(route('dashboard.details', ['metric' => 'patients']))
            ->assertForbidden();
    }

    public function test_staff_cannot_access_active_account_details(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('dashboard.details', ['metric' => 'active_staff']))
            ->assertForbidden();
    }
}
