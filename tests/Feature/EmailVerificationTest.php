<?php

namespace Tests\Feature;

use App\Enums\EmailVerificationResult;
use App\Mail\VerifyPatientEmail;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_hashed_six_digit_code_and_shows_the_verification_screen(): void
    {
        Mail::fake();

        $response = $this->post(route('register.store'), $this->registrationData());

        $user = User::query()->where('email', 'patient@example.test')->firstOrFail();
        $code = $this->sentCode();
        $this->assertNull($user->email_verified_at);
        $this->assertGuest();
        $response->assertRedirectToRoute('verification.notice')
            ->assertSessionHas('verification_user_id', $user->id);
        $this->get(route('verification.notice'))
            ->assertSee('Verify Your Email')
            ->assertSee('We sent a 6-digit verification code')
            ->assertSee('name="code"', false)
            ->assertSee('Resend Code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
        ]);
        Mail::assertSent(VerifyPatientEmail::class, fn (VerifyPatientEmail $mail): bool => $mail->hasTo('patient@example.test'));
    }

    public function test_correct_code_verifies_once_and_patient_can_log_in_normally(): void
    {
        Mail::fake();
        $this->post(route('register.store'), $this->registrationData());
        $user = User::query()->where('email', 'patient@example.test')->firstOrFail();
        $code = $this->sentCode();

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertViewHas('result', EmailVerificationResult::Verified)
            ->assertSee('Email Verified Successfully!');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_tokens', ['user_id' => $user->id]);

        $this->post(route('login.store'), [
            'email' => 'patient@example.test',
            'password' => 'secure-password-123',
        ])->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);

        $this->withSession(['verification_user_id' => $user->id])
            ->post(route('verification.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Invalid verification code. Please try again.']);
    }

    public function test_expired_code_is_rejected_and_removed(): void
    {
        Mail::fake();
        $this->post(route('register.store'), $this->registrationData());
        $user = User::query()->where('email', 'patient@example.test')->firstOrFail();
        $code = $this->sentCode();
        DB::table('email_verification_tokens')->where('user_id', $user->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'This verification code has expired. Please request a new code.']);

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_tokens', ['user_id' => $user->id]);
    }

    public function test_incorrect_code_does_not_verify_an_account(): void
    {
        Mail::fake();
        $this->post(route('register.store'), $this->registrationData());
        $user = User::query()->where('email', 'patient@example.test')->firstOrFail();

        $this->post(route('verification.verify'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'Invalid verification code. Please try again.']);
        $this->post(route('verification.verify'), ['code' => '12ab56'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $user->id]);
    }

    public function test_resend_replaces_the_code_and_enforces_a_cooldown(): void
    {
        Mail::fake();
        $this->post(route('register.store'), $this->registrationData());
        $user = User::query()->where('email', 'patient@example.test')->firstOrFail();
        $firstCode = $this->sentCode();
        $firstHash = hash_hmac('sha256', $firstCode, (string) config('app.key'));

        $this->post(route('verification.resend'))
            ->assertSessionHas('status', 'Please wait before requesting another verification code.');
        Mail::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->post(route('verification.resend'))
            ->assertSessionHas('status', 'A new verification code was sent to your email address.');

        $secondCode = $this->sentCode(1);
        $this->assertNotSame($firstCode, $secondCode);
        $this->assertDatabaseMissing('email_verification_tokens', [
            'user_id' => $user->id,
            'token_hash' => $firstHash,
        ]);
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash_hmac('sha256', $secondCode, (string) config('app.key')),
        ]);
        $this->post(route('verification.verify'), ['code' => $firstCode])
            ->assertSessionHasErrors(['code' => 'Invalid verification code. Please try again.']);
        $this->post(route('verification.verify'), ['code' => $secondCode])
            ->assertViewHas('result', EmailVerificationResult::Verified);
        Mail::assertSentCount(2);
    }

    public function test_unverified_patient_can_log_in_without_a_verification_prompt(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.test',
            'password' => 'secure-password-123',
            'role' => 'patient',
        ]);
        Patient::factory()->create(['user_id' => $user->id]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'secure-password-123',
        ])->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Verify Your Email');
    }

    public function test_admin_and_staff_can_still_log_in_without_patient_email_verification(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->unverified()->create([
                'email' => $role.'@example.test',
                'password' => 'secure-password-123',
                'role' => $role,
            ]);

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'secure-password-123',
            ])->assertRedirectToRoute('dashboard');

            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_resend_is_not_available_outside_the_registration_verification_session(): void
    {
        Mail::fake();

        $this->post(route('verification.resend'))
            ->assertRedirectToRoute('login')
            ->assertSessionHas('status', 'Your registration verification session has expired. Please register again.');

        Mail::assertSentCount(0);
    }

    public function test_email_delivery_failure_is_reported_and_does_not_verify_or_delete_the_patient(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('patient@example.test')
            ->andThrow(new RuntimeException('SMTP service unavailable.'));

        $this->post(route('register.store'), $this->registrationData())
            ->assertRedirectToRoute('verification.notice')
            ->assertSessionHasErrors('email');

        $user = User::query()->where('email', 'patient@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertModelExists($user);
        $this->assertDatabaseMissing('email_verification_tokens', ['user_id' => $user->id]);
        $this->get(route('verification.notice'))->assertSee('Verify Your Email');
    }

    private function registrationData(): array
    {
        return [
            'name' => 'Jamie Patient',
            'date_of_birth' => '1990-03-10',
            'gender' => 'prefer_not_to_say',
            'phone' => '09170000000',
            'email' => 'patient@example.test',
            'address' => 'Clinic Street',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
        ];
    }

    private function sentCode(int $messageIndex = 0): string
    {
        $messages = Mail::sent(VerifyPatientEmail::class);
        $message = $messages->values()->get($messageIndex);

        return $message->verificationCode;
    }
}
