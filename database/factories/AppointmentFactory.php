<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'service_id' => Service::factory(),
            'created_by' => User::factory()->state(['role' => 'staff']),
            'type' => 'online',
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
