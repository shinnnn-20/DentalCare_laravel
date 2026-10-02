<?php

namespace Database\Factories;

use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalRecord>
 */
class DentalRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'created_by' => User::factory()->state(['role' => 'admin']),
            'diagnosis' => fake()->sentence(),
            'treatment' => fake()->sentence(),
            'prescription' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->paragraph(),
            'follow_up_date' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
        ];
    }
}
