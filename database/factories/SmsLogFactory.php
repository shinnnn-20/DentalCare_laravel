<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\SmsLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsLog>
 */
class SmsLogFactory extends Factory
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
            'phone' => fake()->phoneNumber(),
            'message' => fake()->sentence(),
            'type' => 'appointment_confirmation',
            'status' => 'queued',
            'error' => null,
            'sent_at' => null,
        ];
    }
}
