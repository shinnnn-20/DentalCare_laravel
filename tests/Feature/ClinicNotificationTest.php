<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ClinicActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClinicNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_booking_creates_one_database_notification_for_each_active_clinic_user(): void
    {
        $this->travelTo('2026-10-07 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $inactiveStaff = User::factory()->create(['role' => 'staff', 'is_active' => false]);
        $patient = Patient::factory()->for(User::factory()->state([
            'name' => 'Jamie Patient',
            'role' => 'patient',
        ]))->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($patient->user)->post(route('appointments.store'), [
            'service_id' => $service->id,
            'appointment_date' => '2026-10-08',
            'appointment_time' => '14:00',
        ]);

        $response->assertRedirectToRoute('appointments.index');
        $appointment = Appointment::firstOrFail();
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $admin->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $staff->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $inactiveStaff->id)->count());
        $notification = $admin->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);
        $this->assertSame('New Appointment Booked', $notification->data['title']);
        $this->assertSame(
            'Jamie Patient booked an appointment for October 8, 2026 at 2:00 PM.',
            $notification->data['message'],
        );
        $this->assertSame('appointment', $notification->data['related_type']);
        $this->assertSame($appointment->id, $notification->data['related_id']);
        $this->assertStringContainsString('#appointment-'.$appointment->id, $notification->data['url']);
    }

    public function test_patient_cancellation_notifies_staff_with_the_original_appointment_time(): void
    {
        $this->travelTo('2026-10-07 08:00:00');
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->for(User::factory()->state([
            'name' => 'Jamie Patient',
            'role' => 'patient',
        ]))->create();
        $appointment = Appointment::factory()
            ->for($patient)
            ->create([
                'starts_at' => Carbon::parse('2026-10-08 14:00:00'),
                'status' => 'pending',
            ]);

        $response = $this->actingAs($patient->user)->post(route('appointments.transition', [
            $appointment,
            'cancel',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $notification = $staff->notifications()->firstOrFail();
        $this->assertSame('Appointment Cancelled', $notification->data['title']);
        $this->assertSame(
            'Jamie Patient cancelled their appointment scheduled for October 8, 2026 at 2:00 PM.',
            $notification->data['message'],
        );
    }

    public function test_staff_action_notifies_other_clinic_users_and_opening_marks_only_the_recipient_notification_read(): void
    {
        $this->travelTo('2026-10-07 08:00:00');
        $actor = User::factory()->create(['role' => 'admin']);
        $recipient = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->for(User::factory()->state([
            'name' => 'Jamie Patient',
            'role' => 'patient',
        ]))->create();
        $appointment = Appointment::factory()
            ->for($patient)
            ->create([
                'starts_at' => Carbon::parse('2026-10-08 14:00:00'),
                'status' => 'pending',
            ]);

        $this->actingAs($actor)
            ->post(route('appointments.transition', [$appointment, 'approve']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $actor->notifications()->count());
        $notification = $recipient->notifications()->firstOrFail();
        $this->assertSame('Appointment Approved', $notification->data['title']);

        $this->actingAs($recipient)
            ->post(route('clinic.notifications.open', $notification->id))
            ->assertRedirect($notification->data['url']);

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(0, $recipient->unreadNotifications()->count());
    }

    public function test_staff_notification_endpoints_are_role_protected_and_scoped_to_the_authenticated_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = User::factory()->create(['role' => 'patient']);
        $notification = new ClinicActivityNotification(
            'appointment.booked',
            'New Appointment Booked',
            'A patient booked an appointment.',
            route('appointments.index', [], false),
            'appointment',
            1,
        );
        $admin->notify($notification);
        $staff->notify($notification);
        $adminNotification = $admin->notifications()->firstOrFail();

        $this->getJson(route('clinic.notifications.index'))
            ->assertUnauthorized();

        $this->actingAs($patient)
            ->getJson(route('clinic.notifications.index'))
            ->assertForbidden();

        $staffResponse = $this->actingAs($staff)
            ->getJson(route('clinic.notifications.index'));
        $staffResponse->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'notifications');

        $this->actingAs($staff)
            ->postJson(route('clinic.notifications.open', $adminNotification->id))
            ->assertNotFound();
        $this->assertNull($adminNotification->fresh()->read_at);

        $this->actingAs($admin)
            ->postJson(route('clinic.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->assertSame(1, $staff->unreadNotifications()->count());
    }

    public function test_notification_bell_is_rendered_for_admin_and_staff_but_not_patients(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertSee('data-clinic-notifications', false);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertSee('data-clinic-notifications', false);

        $this->actingAs($patient->user)
            ->get(route('dashboard'))
            ->assertDontSee('data-clinic-notifications', false);
    }

    public function test_recording_a_payment_notifies_other_clinic_users_with_a_bill_link(): void
    {
        Config::set('services.clinic_sms.endpoint', null);
        Config::set('services.clinic_sms.token', null);
        Http::preventStrayRequests();

        $actor = User::factory()->create(['role' => 'admin']);
        $recipient = User::factory()->create(['role' => 'staff']);
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->for($patient)->create();
        $bill = Bill::factory()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'created_by' => $actor->id,
            'total' => 500,
            'subtotal' => 500,
        ]);

        $response = $this->actingAs($actor)->post(route('clinic.payments.store', $bill), [
            'method' => 'cash',
            'amount' => '500.00',
            'applied_amount' => '500.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'applied_amount' => '500.00',
        ]);
        $this->assertSame(0, $actor->notifications()->count());
        $notification = $recipient->notifications()->firstOrFail();
        $this->assertSame('Payment Recorded', $notification->data['title']);
        $this->assertStringContainsString('₱500.00', $notification->data['message']);
        $this->assertStringContainsString('bill='.$bill->id, $notification->data['url']);
    }
}
