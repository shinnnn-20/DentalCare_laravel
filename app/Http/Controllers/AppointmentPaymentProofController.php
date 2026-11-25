<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AppointmentPaymentProofController extends Controller
{
    public function showProof(Request $request, Appointment $appointment): BinaryFileResponse
    {
        $user = $request->user();

        if ($user->role === 'patient') {
            abort_unless($appointment->patient()->where('user_id', $user->id)->exists(), 404);
        } else {
            abort_unless($user->hasRole('admin', 'staff'), 403);
            $this->authorizeBranch($user, $appointment);
        }

        $path = $appointment->payment_proof_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reviewProof(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor->hasRole('admin', 'staff'), 403);
        $this->authorizeBranch($actor, $appointment);

        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
        ]);

        DB::transaction(function () use ($appointment, $actor, $data): void {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedAppointment->payment_method !== 'gcash'
                || $lockedAppointment->payment_proof_path === null
                || $lockedAppointment->payment_proof_status !== 'pending_review'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Only GCash receipts awaiting review can be verified or rejected.',
                ]);
            }

            $lockedAppointment->update([
                'payment_proof_status' => $data['status'],
                'payment_proof_reviewed_by' => $actor->id,
                'payment_proof_reviewed_at' => now(),
            ]);

        });

        $appointment->refresh();

        $reviewAction = $appointment->payment_proof_status === 'verified' ? 'verified' : 'rejected';
        $auditTrail->record(
            $actor,
            'appointment_payment_proof_'.$reviewAction,
            'appointments',
            $appointment,
            sprintf(
                '%s %s the GCash receipt for appointment #%d.',
                $actor->name,
                $reviewAction,
                $appointment->id,
            ),
        );

        return redirect()->route('clinic.appointment-records', [
            'branch_id' => $appointment->branch_id ?? 'unassigned',
            'date' => $appointment->starts_at->toDateString(),
            'appointment_id' => $appointment->id,
        ])->with('status', 'GCash receipt proof '.$reviewAction.'.');
    }

    public function resubmitProof(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(
            $user->role === 'patient'
            && $appointment->patient()->where('user_id', $user->id)->exists(),
            404,
        );

        $data = $request->validate([
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        DB::transaction(function () use ($appointment): void {
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureProofCanBeResubmitted($lockedAppointment);
        });

        $newPath = $data['payment_proof']->store('appointment-payment-proofs', 'local');
        if (! is_string($newPath)) {
            throw new RuntimeException('The GCash receipt image could not be stored.');
        }

        try {
            $oldPath = DB::transaction(function () use ($appointment, $newPath): ?string {
                $lockedAppointment = Appointment::query()
                    ->whereKey($appointment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProofCanBeResubmitted($lockedAppointment);
                $previousPath = $lockedAppointment->payment_proof_path;
                $lockedAppointment->update([
                    'payment_proof_path' => $newPath,
                    'payment_proof_status' => 'pending_review',
                    'payment_proof_reviewed_by' => null,
                    'payment_proof_reviewed_at' => null,
                ]);

                return $previousPath;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $appointment->refresh();
        $auditTrail->record(
            $user,
            'appointment_payment_proof_resubmitted',
            'appointments',
            $appointment,
            sprintf('Patient %s resubmitted GCash receipt proof for appointment #%d.', $user->name, $appointment->id),
        );

        return redirect()->route('appointments.index')->with('status', 'Replacement GCash receipt submitted for staff review.');
    }

    private function authorizeBranch(User $user, Appointment $appointment): void
    {
        if ($user->role === 'staff' && $user->branch_id !== null && (int) $appointment->branch_id !== (int) $user->branch_id) {
            abort(404);
        }
    }

    private function ensureProofCanBeResubmitted(Appointment $appointment): void
    {
        if (
            $appointment->payment_method !== 'gcash'
            || $appointment->payment_proof_status !== 'rejected'
            || ! in_array($appointment->status, ['pending', 'approved'], true)
        ) {
            throw ValidationException::withMessages([
                'payment_proof' => 'A replacement receipt can only be uploaded after a GCash proof is rejected for an active appointment.',
            ]);
        }
    }
}
