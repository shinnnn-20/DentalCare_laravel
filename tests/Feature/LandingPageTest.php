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

        $response = $this->get(route('home'));

        $response
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
            ->assertDontSee('href="tel:', false)
            ->assertSee('Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107')
            ->assertSee('where1=14.313687869546%2C%20120.91615498066')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('images/landing/doc-brigs-dental-care-logo.jpg')
            ->assertDontSee('images/landing/dental-services.jpg')
            ->assertSee('Three convenient branches')
            ->assertSee('General Trias Branch')
            ->assertSee('Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107')
            ->assertSee('<span class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-teal-800">0917 160 0541</span>', false)
            ->assertSee('Trece Branch')
            ->assertSee('7V6G+7QX, Trece Martires - Indang Rd, Inocencio (B. Pook), Trece Martires City, Cavite')
            ->assertSee('Dasmariñas Branch')
            ->assertSee('Unit 8 CITRINE Bldg., Brgy. Sampaloc I (opposite side of Philippine Christian University - PCU), Dasmariñas, Philippines, 4114')
            ->assertSee('0967 284 1871')
            ->assertSee('<span class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-teal-800">0967 284 1871</span>', false)
            ->assertSee('images/landing/general-trias-treatment-room.jpg')
            ->assertSee('images/landing/general-trias-branch-exterior.png')
            ->assertSee('images/landing/general-trias-clinic-room.jpg')
            ->assertSee('images/landing/general-trias-waiting-area.jpg')
            ->assertSee('images/landing/trece-treatment-room.jpg')
            ->assertSee('images/landing/trece-clinic-team.jpg')
            ->assertSee('images/landing/trece-reception.jpg')
            ->assertSee('images/landing/dasmarinas-exterior.jpg')
            ->assertSee('images/landing/dasmarinas-reception.jpg')
            ->assertSee('images/landing/dasmarinas-treatment-room.jpg')
            ->assertSee('View General Trias Branch on Google Maps')
            ->assertSee('View Trece Branch on Google Maps')
            ->assertSee('View Dasmariñas Branch on Google Maps')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false)
            ->assertSee('query=Unit%20F%202nd%20Floor%2C%20QMRC%20Building', false)
            ->assertSee('query=7V6G%2B7QX%2C%20Trece%20Martires', false)
            ->assertSee('query=Unit%208%20CITRINE%20Bldg.', false)
            ->assertSee('© '.now()->year.' Doc Brigs Dental Care. All rights reserved.');

        $this->assertSame(3, substr_count($response->getContent(), 'View on Google Maps'));
    }

    public function test_public_service_cards_are_clickable_and_include_service_descriptions(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('data-service-dialog', false)
            ->assertSee('data-service-dialog-trigger', false)
            ->assertSee('data-service-name="Oral Prophylaxis"', false)
            ->assertSee('data-service-description="A professional cleaning', false)
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('data-service-dialog-title', false)
            ->assertSee('data-service-dialog-description', false)
            ->assertSee('data-service-dialog-close', false)
            ->assertSee('href="'.route('appointments.start').'"', false);
    }

    public function test_booking_entry_shows_login_before_returning_to_appointments(): void
    {
        $user = User::factory()->create();

        $this->get(route('appointments.start'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('appointments.index'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in to continue to your clinic account.')
            ->assertSee('class="fixed left-4 top-4 z-10 sm:left-6 sm:top-6"', false)
            ->assertSee('class="btn-primary" href="'.route('home').'"', false)
            ->assertSee('Back to Home');

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
