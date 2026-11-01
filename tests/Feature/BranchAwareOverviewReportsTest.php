<?php

namespace Tests\Feature;

use App\Models\Appointment;
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
