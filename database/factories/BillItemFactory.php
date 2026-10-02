<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillItem>
 */
class BillItemFactory extends Factory
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
            'service_id' => Service::factory(),
            'service_name' => fake()->words(2, true),
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ];
    }
}
