<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('clinicRoles')]
    public function test_staff_and_doctors_can_change_their_own_password(string $role): void
    {
        $user = User::factory()->create([
            'role' => $role,
            'password' => 'Initial-password!',
        ]);
        $otherUser = User::factory()->create([
            'role' => 'doctor',
            'password' => 'Other-account-password!',
        ]);

        $this->actingAs($user)
            ->get(route('account.password.edit'))
            ->assertOk()
            ->assertSee('Current password')
            ->assertSee('Confirm new password')
            ->assertSee(route('account.password.update'));

        $this->put(route('account.password.update'), [
            'current_password' => 'Initial-password!',
            'password' => 'Updated-password!',
            'password_confirmation' => 'Updated-password!',
            'user_id' => $otherUser->id,
        ])
            ->assertRedirect(route('account.password.edit'))
            ->assertSessionHas('status', 'Your password has been changed.');

        $this->assertTrue(Hash::check('Updated-password!', $user->fresh()->password));
        $this->assertTrue(Hash::check('Other-account-password!', $otherUser->fresh()->password));
    }

    public function test_current_password_must_match_before_changing_password(): void
    {
        $user = User::factory()->create([
            'role' => 'doctor',
            'password' => 'Initial-password!',
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'Incorrect-password!',
                'password' => 'Updated-password!',
                'password_confirmation' => 'Updated-password!',
            ])
            ->assertSessionHasErrors(['current_password' => 'The password is incorrect.']);

        $this->assertTrue(Hash::check('Initial-password!', $user->fresh()->password));
    }

    public function test_new_password_confirmation_must_match(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
            'password' => 'Initial-password!',
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'Initial-password!',
                'password' => 'Updated-password!',
                'password_confirmation' => 'Different-password!',
            ])
            ->assertSessionHasErrors(['password' => 'The password field confirmation does not match.']);

        $this->assertTrue(Hash::check('Initial-password!', $user->fresh()->password));
    }

    public function test_password_change_uses_the_shared_password_policy(): void
    {
        $user = User::factory()->create([
            'role' => 'doctor',
            'password' => 'Initial-password!',
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'Initial-password!',
                'password' => 'weakpassword1',
                'password_confirmation' => 'weakpassword1',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Initial-password!', $user->fresh()->password));
    }

    public function test_patients_cannot_open_the_staff_password_change_page(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->get(route('account.password.edit'))
            ->assertForbidden();
    }

    public static function clinicRoles(): array
    {
        return [
            'staff' => ['staff'],
            'doctor' => ['doctor'],
        ];
    }
}
