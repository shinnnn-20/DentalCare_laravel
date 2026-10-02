<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\RfidCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfidCard>
 */
class RfidCardFactory extends Factory
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
            'uid' => fake()->unique()->bothify('??-##-??-##'),
            'is_active' => true,
        ];
    }
}
