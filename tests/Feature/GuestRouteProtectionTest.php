<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestRouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_opening_protected_routes_directly(): void
    {
        $protectedRoutes = [
            'dashboard',
            'appointments.index',
            'appointments.availability',
            'patient.billing',
            'clinic.patients.index',
            'clinic.billing.index',
            'admin.staff.index',
        ];

        foreach ($protectedRoutes as $routeName) {
            $this->get(route($routeName))
                ->assertRedirectToRoute('login');
        }
    }

    public function test_guest_cannot_submit_an_appointment_booking_directly(): void
    {
        $this->post(route('appointments.store'))
            ->assertRedirectToRoute('login');

        $this->assertDatabaseCount('appointments', 0);
    }
}
