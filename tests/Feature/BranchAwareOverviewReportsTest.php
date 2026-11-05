<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentAvailabilityBlock;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BranchAwareOverviewReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_summarize_active_blocked_hours_by_month_or_year_and_selected_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'North Branch']);
        $otherBranch = Branch::factory()->create(['name' => 'South Branch']);
        AppointmentAvailabilityBlock::factory()->for($branch)->create([
            'starts_at' => '2026-11-03 10:00:00',
            'ends_at' => '2026-11-03 12:00:00',
        ]);
        AppointmentAvailabilityBlock::factory()->for($branch)->create([
            'starts_at' => '2026-12-03 10:00:00',
            'ends_at' => '2026-12-03 13:00:00',
            'is_active' => false,
        ]);
        AppointmentAvailabilityBlock::factory()->for($branch)->create([
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 11:30:00',
        ]);
        AppointmentAvailabilityBlock::factory()->for($otherBranch)->create([
            'starts_at' => '2026-11-03 09:00:00',
            'ends_at' => '2026-11-03 10:00:00',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $filters = [
            'from' => '2026-11-01',
            'to' => '2027-01-31',
            'branch_id' => $branch->id,
        ];

        $this->actingAs($admin)
            ->get(route('admin.reports', [...$filters, 'availability_period' => 'month']))
            ->assertOk()
            ->assertSee('Nov 2026')
            ->assertSee('Jan 2027')
            ->assertSee('3.50')
            ->assertSee('Active blocked intervals');

        $this->get(route('admin.reports', [...$filters, 'availability_period' => 'year']))
            ->assertOk()
            ->assertSee('2026')
            ->assertSee('2027')
            ->assertSee('2.00')
            ->assertSee('1.50');
    }

    public function test_revenue_details_filter_by_branch_and_patient_on_overview_and_reports(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', config('clinic.timezone')));
        $northBranch = Branch::factory()->create(['name' => 'North Branch']);
        $southBranch = Branch::factory()->create(['name' => 'South Branch']);
        $service = Service::factory()->create();
        $northPatient = Patient::factory()->for(User::factory()->state([
            'role' => 'patient',
            'name' => 'Maria North',
        ]))->create(['patient_number' => 'P-NORTH-001']);
        $southPatient = Patient::factory()->for(User::factory()->state([
            'role' => 'patient',
            'name' => 'Jose South',
        ]))->create(['patient_number' => 'P-SOUTH-001']);
        $northAppointment = Appointment::factory()->for($northPatient)->for($northBranch)->for($service)->create([
            'starts_at' => '2026-11-03 10:00:00',
        ]);
        $southAppointment = Appointment::factory()->for($southPatient)->for($southBranch)->for($service)->create([
            'starts_at' => '2026-11-03 11:00:00',
        ]);
        $northBill = Bill::factory()->for($northPatient)->for($northAppointment)->create();
        $southBill = Bill::factory()->for($southPatient)->for($southAppointment)->create();
        Payment::factory()->for($northBill)->create([
            'applied_amount' => '125.00',
            'created_at' => '2026-11-03 04:00:00',
        ]);
        Payment::factory()->for($southBill)->create([
            'applied_amount' => '475.00',
            'created_at' => '2026-11-03 04:30:00',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard.details', [
                'metric' => 'today_revenue',
                'branch_id' => $northBranch->id,
                'patient_search' => 'P-NORTH-001',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('revenue_total', '₱125.00')
            ->assertJsonPath('records.0.name', 'Maria North')
            ->assertJsonPath('records.0.branch', 'North Branch')
            ->assertJsonPath('records.0.amount', '₱125.00');

        $this->get(route('admin.reports.details', [
            'metric' => 'revenue',
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'branch_id' => $southBranch->id,
            'patient_search' => 'Jose South',
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('revenue_total', '₱475.00')
            ->assertJsonPath('records.0.name', 'Jose South')
            ->assertJsonPath('records.0.branch', 'South Branch')
            ->assertJsonPath('records.0.amount', '₱475.00');

        $this->get(route('dashboard.details', [
            'metric' => 'today_revenue',
            'branch_id' => $northBranch->id,
            'patient_search' => 'Jose South',
        ]))
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('revenue_total', '₱0.00');
    }

    public function test_doctors_cannot_view_today_revenue_or_reports(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', config('clinic.timezone')));
        $branch = Branch::factory()->create(['name' => 'Doctor Branch']);
        $otherBranch = Branch::factory()->create(['name' => 'Other Branch']);
        $service = Service::factory()->create();
        $patient = Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create();
        $otherPatient = Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create();
        $appointment = Appointment::factory()->for($patient)->for($branch)->for($service)->create([
            'starts_at' => '2026-11-03 10:00:00',
        ]);
        $otherAppointment = Appointment::factory()->for($otherPatient)->for($otherBranch)->for($service)->create([
            'starts_at' => '2026-11-03 11:00:00',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create();
        $otherBill = Bill::factory()->for($otherPatient)->for($otherAppointment)->create();
        Payment::factory()->for($bill)->create([
            'applied_amount' => '125.00',
            'created_at' => '2026-11-03 04:00:00',
        ]);
        Payment::factory()->for($otherBill)->create([
            'applied_amount' => '475.00',
            'created_at' => '2026-11-03 04:30:00',
        ]);
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($doctor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("Today's revenue");

        $this->get(route('dashboard.details', ['metric' => 'today_revenue']))
            ->assertForbidden();

        $this->get(route('admin.reports', [
            'from' => '2026-11-03',
            'to' => '2026-11-03',
        ]))->assertForbidden();

        $this->get(route('admin.reports', [
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'branch_id' => $otherBranch->id,
        ]))->assertForbidden();

        $this->get(route('admin.reports.details', [
            'metric' => 'revenue',
            'from' => '2026-11-03',
            'to' => '2026-11-03',
        ]))->assertForbidden();

        $this->getJson(route('admin.reports.details', [
            'metric' => 'revenue',
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'branch_id' => $otherBranch->id,
        ]))->assertForbidden();

        $this->get(route('admin.audit-logs'))->assertForbidden();
    }

    public function test_overview_and_reports_filter_appointments_revenue_and_print_branch_context(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00', config('clinic.timezone')));

        $northBranch = Branch::factory()->create(['name' => 'North Branch']);
        $southBranch = Branch::factory()->create(['name' => 'South Branch']);
        $service = Service::factory()->create();
        $northPatient = Patient::factory()->create();
        $southPatient = Patient::factory()->create();
        $unassignedPatient = Patient::factory()->create();
        $northAppointment = Appointment::factory()->for($northPatient)->for($service)->create([
            'branch_id' => $northBranch->id,
            'starts_at' => '2026-10-30 09:00:00',
            'status' => 'completed',
        ]);
        $southAppointment = Appointment::factory()->for($southPatient)->for($service)->create([
            'branch_id' => $southBranch->id,
            'starts_at' => '2026-10-30 10:00:00',
            'status' => 'pending',
        ]);
        $unassignedAppointment = Appointment::factory()->for($unassignedPatient)->for($service)->create([
            'branch_id' => null,
            'starts_at' => '2026-10-30 11:00:00',
            'status' => 'cancelled',
        ]);
        $northBill = Bill::factory()->for($northPatient)->for($northAppointment)->create([
            'payment_status' => 'partially_paid',
        ]);
        $southBill = Bill::factory()->for($southPatient)->for($southAppointment)->create([
            'payment_status' => 'partially_paid',
        ]);
        $unassignedBill = Bill::factory()->for($unassignedPatient)->for($unassignedAppointment)->create([
            'payment_status' => 'partially_paid',
        ]);
        Payment::factory()->for($northBill)->create([
            'applied_amount' => 100,
            'created_at' => '2026-10-30 11:00:00',
        ]);
        Payment::factory()->for($southBill)->create([
            'applied_amount' => 250,
            'created_at' => '2026-10-30 11:00:00',
        ]);
        Payment::factory()->for($unassignedBill)->create([
            'applied_amount' => 50,
            'created_at' => '2026-10-30 11:00:00',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard', [
                'branch_id' => $northBranch->id,
                'statistics_period' => 'week',
            ]))
            ->assertOk()
            ->assertSee('North Branch')
            ->assertViewHas('statistics', function (array $statistics): bool {
                return array_sum(array_column($statistics['timeline'], 'appointments')) === 1
                    && array_sum(array_column($statistics['timeline'], 'revenue')) === 100.0
                    && count($statistics['statuses']) === 1
                    && $statistics['statuses'][0]['label'] === 'Completed';
            })
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics[0][1] === '1');

        $this->get(route('admin.reports', [
            'from' => '2026-10-30',
            'to' => '2026-10-30',
            'branch_id' => $northBranch->id,
        ]))
            ->assertOk()
            ->assertSee(asset('images/landing/doc-brigs-dental-care-logo.jpg'), false)
            ->assertSee('data-report-branch="North Branch"', false)
            ->assertSee('Branch: <strong>North Branch</strong>', false)
            ->assertViewHas('appointments', 1)
            ->assertViewHas('walkIns', 0)
            ->assertViewHas('completed', 1)
            ->assertViewHas('revenue', 100)
            ->assertViewHas('newPatients', 1)
            ->assertViewHas('timeline', fn (array $timeline): bool => array_sum(array_column($timeline, 'appointments')) === 1
                && array_sum(array_column($timeline, 'revenue')) === 100.0);

        $this->get(route('dashboard', [
            'branch_id' => 'unassigned',
            'statistics_period' => 'week',
        ]))
            ->assertOk()
            ->assertViewHas('selectedBranchName', 'Unassigned appointments')
            ->assertViewHas('statistics', fn (array $statistics): bool => array_sum(array_column($statistics['timeline'], 'appointments')) === 1
                && array_sum(array_column($statistics['timeline'], 'revenue')) === 50.0);

        $this->get(route('admin.reports', [
            'from' => '2026-10-30',
            'to' => '2026-10-30',
            'branch_id' => 'unassigned',
        ]))
            ->assertOk()
            ->assertSee('data-report-branch="Unassigned appointments"', false)
            ->assertViewHas('appointments', 1)
            ->assertViewHas('cancelled', 1)
            ->assertViewHas('revenue', 50);
    }

    public function test_overview_and_reports_reject_unknown_branch_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard', ['branch_id' => '999999']))
            ->assertSessionHasErrors('branch_id');

        $this->get(route('admin.reports', [
            'from' => '2026-10-30',
            'to' => '2026-10-30',
            'branch_id' => '999999',
        ]))
            ->assertSessionHasErrors('branch_id');
    }
}
