<?php

namespace Tests\Feature;

use App\Enums\EmailVerificationResult;
use App\Mail\VerifyPatientEmail;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_request_a_reset_link_and_reset_their_password(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'patient@example.test',
            'password' => 'Old-password-123!',
        ]);
        $oldRememberToken = $user->remember_token;

        $this->get(route('login'))
            ->assertSee(route('password.request'), false);

        $this->post(route('password.email'), ['email' => 'PATIENT@example.test'])
            ->assertSessionHas('status', 'If an account exists for that email address, a password reset link will be sent.');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertIsString($token);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('name="token"', false);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-password-123!',
            'password_confirmation' => 'New-password-123!',
        ])->assertRedirectToRoute('login')
            ->assertSessionHas('status', 'Your password has been reset. You can now sign in.');

        $this->assertTrue(Hash::check('New-password-123!', $user->fresh()->password));
        $this->assertNotSame($oldRememberToken, $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_reset_request_does_not_disclose_unknown_emails(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertSessionHas('status', 'If an account exists for that email address, a password reset link will be sent.');

        Notification::assertNothingSent();
    }

    public function test_password_reset_rejects_an_invalid_token_without_changing_the_password(): void
    {
        $user = User::factory()->create([
            'email' => 'patient@example.test',
            'password' => 'Old-password-123!',
        ]);
        $originalPassword = $user->password;

        $this->post(route('password.update'), [
            'token' => 'invalid-reset-token',
            'email' => $user->email,
            'password' => 'New-password-123!',
            'password_confirmation' => 'New-password-123!',
        ])->assertSessionHasErrors([
            'email' => 'This password reset link is invalid or has expired. Request a new one and try again.',
        ]);

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_password_reset_enforces_password_requirements(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'patient@example.test',
            'password' => 'Old-password-123!',
        ]);
        $token = Password::createToken($user);
        $originalPassword = $user->password;

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123!',
            'password_confirmation' => 'new-password-123!',
        ])->assertSessionHasErrors('password');

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

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
        Mail::assertSent(VerifyPatientEmail::class, function (VerifyPatientEmail $mail): bool {
            return $mail->hasTo('patient@example.test')
                && str_contains($mail->render(), 'Doc Brigs Dental Care');
        });
    }

    #[DataProvider('invalidRegistrationIdentityFields')]
    public function test_registration_rejects_invalid_name_email_and_contact_number(
        string $field,
        string $value,
        string $errorMessage,
    ): void {
        $this->post(route('register.store'), [
            ...$this->registrationData(),
            $field => $value,
        ])->assertSessionHasErrors([$field => $errorMessage]);

        $this->assertDatabaseMissing('users', ['email' => 'patient@example.test']);
    }

    public static function invalidRegistrationIdentityFields(): array
    {
        return [
            'name contains digits' => ['name', 'Jamie Patient 2', 'The name field format is invalid.'],
            'email has invalid format' => ['email', 'not-an-email', 'The email field must be a valid email address.'],
            'phone exceeds eleven digits' => [
                'phone',
                '091700000001',
                'The phone field must not be greater than 11 characters.',
            ],
            'phone contains non-digits' => ['phone', '0917-000-000', 'The phone field format is invalid.'],
        ];
    }

    #[DataProvider('invalidRegistrationPasswords')]
    public function test_registration_rejects_passwords_shorter_than_eight_or_without_a_special_character(
        string $password,
        string $errorMessage,
    ): void {
        $this->post(route('register.store'), [
            ...$this->registrationData(),
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors(['password' => $errorMessage]);

        $this->assertDatabaseMissing('users', ['email' => 'patient@example.test']);
    }

    public static function invalidRegistrationPasswords(): array
    {
        return [
            'short password' => ['Abc1!xy', 'The password field must be at least 8 characters.'],
            'no special character' => ['Abcdef12', 'The password field must contain at least one symbol.'],
            'no capital letter' => ['abcdef1!', 'The password field format is invalid.'],
            'space is not a special character' => ['Abcdef 1', 'The password field format is invalid.'],
        ];
    }

    public function test_registration_accepts_an_eight_character_password_with_a_capital_letter_and_special_character(): void
    {
        Mail::fake();

        $this->post(route('register.store'), [
            ...$this->registrationData(),
            'password' => 'Abcdef1!',
            'password_confirmation' => 'Abcdef1!',
        ])->assertRedirectToRoute('verification.notice');

        $this->assertDatabaseHas('users', ['email' => 'patient@example.test']);
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
            'password' => 'Secure-password-123!',
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
            'password' => 'Secure-password-123!',
            'role' => 'patient',
        ]);
        Patient::factory()->create(['user_id' => $user->id]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Secure-password-123!',
        ])->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Verify Your Email');
    }

    public function test_admin_and_staff_can_still_log_in_without_patient_email_verification(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->unverified()->create([
                'email' => $role.'@example.test',
                'password' => 'Secure-password-123!',
                'role' => $role,
            ]);

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'Secure-password-123!',
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
            'branch_id' => Branch::query()->where('slug', 'general-trias')->value('id'),
            'phone' => '09170000000',
            'email' => 'patient@example.test',
            'address' => 'Clinic Street',
            'password' => 'Secure-password-123!',
            'password_confirmation' => 'Secure-password-123!',
        ];
    }

    private function sentCode(int $messageIndex = 0): string
    {
        $messages = Mail::sent(VerifyPatientEmail::class);
        $message = $messages->values()->get($messageIndex);

        return $message->verificationCode;
    }
}
