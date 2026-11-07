<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_service_descriptions_and_estimated_durations(): void
    {
        $legacyService = Service::factory()->create([
            'name' => 'Dental Cleaning',
            'description' => null,
            'duration_minutes' => 30,
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('services', [
            'id' => $legacyService->id,
            'description' => 'A routine professional cleaning to remove plaque and tartar deposits and polish the teeth.',
            'duration_minutes' => 30,
        ]);
        $this->assertDatabaseHas('services', [
            'name' => 'Root Canal — Mono-Rooted Tooth (Anterior)',
            'description' => 'Root canal treatment for a front tooth with one root, including cleaning and sealing the canal.',
            'duration_minutes' => 90,
        ]);
        $this->assertSame(
            0,
            Service::query()->whereNull('branch_id')->whereNull('description')->count(),
        );

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('clinic.services.index'))
            ->assertSee('Acrylic / Plastic Crown')
            ->assertSee('A prefabricated acrylic crown')
            ->assertSee('Estimated chair time per appointment:');
    }

    public function test_database_seeder_copies_catalog_details_to_blank_branch_overrides_without_overwriting_custom_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $baseService = Service::query()
            ->whereNull('branch_id')
            ->where('name', 'Acrylic / Plastic Crown')
            ->firstOrFail();
        $baseService->update([
            'description' => 'Clinic-specific crown description.',
            'duration_minutes' => 70,
        ]);
        $branch = Branch::factory()->create();
        $branchOverride = Service::factory()->create([
            'name' => $baseService->name,
            'branch_id' => $branch->id,
            'base_service_id' => $baseService->id,
            'description' => null,
            'duration_minutes' => 30,
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('services', [
            'id' => $branchOverride->id,
            'description' => 'Clinic-specific crown description.',
            'duration_minutes' => 70,
        ]);
    }
}
