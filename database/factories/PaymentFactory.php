<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_id' => Bill::factory(),
            'transaction_id' => 'TXN-'.Str::upper(Str::random(12)),
            'amount' => 1000,
            'applied_amount' => 1000,
            'change_amount' => 0,
            'method' => 'cash',
            'processed_by' => User::factory()->state(['role' => 'staff']),
        ];
    }
}
