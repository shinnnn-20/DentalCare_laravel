<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'patient']),
            'patient_number' => fake()->unique()->numerify('P-2026-#####'),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['female', 'male', 'other', 'prefer_not_to_say']),
            'address' => fake()->address(),
            'emergency_contact' => fake()->phoneNumber(),
        ];
    }
}
