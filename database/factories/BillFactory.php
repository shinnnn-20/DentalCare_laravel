<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_number' => 'B-'.Str::upper(Str::random(12)),
            'patient_id' => ($patient = Patient::factory()),
            'appointment_id' => Appointment::factory()->for($patient),
            'created_by' => User::factory()->state(['role' => 'staff']),
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'payment_status' => 'unpaid',
        ];
    }
}
