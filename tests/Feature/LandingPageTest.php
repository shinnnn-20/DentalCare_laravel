<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_displays_clinic_details_and_requested_services(): void
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
            ->assertSee('href="'.route('appointments.start').'"', false)
            ->assertSee('Oral Prophylaxis')
            ->assertSee('Restoration')
            ->assertSee('Extraction')
            ->assertSee('Dentures')
            ->assertSee('Braces')
            ->assertSee('Surgery')
            ->assertSee('Root Canal Therapy')
            ->assertSee('Jacket Crowns')
            ->assertSee('Whitening')
            ->assertSee('Implant')
            ->assertDontSee('Inactive Clinic Service')
            ->assertDontSee('Clinic Catalog Cleaning')
            ->assertSee('0917 160 0541')
            ->assertSee('href="tel:+639171600541"', false)
            ->assertSee('Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107')
            ->assertSee('where1=14.313687869546%2C%20120.91615498066')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('images/landing/doc-brigs-dental-care-logo.jpg')
            ->assertDontSee('images/landing/dental-services.jpg')
            ->assertSee('Three convenient branches')
            ->assertSee('General Trias Branch')
            ->assertSee('Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107')
            ->assertSee('Trece Branch')
            ->assertSee('Dasmariñas Branch')
            ->assertSee('Unit 8 CITRINE Bldg., Brgy. Sampaloc I (opposite side of Philippine Christian University - PCU), Dasmariñas, Philippines, 4114')
            ->assertSee('Monday–Saturday, 8 AM–7 PM; Sunday by appointment')
            ->assertSee('0967 284 1871')
            ->assertSee('href="tel:+639672841871"', false)
            ->assertSee('images/landing/general-trias-treatment-room.jpg')
            ->assertSee('images/landing/general-trias-clinic-room.jpg')
            ->assertSee('images/landing/general-trias-waiting-area.jpg')
            ->assertSee('images/landing/trece-treatment-room.jpg')
            ->assertSee('images/landing/trece-clinic-team.jpg')
            ->assertSee('images/landing/trece-reception.jpg')
            ->assertSee('images/landing/dasmarinas-exterior.jpg')
            ->assertSee('images/landing/dasmarinas-reception.jpg')
            ->assertSee('images/landing/dasmarinas-treatment-room.jpg')
            ->assertSee('© '.now()->year.' Doc Brigs Dental Care. All rights reserved.');
    }

    public function test_booking_entry_shows_login_before_returning_to_appointments(): void
    {
        $user = User::factory()->create();

        $this->get(route('appointments.start'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('appointments.index'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in to continue to your clinic account.');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('appointments.index'));
    }

    public function test_signed_in_patient_sees_login_first_page_with_continue_booking_option(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->get(route('appointments.start'))
            ->assertOk()
            ->assertSee('You’re already signed in.')
            ->assertSee('href="'.route('appointments.index').'"', false)
            ->assertSee('Continue to Book an Appointment');
    }

    public function test_authenticated_homepage_and_existing_portal_routes_remain_available(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Doc Brigs Dental Care')
            ->assertSee(asset('images/landing/doc-brigs-dental-care-logo.jpg'), false);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(asset('images/landing/doc-brigs-dental-care-logo.jpg'), false)
            ->assertSee('alt="Doc Brigs Dental Care logo"', false);
        $this->get(route('appointments.index'))->assertOk();
    }
}
