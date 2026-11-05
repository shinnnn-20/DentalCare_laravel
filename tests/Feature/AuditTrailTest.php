<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_entries_store_the_specific_description_provided_by_the_action(): void
    {
        $admin = User::factory()->create([
            'name' => 'Clinic Admin',
            'role' => 'admin',
        ]);
        $service = Service::factory()->create([
            'name' => 'Dental Cleaning',
            'price' => '250.00',
            'duration_minutes' => 30,
        ]);

        app(AuditTrail::class)->record(
            $admin,
            'service_created',
            'services',
            $service,
            'Created service "Dental Cleaning" at PHP 250.00, duration 30 minutes, for all branches (shared service).',
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'service_created',
            'record_type' => 'Service',
            'record_id' => (string) $service->id,
            'description' => 'Created service "Dental Cleaning" at PHP 250.00, duration 30 minutes, for all branches (shared service).',
        ]);
    }

    public function test_audit_entries_reject_a_blank_description(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(AuditTrail::class)->record($admin, 'service_updated', 'services', $service, '   ');
    }

    public function test_service_update_audit_describes_name_price_and_deactivation_changes(): void
    {
        $admin = User::factory()->create([
            'name' => 'Clinic Admin',
            'role' => 'admin',
        ]);
        $service = Service::factory()->create([
            'name' => 'Acrylic Crown',
            'price' => '100.00',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), [
                'name' => 'Plastic Crown',
                'price' => '150.00',
                'requires_price_confirmation' => false,
                'duration_minutes' => 30,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service_updated',
            'record_type' => 'Service',
            'record_id' => (string) $service->id,
            'description' => 'Clinic Admin updated service "Plastic Crown": name changed from "Acrylic Crown" to "Plastic Crown"; price changed from PHP 100.00 to PHP 150.00; service deactivated.',
        ]);
    }

    public function test_service_activation_audit_names_the_activation(): void
    {
        $admin = User::factory()->create([
            'name' => 'Clinic Admin',
            'role' => 'admin',
        ]);
        $service = Service::factory()->create([
            'name' => 'Dental Cleaning',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), [
                'name' => $service->name,
                'price' => $service->price,
                'requires_price_confirmation' => false,
                'duration_minutes' => 30,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service_activated',
            'record_type' => 'Service',
            'record_id' => (string) $service->id,
            'description' => 'Clinic Admin activated service "Dental Cleaning".',
        ]);
    }

    public function test_service_deactivation_audit_names_the_deactivation(): void
    {
        $admin = User::factory()->create([
            'name' => 'Clinic Admin',
            'role' => 'admin',
        ]);
        $service = Service::factory()->create([
            'name' => 'Dental Cleaning',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), [
                'name' => $service->name,
                'price' => $service->price,
                'requires_price_confirmation' => false,
                'duration_minutes' => 30,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service_deactivated',
            'record_type' => 'Service',
            'record_id' => (string) $service->id,
            'description' => 'Clinic Admin deactivated service "Dental Cleaning".',
        ]);
    }

    public function test_audit_logs_show_a_contextual_description_when_older_entries_have_none(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AuditLog::factory()->create([
            'action' => 'appointment_rescheduled',
            'module' => 'appointments',
            'record_type' => 'Appointment',
            'record_id' => '51',
            'description' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs'))
            ->assertSee('Historical event: appointment rescheduled for Appointment #51. Details were not captured when this event occurred.');
    }
}
