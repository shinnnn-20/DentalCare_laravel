<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_displays_clinic_details_and_active_services(): void
    {
        Service::factory()->create([
            'name' => 'Clinic Catalog Cleaning',
            'category' => 'Preventive Care',
            'is_active' => true,
        ]);
        Service::factory()->create([
            'name' => 'Inactive Clinic Service',
            'is_active' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Doc Brigs Dental Care')
            ->assertSee('Book an Appointment')
            ->assertSee('Clinic Catalog Cleaning')
            ->assertDontSee('Inactive Clinic Service')
            ->assertSee('0917 160 0541')
            ->assertSee('href="tel:+639171600541"', false)
            ->assertSee('Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107')
            ->assertSee('where1=14.313687869546%2C%20120.91615498066')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('images/landing/doc-brigs-logo.png')
            ->assertSee('images/landing/clinic-exterior.png')
            ->assertSee('images/landing/clinic-services.png')
            ->assertSee('images/landing/clinic-interior.png')
            ->assertSee('© '.now()->year.' Doc Brigs Dental Care. All rights reserved.');
    }

    public function test_authenticated_homepage_and_existing_portal_routes_remain_available(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Doc Brigs Dental Care');

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('appointments.index'))->assertOk();
    }
}
