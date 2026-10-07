<?php

namespace App\Services;

use App\Enums\EmailVerificationResult;
use App\Mail\VerifyPatientEmail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailVerificationService
{
    private const CODE_LIFETIME_MINUTES = 15;

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function send(User $user, bool $enforceCooldown = true): bool
    {
        if ($user->email_verified_at !== null) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = hash_hmac('sha256', $code, (string) config('app.key'));
        $issued = DB::transaction(function () use ($user, $codeHash, $enforceCooldown): bool {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->email_verified_at !== null) {
                return false;
            }

            $existingToken = DB::table('email_verification_tokens')
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->first();

            if (
                $enforceCooldown
                && $existingToken !== null
                && Carbon::parse($existingToken->created_at)
                    ->greaterThan(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
            ) {
                return false;
            }

            DB::table('email_verification_tokens')->updateOrInsert(
                ['user_id' => $lockedUser->id],
                [
                    'token_hash' => $codeHash,
                    'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
                    'created_at' => now(),
                ],
            );

            return true;
        });

        if (! $issued) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new VerifyPatientEmail($user, $code));
        } catch (Throwable $exception) {
            DB::table('email_verification_tokens')
                ->where('user_id', $user->id)
                ->where('token_hash', $codeHash)
                ->delete();

            throw $exception;
        }

        return true;
    }

    public function resendCooldownRemaining(User $user): int
    {
        $createdAt = DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->value('created_at');

        if ($createdAt === null) {
            return 0;
        }

        $availableAt = Carbon::parse($createdAt)->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return max(0, $availableAt->timestamp - now()->timestamp);
    }

    public function verify(User $user, string $code): EmailVerificationResult
    {
        $codeHash = hash_hmac('sha256', $code, (string) config('app.key'));

        return DB::transaction(function () use ($user, $codeHash): EmailVerificationResult {
            $verificationToken = DB::table('email_verification_tokens')
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($verificationToken === null) {
                return EmailVerificationResult::Invalid;
            }

            if (now()->greaterThanOrEqualTo($verificationToken->expires_at)) {
                DB::table('email_verification_tokens')
                    ->where('user_id', $verificationToken->user_id)
                    ->delete();

                return EmailVerificationResult::Expired;
            }

            $user = User::query()->whereKey($verificationToken->user_id)->lockForUpdate()->first();
            if ($user === null) {
                DB::table('email_verification_tokens')
                    ->where('user_id', $verificationToken->user_id)
                    ->delete();

                return EmailVerificationResult::Invalid;
            }

            if ($user->email_verified_at !== null) {
                DB::table('email_verification_tokens')
                    ->where('user_id', $verificationToken->user_id)
                    ->delete();

                return EmailVerificationResult::AlreadyVerified;
            }

            if (! hash_equals((string) $verificationToken->token_hash, $codeHash)) {
                return EmailVerificationResult::Invalid;
            }

            DB::table('email_verification_tokens')
                ->where('user_id', $verificationToken->user_id)
                ->delete();
            $user->forceFill(['email_verified_at' => now()])->save();

            return EmailVerificationResult::Verified;
        });
    }
}
