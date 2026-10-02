<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\RfidLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfidLog>
 */
class RfidLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfid_card_id' => null,
            'patient_id' => Patient::factory(),
            'scanned_by' => User::factory()->state(['role' => 'staff']),
            'uid' => fake()->bothify('??-##-??-##'),
            'result' => 'identified',
            'scanned_at' => now(),
        ];
    }
}
