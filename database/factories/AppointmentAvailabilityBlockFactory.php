<?php

namespace Database\Factories;

use App\Models\AppointmentAvailabilityBlock;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentAvailabilityBlock>
 */
class AppointmentAvailabilityBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'created_by' => User::factory(),
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'reason' => 'Long dental procedure',
            'description' => null,
            'is_active' => true,
        ];
    }
}
