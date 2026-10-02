<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QueueEntry>
 */
class QueueEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'queue_date' => today(),
            'queue_number' => 'Q-'.Str::upper(Str::random(8)),
            'status' => 'waiting',
        ];
    }
}
