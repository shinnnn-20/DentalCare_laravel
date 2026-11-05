<?php

namespace Tests\Feature;

use App\Models\DentalRecord;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientFollowUpPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_dashboard_highlights_their_next_upcoming_follow_up(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00', config('clinic.timezone')));
        $patient = Patient::factory()->create();
        $nextFollowUp = DentalRecord::factory()->for($patient)->create([
            'treatment' => 'Follow-up cleaning',
            'follow_up_date' => '2026-11-12',
        ]);
        DentalRecord::factory()->for($patient)->create([
            'treatment' => 'Later follow-up',
            'follow_up_date' => '2026-11-19',
        ]);
        DentalRecord::factory()->for($patient)->create([
            'treatment' => 'Past follow-up',
            'follow_up_date' => '2026-11-04',
        ]);
        DentalRecord::factory()->for(Patient::factory())->create([
            'treatment' => 'Another patient follow-up',
            'follow_up_date' => '2026-11-10',
        ]);

        $response = $this->actingAs($patient->user)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertViewHas('nextFollowUp', fn (DentalRecord $record): bool => $record->id === $nextFollowUp->id)
            ->assertSee('Upcoming follow-up')
            ->assertSee('Thursday, November 12, 2026')
            ->assertSee('Follow-up cleaning')
            ->assertSee(route('patient.records'), false)
            ->assertDontSee('Another patient follow-up');

        $this->get(route('patient.records'))
            ->assertSee('Follow-up date')
            ->assertSee('datetime="2026-11-12"', false)
            ->assertSee('Nov 12, 2026');
    }

    public function test_patient_dashboard_hides_upcoming_follow_up_reminder_when_only_past_follow_ups_exist(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00', config('clinic.timezone')));
        $patient = Patient::factory()->create();
        DentalRecord::factory()->for($patient)->create([
            'follow_up_date' => '2026-11-04',
        ]);

        $this->actingAs($patient->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Upcoming follow-up');
    }

    public function test_patient_dashboard_shows_only_the_five_most_recent_dental_records(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00', config('clinic.timezone')));
        $patient = Patient::factory()->create();
        $recordIds = [];

        foreach (range(1, 6) as $number) {
            $createdAt = Carbon::parse('2026-11-01 09:00:00', config('clinic.timezone'))->addDays($number);
            $recordIds[] = DentalRecord::factory()->for($patient)->create([
                'treatment' => 'Overview record '.$number,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->id;
        }

        $this->actingAs($patient->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('recentRecords', fn ($recentRecords): bool => $recentRecords->modelKeys() === array_reverse(array_slice($recordIds, 1)))
            ->assertSee('Overview record 6')
            ->assertSee('Overview record 5')
            ->assertSee('Overview record 4')
            ->assertSee('Overview record 3')
            ->assertSee('Overview record 2')
            ->assertDontSee('Overview record 1');
    }
}
