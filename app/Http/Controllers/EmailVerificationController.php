<?php

namespace App\Http\Controllers;

use App\Enums\EmailVerificationResult;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class EmailVerificationController extends Controller
{
    public function notice(Request $request, EmailVerificationService $emailVerification): View|RedirectResponse
    {
        $user = $this->pendingPatient($request);

        if ($user === null) {
            return redirect()->route('login')
                ->with('status', 'Please register for a patient account to verify your email.');
        }

        if ($user->email_verified_at !== null) {
            $request->session()->forget('verification_user_id');

            return redirect()->route('login')
                ->with('status', 'Your email has already been verified. You can now log in.');
        }

        return view('auth.verify-email', [
            'user' => $user,
            'resendCooldown' => $emailVerification->resendCooldownRemaining($user),
        ]);
    }

    public function verify(Request $request, EmailVerificationService $emailVerification): View|RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'regex:/^\d{6}$/'],
        ]);
        $user = $this->pendingPatient($request);

        if ($user === null) {
            return redirect()->route('login')
                ->with('status', 'Your registration verification session has expired. Please log in or register again.');
        }

        $result = $emailVerification->verify($user, $data['code']);

        if ($result === EmailVerificationResult::Verified || $result === EmailVerificationResult::AlreadyVerified) {
            $request->session()->forget('verification_user_id');

            return view('auth.verify-result', ['result' => $result]);
        }

        $message = $result === EmailVerificationResult::Expired
            ? 'This verification code has expired. Please request a new code.'
            : 'Invalid verification code. Please try again.';

        return redirect()->route('verification.notice')->withErrors(['code' => $message]);
    }

    public function resend(Request $request, EmailVerificationService $emailVerification): RedirectResponse
    {
        $user = $this->pendingPatient($request);

        if ($user === null) {
            return redirect()->route('login')
                ->with('status', 'Your registration verification session has expired. Please register again.');
        }

        if ($user->email_verified_at !== null) {
            $request->session()->forget('verification_user_id');

            return redirect()->route('login')
                ->with('status', 'Your email has already been verified. You can now log in.');
        }

        try {
            $sent = $emailVerification->send($user);
        } catch (Throwable $exception) {
            Log::error('Patient email verification message could not be resent.', [
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return back()->withErrors(['email' => 'Unable to send the verification code. Please try again later.']);
        }

        if (! $sent) {
            return back()
                ->with('status', 'Please wait before requesting another verification code.');
        }

        return back()->with('status', 'A new verification code was sent to your email address.');
    }

    private function pendingPatient(Request $request): ?User
    {
        $userId = $request->session()->get('verification_user_id');

        if (! is_int($userId) && ! ctype_digit((string) $userId)) {
            return null;
        }

        return User::query()
            ->whereKey((int) $userId)
            ->where('role', 'patient')
            ->first();
    }
}
