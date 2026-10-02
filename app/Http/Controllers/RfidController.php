<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\RfidCard;
use App\Models\RfidLog;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidController extends Controller
{
    public function index(): View
    {
        return view('rfid.index', [
            'patients' => Patient::with(['user', 'rfidCard'])->orderBy('patient_number')->paginate(20),
        ]);
    }

    public function assign(Request $request, Patient $patient, AuditTrail $auditTrail): RedirectResponse
    {
        $request->merge([
            'uid' => mb_strtoupper(trim((string) $request->input('uid'))),
        ]);
        $card = $patient->rfidCard;
        $data = $request->validate([
            'uid' => ['required', 'string', 'max:100', Rule::unique('rfid_cards', 'uid')->ignore($card?->id)],
        ]);

        $card = RfidCard::updateOrCreate(
            ['patient_id' => $patient->id],
            ['uid' => $data['uid'], 'is_active' => true],
        );
        $auditTrail->record($request->user(), 'rfid_assigned', 'rfid', $card);

        return back()->with('status', 'RFID card assigned.');
    }

    public function scanPage(): View
    {
        return view('rfid.scan');
    }

    public function scan(Request $request): View
    {
        $data = $request->validate(['uid' => ['required', 'string', 'max:100']]);
        $uid = mb_strtoupper(trim($data['uid']));
        $card = RfidCard::query()->with(['patient.user'])->where('uid', $uid)->where('is_active', true)->first();

        RfidLog::create([
            'rfid_card_id' => $card?->id,
            'patient_id' => $card?->patient_id,
            'scanned_by' => $request->user()->id,
            'uid' => $uid,
            'result' => $card === null ? 'unknown' : 'identified',
            'scanned_at' => now(),
        ]);

        return view('rfid.scan', [
            'uid' => $uid,
            'patient' => $card?->patient,
            'appointments' => $card?->patient?->appointments()
                ->with('service')
                ->whereDate('starts_at', today())
                ->whereIn('status', ['approved', 'checked_in', 'waiting'])
                ->orderBy('starts_at')
                ->get() ?? collect(),
        ]);
    }

    public function checkIn(Request $request, Appointment $appointment, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate(['uid' => ['required', 'string', 'max:100']]);
        $card = RfidCard::query()
            ->where('uid', mb_strtoupper(trim($data['uid'])))
            ->where('is_active', true)
            ->first();

        abort_unless($card !== null && $card->patient_id === $appointment->patient_id, 404);
        abort_unless($appointment->starts_at->isToday() && $appointment->status === 'approved', 422);

        DB::transaction(function () use ($appointment): void {
            $appointment->update(['status' => 'checked_in']);
            if (! $appointment->queueEntry()->exists()) {
                $queue = QueueEntry::create([
                    'appointment_id' => $appointment->id,
                    'queue_date' => today(),
                    'queue_number' => 'Q-PENDING-'.Str::uuid(),
                    'status' => 'waiting',
                ]);
                $queue->update(['queue_number' => sprintf('Q-%03d', $queue->id)]);
            }
        });

        RfidLog::create([
            'rfid_card_id' => $card->id,
            'patient_id' => $card->patient_id,
            'scanned_by' => $request->user()->id,
            'uid' => $card->uid,
            'result' => 'checked_in',
            'scanned_at' => now(),
        ]);
        $auditTrail->record($request->user(), 'rfid_check_in', 'rfid', $appointment);

        return redirect()->route('queue.index')->with('status', 'Patient checked in and added to the queue.');
    }
}
