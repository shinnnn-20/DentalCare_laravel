<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentAvailabilityBlock;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Branch;
use App\Models\DentalRecord;
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
        $service = Service::factory()->create(['name' => 'Routine dental cleaning']);
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
        $dentist = User::factory()->create([
            'name' => 'Dr. Jose Dentist',
            'role' => 'doctor',
        ]);
        DentalRecord::factory()->for($southPatient)->for($southAppointment)->create([
            'created_by' => $dentist->id,
        ]);
        BillItem::factory()->for($southBill)->create([
            'service_name' => 'Root canal treatment',
        ]);
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
            ->assertJsonPath('records.0.dentist', 'Dr. Jose Dentist')
            ->assertJsonPath('records.0.services', 'Root canal treatment')
            ->assertJsonPath('records.0.amount', '₱475.00');

        $this->get(route('admin.reports.details', [
            'metric' => 'revenue',
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'branch_id' => $northBranch->id,
        ]))
            ->assertOk()
            ->assertJsonPath('records.0.dentist', 'Not recorded')
            ->assertJsonPath('records.0.services', 'Routine dental cleaning');

        $this->get(route('dashboard.details', [
            'metric' => 'today_revenue',
            'branch_id' => $northBranch->id,
            'patient_search' => 'Jose South',
        ]))
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('revenue_total', '₱0.00');
    }

    public function test_reports_summarize_dentist_performance_by_selected_date_and_branch(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', config('clinic.timezone')));
        $northBranch = Branch::factory()->create(['name' => 'North Branch']);
        $southBranch = Branch::factory()->create(['name' => 'South Branch']);
        $service = Service::factory()->create();
        $northDentist = User::factory()->create(['name' => 'Dr. North', 'role' => 'doctor']);
        $southDentist = User::factory()->create(['name' => 'Dr. South', 'role' => 'doctor']);
        $northPatients = collect([
            Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create(),
            Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create(),
        ]);
        $southPatient = Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create();

        $northBills = $northPatients->map(function (Patient $patient, int $index) use ($northBranch, $northDentist, $service): Bill {
            $appointment = Appointment::factory()->for($patient)->for($northBranch)->for($service)->create([
                'starts_at' => '2026-11-03 '.($index === 0 ? '10:00:00' : '11:00:00'),
                'status' => 'completed',
            ]);
            DentalRecord::factory()->for($patient)->for($appointment)->create([
                'created_by' => $northDentist->id,
                'treating_dentist_id' => $northDentist->id,
                'created_at' => '2026-11-03 04:00:00',
            ]);

            return Bill::factory()->for($patient)->for($appointment)->create();
        });
        $southAppointment = Appointment::factory()->for($southPatient)->for($southBranch)->for($service)->create([
            'starts_at' => '2026-11-03 12:00:00',
            'status' => 'completed',
        ]);
        DentalRecord::factory()->for($southPatient)->for($southAppointment)->create([
            'created_by' => $southDentist->id,
            'treating_dentist_id' => $southDentist->id,
            'created_at' => '2026-11-03 04:00:00',
        ]);
        $southBill = Bill::factory()->for($southPatient)->for($southAppointment)->create();
        $previousDayPatient = Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create();
        $previousDayAppointment = Appointment::factory()->for($previousDayPatient)->for($northBranch)->for($service)->create([
            'starts_at' => '2026-11-02 12:00:00',
            'status' => 'completed',
        ]);
        DentalRecord::factory()->for($previousDayPatient)->for($previousDayAppointment)->create([
            'created_by' => $northDentist->id,
            'treating_dentist_id' => $northDentist->id,
            'created_at' => '2026-11-02 04:00:00',
        ]);

        Payment::factory()->for($northBills[0])->create([
            'applied_amount' => '100.00',
            'created_at' => '2026-11-03 04:30:00',
        ]);
        Payment::factory()->for($northBills[1])->create([
            'applied_amount' => '150.00',
            'created_at' => '2026-11-03 05:00:00',
        ]);
        Payment::factory()->for($northBills[1])->create([
            'applied_amount' => '999.00',
            'created_at' => '2026-11-02 04:00:00',
        ]);
        Payment::factory()->for($southBill)->create([
            'applied_amount' => '475.00',
            'created_at' => '2026-11-03 05:30:00',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $allBranchesResponse = $this->actingAs($admin)->get(route('admin.reports', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'dentist_from' => '2026-11-03',
            'dentist_to' => '2026-11-03',
        ]));

        $allBranchesResponse->assertOk()
            ->assertSee('Dentist Performance Statistics')
            ->assertSee('Patients treated')
            ->assertSee('Amount collected')
            ->assertSee('name="dentist_from"', false)
            ->assertSee('value="2026-11-03"', false);
        $allBranchesPerformance = $allBranchesResponse->viewData('dentistPerformance');
        $this->assertCount(2, $allBranchesPerformance);
        $this->assertSame('Dr. North', $allBranchesPerformance[0]['dentist_name']);
        $this->assertSame('North Branch', $allBranchesPerformance[0]['branch']);
        $this->assertSame(2, $allBranchesPerformance[0]['patients_treated']);
        $this->assertSame(2, $allBranchesPerformance[0]['services_performed']);
        $this->assertSame(2, $allBranchesPerformance[0]['completed_appointments']);
        $this->assertSame(250.0, (float) $allBranchesPerformance[0]['revenue']);
        $this->assertSame('Dr. South', $allBranchesPerformance[1]['dentist_name']);
        $this->assertSame(475.0, (float) $allBranchesPerformance[1]['total_collected']);

        $previousDayResponse = $this->get(route('admin.reports', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'dentist_date' => '2026-11-02',
            'branch_id' => $northBranch->id,
        ]))
            ->assertOk()
            ->assertSee('November 2, 2026')
            ->assertSee('Dr. North');
        $previousDayPerformance = $previousDayResponse->viewData('dentistPerformance');
        $this->assertCount(1, $previousDayPerformance);
        $this->assertSame('Dr. North', $previousDayPerformance[0]['dentist_name']);
        $this->assertSame(1, $previousDayPerformance[0]['patients_treated']);
        $this->assertSame(999.0, (float) $previousDayPerformance[0]['total_collected']);

        $southBranchResponse = $this->get(route('admin.reports', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'dentist_date' => '2026-11-03',
            'branch_id' => $northBranch->id,
            'dentist_branch_id' => $southBranch->id,
        ]))
            ->assertOk()
            ->assertSee('South Branch');
        $southBranchPerformance = $southBranchResponse->viewData('dentistPerformance');
        $this->assertCount(1, $southBranchPerformance);
        $this->assertSame('Dr. South', $southBranchPerformance[0]['dentist_name']);
        $this->assertSame('South Branch', $southBranchPerformance[0]['branch']);
        $this->assertSame(475.0, (float) $southBranchPerformance[0]['total_collected']);

        $this->get(route('admin.reports', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'dentist_date' => '2026-11-03',
            'branch_id' => $northBranch->id,
            'dentist_branch_id' => 'all',
        ]))
            ->assertOk()
            ->assertSee('Dr. North')
            ->assertSee('Dr. South')
            ->assertViewHas('dentistPerformance', fn (array $rows): bool => count($rows) === 2
                && $rows[0]['dentist_name'] === 'Dr. North'
                && $rows[1]['dentist_name'] === 'Dr. South');

        $this->get(route('admin.reports', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'branch_id' => $northBranch->id,
        ]))
            ->assertOk()
            ->assertSee('Dr. North')
            ->assertViewHas('dentistPerformance', fn (array $rows): bool => count($rows) === 1
                && $rows[0]['dentist_name'] === 'Dr. North'
                && $rows[0]['branch'] === 'North Branch');
    }

    public function test_dentist_report_allocates_partial_payments_by_service_without_falling_back_to_record_creator(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', config('clinic.timezone')));
        $branch = Branch::factory()->create(['name' => 'Service Attribution Branch']);
        $patient = Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create();
        $firstDentist = User::factory()->create([
            'name' => 'Dr. Ana',
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $secondDentist = User::factory()->create([
            'name' => 'Dr. Ben',
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $firstService = Service::factory()->create(['name' => 'Cleaning']);
        $secondService = Service::factory()->create(['name' => 'Filling']);
        $unattributedService = Service::factory()->create(['name' => 'X-ray']);
        $appointment = Appointment::factory()->for($patient)->for($branch)->for($firstService)->create([
            'starts_at' => '2026-11-03 10:00:00',
            'status' => 'completed',
        ]);
        $appointment->services()->sync([$firstService->id, $secondService->id, $unattributedService->id]);
        DentalRecord::factory()->for($patient)->for($appointment)->create([
            'created_by' => $firstDentist->id,
            'treating_dentist_id' => $firstDentist->id,
            'created_at' => '2026-11-03 04:00:00',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => '600.00',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'discount' => '60.00',
            'total' => '540.00',
            'payment_status' => 'partially_paid',
            'created_at' => '2026-11-03 04:10:00',
        ]);
        BillItem::factory()->for($bill)->for($firstService)->create([
            'treating_dentist_id' => $firstDentist->id,
            'service_name' => 'Cleaning',
            'unit_price' => '100.00',
            'subtotal' => '100.00',
            'created_at' => '2026-11-03 04:10:00',
        ]);
        BillItem::factory()->for($bill)->for($secondService)->create([
            'treating_dentist_id' => $secondDentist->id,
            'service_name' => 'Filling',
            'unit_price' => '200.00',
            'subtotal' => '200.00',
            'created_at' => '2026-11-03 04:10:00',
        ]);
        BillItem::factory()->for($bill)->for($unattributedService)->create([
            'treating_dentist_id' => null,
            'service_name' => 'X-ray',
            'unit_price' => '300.00',
            'subtotal' => '300.00',
            'created_at' => '2026-11-03 04:10:00',
        ]);
        Payment::factory()->for($bill)->create([
            'amount' => '60.00',
            'applied_amount' => '60.00',
            'created_at' => '2026-11-03 05:00:00',
        ]);
        Payment::factory()->for($bill)->create([
            'amount' => '30.00',
            'applied_amount' => '30.00',
            'created_at' => '2026-11-04 05:00:00',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.reports', [
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'dentist_from' => '2026-11-03',
            'dentist_to' => '2026-11-03',
            'dentist_branch_id' => $branch->id,
        ]));

        $response->assertOk()
            ->assertSee('Cleaning')
            ->assertSee('Filling')
            ->assertSee('X-ray')
            ->assertSee('Not recorded');
        $performance = $response->viewData('dentistPerformance');
        $this->assertCount(3, $performance);
        $this->assertSame(['Dr. Ana', 'Dr. Ben', 'Not recorded'], array_column($performance, 'dentist_name'));
        $this->assertSame([10.0, 20.0, 30.0], array_map('floatval', array_column($performance, 'total_collected')));
        $this->assertSame([75.0, 150.0, 225.0], array_map('floatval', array_column($performance, 'outstanding_balance')));
        $this->assertSame([90.0, 180.0, 270.0], array_map('floatval', array_column($performance, 'total_billed')));
        $this->assertSame(60.0, (float) $response->viewData('dentistClinicCollected'));
        $this->assertSame(0.0, (float) $response->viewData('dentistUnattributedCollected'));
        $this->assertSame(
            $response->viewData('dentistClinicCollected'),
            array_sum(array_column($performance, 'total_collected')),
        );

        $this->get(route('admin.reports', [
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'dentist_from' => '2026-11-03',
            'dentist_to' => '2026-11-03',
            'dentist_branch_id' => $branch->id,
            'dentist_id' => $secondDentist->id,
        ]))
            ->assertOk()
            ->assertViewHas('dentistPerformance', fn (array $rows): bool => count($rows) === 1
                && $rows[0]['dentist_name'] === 'Dr. Ben'
                && (float) $rows[0]['total_collected'] === 20.0);
    }

    public function test_dentist_report_includes_add_on_service_created_during_the_selected_period(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', config('clinic.timezone')));
        $branch = Branch::factory()->create(['name' => 'Add-on Report Branch']);
        $patient = Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create();
        $dentist = User::factory()->create([
            'name' => 'Dr. Add-on',
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $originalService = Service::factory()->create(['name' => 'Original Service']);
        $addOnService = Service::factory()->create(['name' => 'Add-on Service']);
        $appointment = Appointment::factory()->for($patient)->for($branch)->for($originalService)->create([
            'starts_at' => '2026-11-02 10:00:00',
            'status' => 'completed',
        ]);
        DentalRecord::factory()->for($patient)->for($appointment)->create([
            'treating_dentist_id' => $dentist->id,
            'created_at' => '2026-11-02 04:00:00',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create([
            'subtotal' => '250.00',
            'total' => '250.00',
            'created_at' => '2026-11-02 04:10:00',
        ]);
        BillItem::factory()->for($bill)->for($addOnService)->create([
            'treating_dentist_id' => $dentist->id,
            'service_name' => 'Add-on Service',
            'is_add_on' => true,
            'unit_price' => '250.00',
            'subtotal' => '250.00',
            'created_at' => '2026-11-03 04:10:00',
        ]);
        Payment::factory()->for($bill)->create([
            'amount' => '50.00',
            'applied_amount' => '50.00',
            'created_at' => '2026-11-03 05:00:00',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.reports', [
            'from' => '2026-11-03',
            'to' => '2026-11-03',
            'dentist_from' => '2026-11-03',
            'dentist_to' => '2026-11-03',
            'dentist_branch_id' => $branch->id,
        ]))->assertOk();

        $performance = $response->viewData('dentistPerformance')[0];
        $this->assertSame(1, $performance['services_performed']);
        $this->assertSame(1, $performance['services_billed']);
        $this->assertSame(1, $performance['patients_treated']);
        $this->assertSame(0, $performance['completed_appointments']);
        $this->assertSame(250.0, (float) $performance['total_billed']);
        $this->assertSame(50.0, (float) $performance['total_collected']);
        $this->assertSame(200.0, (float) $performance['outstanding_balance']);
        $this->assertSame('Add-on Service', $response->viewData('dentistPerformanceDetails')[0]['service']);
    }

    public function test_dentist_performance_counts_treatment_records_created_on_selected_date_even_if_appointment_is_later(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00', config('clinic.timezone')));
        $branch = Branch::factory()->create(['name' => 'Trese Branch']);
        $patient = Patient::factory()->for(User::factory()->state(['role' => 'patient']))->create();
        $service = Service::factory()->create();
        $dentist = User::factory()->create(['name' => 'Juan Delos Santos', 'role' => 'doctor']);
        $appointment = Appointment::factory()->for($patient)->for($branch)->for($service)->create([
            'starts_at' => '2026-11-09 15:00:00',
        ]);
        DentalRecord::factory()->for($patient)->for($appointment)->create([
            'created_by' => $dentist->id,
            'treating_dentist_id' => $dentist->id,
            'created_at' => '2026-11-07 17:23:16',
        ]);
        $bill = Bill::factory()->for($patient)->for($appointment)->create();
        Payment::factory()->for($bill)->create([
            'applied_amount' => '400.00',
            'created_at' => '2026-11-07 17:23:40',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.reports', [
                'dentist_date' => '2026-11-08',
                'dentist_branch_id' => $branch->id,
            ]))
            ->assertOk()
            ->assertSee('Treatment counts use recorded treatment dates')
            ->assertViewHas('dentistPerformance', fn (array $rows): bool => count($rows) === 1
                && $rows[0]['dentist_name'] === 'Juan Delos Santos'
                && $rows[0]['branch'] === 'Trese Branch'
                && $rows[0]['patients_treated'] === 1
                && (float) $rows[0]['total_collected'] === 400.0);
    }

    public function test_dentist_performance_uses_treating_dentist_and_appointment_branch_not_patient_home_branch(): void
    {
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00', config('clinic.timezone')));
        $treceBranch = Branch::factory()->create(['name' => 'Attribution Home Branch']);
        $dasmarinasBranch = Branch::factory()->create(['name' => 'Attribution Service Branch']);
        $patient = Patient::factory()
            ->for(User::factory()->state(['role' => 'patient', 'name' => 'Patient One']))
            ->create(['branch_id' => $treceBranch->id]);
        $treatingDentist = User::factory()->create([
            'name' => 'Dentist One',
            'role' => 'doctor',
            'branch_id' => $dasmarinasBranch->id,
        ]);
        $otherBranchDentist = User::factory()->create([
            'name' => 'Trece Dentist',
            'role' => 'doctor',
            'branch_id' => $treceBranch->id,
        ]);
        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $dasmarinasBranch->id,
        ]);
        $appointment = Appointment::factory()
            ->for($patient)
            ->for($dasmarinasBranch)
            ->for(Service::factory())
            ->create([
                'starts_at' => '2026-11-09 10:00:00',
                'status' => 'in_consultation',
            ]);

        $this->actingAs($staff)
            ->get(route('clinic.records.create', $appointment))
            ->assertSee('Treating dentist')
            ->assertSee('<option value="'.$treatingDentist->id.'"', false)
            ->assertDontSee('<option value="'.$otherBranchDentist->id.'"', false);

        $this->from(route('clinic.records.create', $appointment))
            ->post(route('clinic.records.store', $appointment), [
                'diagnosis' => 'Routine examination',
                'treatment' => 'Dental cleaning',
                'treating_dentist_id' => $otherBranchDentist->id,
            ])
            ->assertSessionHasErrors('treating_dentist_id');
        $this->assertDatabaseMissing('dental_records', ['appointment_id' => $appointment->id]);

        $this->post(route('clinic.records.store', $appointment), [
            'diagnosis' => 'Routine examination',
            'treatment' => 'Dental cleaning',
            'treating_dentist_id' => $treatingDentist->id,
        ])->assertSessionHas('status', 'Dental record saved.');

        $record = DentalRecord::query()->where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame($staff->id, $record->created_by);
        $this->assertSame($treatingDentist->id, $record->treating_dentist_id);
        $this->assertSame($treceBranch->id, $patient->fresh()->branch_id);
        $this->assertSame($dasmarinasBranch->id, $appointment->fresh()->branch_id);

        $bill = Bill::factory()->for($patient)->for($appointment)->create();
        Payment::factory()->for($bill)->create([
            'applied_amount' => '400.00',
            'created_at' => '2026-11-08 02:00:00',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.reports', [
                'dentist_date' => '2026-11-08',
                'dentist_branch_id' => $dasmarinasBranch->id,
            ]))
            ->assertOk()
            ->assertViewHas('dentistPerformance', fn (array $rows): bool => count($rows) === 1
                && $rows[0]['dentist_name'] === 'Dentist One'
                && $rows[0]['branch'] === 'Attribution Service Branch'
                && $rows[0]['patients_treated'] === 1
                && (float) $rows[0]['total_collected'] === 400.0);
    }

    public function test_reports_reject_an_invalid_dentist_performance_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.reports', [
                'dentist_date' => 'not-a-date',
            ]))
            ->assertSessionHasErrors('dentist_date');

        $this->get(route('admin.reports', [
            'dentist_from' => '2026-11-09',
            'dentist_to' => '2026-11-08',
        ]))
            ->assertSessionHasErrors('dentist_to');
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
