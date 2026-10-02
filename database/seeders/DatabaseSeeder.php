<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            ['Consultation', 500],
            ['Dental Cleaning', 1000],
            ['Tooth Extraction', 1500],
            ['Dental Filling', 1800],
            ['Root Canal', 6500],
            ['Dental X-Ray', 800],
            ['Teeth Whitening', 5000],
        ] as [$name, $price]) {
            Service::firstOrCreate(
                ['name' => $name],
                ['price' => $price, 'is_active' => true],
            );
        }
    }
}
