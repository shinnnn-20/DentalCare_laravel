<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_can_view_the_public_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('A brighter smile begins with')
            ->assertSee('Book an Appointment');
    }
}
