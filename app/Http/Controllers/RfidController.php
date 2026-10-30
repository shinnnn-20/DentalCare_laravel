<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\RfidCard;
use App\Models\RfidLog;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $wasAssigned = $card !== null;
        $data = $request->validate([
            'uid' => ['required', 'string', 'max:100', Rule::unique('rfid_cards', 'uid')->ignore($card?->id)],
            'valid_from' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'valid_until' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d\TH:i',
                Rule::when($request->filled('valid_from'), ['after:valid_from']),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $cardAttributes = [
            'uid' => $data['uid'],
            'is_active' => isset($data['is_active'])
                ? (bool) $data['is_active']
                : ($card?->is_active ?? true),
        ];

        foreach (['valid_from', 'valid_until'] as $validityField) {
            if (array_key_exists($validityField, $data)) {
                $cardAttributes[$validityField] = $data[$validityField] === null
                    ? null
                    : Carbon::createFromFormat(
                        '!Y-m-d\TH:i',
                        $data[$validityField],
                        config('clinic.timezone'),
                    );
            }
        }

        DB::transaction(function () use ($request, $patient, $cardAttributes, $wasAssigned, $auditTrail): void {
            $card = RfidCard::updateOrCreate(
                ['patient_id' => $patient->id],
                $cardAttributes,
            );
            $auditTrail->record(
                $request->user(),
                $wasAssigned ? 'rfid_card_updated' : 'rfid_assigned',
                'rfid',
                $card,
                $wasAssigned
                    ? 'Status: '.$card->validityStatus().'; valid from: '
                        .($card->valid_from?->format('Y-m-d H:i') ?? 'unrestricted')
                        .'; valid until: '.($card->valid_until?->format('Y-m-d H:i') ?? 'no expiry')
                    : null,
            );
        });

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
        $card = RfidCard::query()->with(['patient.user'])->where('uid', $uid)->first();
        $now = now(config('clinic.timezone'));
        $cardStatus = $card?->validityStatus($now) ?? 'not_registered';
        $patient = $cardStatus === 'active' ? $card?->patient : null;

        RfidLog::create([
            'rfid_card_id' => $card?->id,
            'patient_id' => $card?->patient_id,
            'scanned_by' => $request->user()->id,
            'uid' => $uid,
            'result' => $cardStatus === 'not_registered' ? 'unknown' : ($cardStatus === 'active' ? 'identified' : $cardStatus),
            'scanned_at' => $now,
        ]);

        $dentalRecords = $patient?->dentalRecords()->latest()->get() ?? collect();
        $appointments = $patient?->appointments()
            ->with(['branch', 'service', 'services'])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [$now])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [$now])
            ->get() ?? collect();

        return view('rfid.scan', [
            'uid' => $uid,
            'cardStatus' => $cardStatus,
            'scanMessage' => match ($cardStatus) {
                'active' => null,
                'not_yet_valid' => 'This RFID card is not yet valid.',
                'expired' => 'This RFID card has expired.',
                'disabled' => 'This RFID card has been disabled.',
                default => 'No registered RFID card matches this UID.',
            },
            'patient' => $patient,
            'dentalRecords' => $dentalRecords,
            'appointments' => $appointments,
        ]);
    }
}
