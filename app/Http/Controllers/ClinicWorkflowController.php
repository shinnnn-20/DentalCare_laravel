<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\DentalRecord;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\SmsLog;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClinicWorkflowController extends Controller
{
    public function queue(): View
    {
        return view('queue.index', [
            'queueEntries' => QueueEntry::query()
                ->with(['appointment.patient.user', 'appointment.service'])
                ->whereDate('queue_date', today())
                ->whereIn('status', ['waiting', 'called', 'in_consultation'])
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function createRecord(Appointment $appointment): View
    {
        abort_unless(in_array($appointment->status, ['in_consultation', 'completed'], true), 422);

        return view('records.create', [
            'appointment' => $appointment->load(['patient.user', 'service']),
        ]);
    }

    public function storeRecord(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        abort_unless(in_array($appointment->status, ['in_consultation', 'completed'], true), 422);

        $data = $request->validate([
            'diagnosis' => ['required', 'string', 'max:5000'],
            'treatment' => ['required', 'string', 'max:5000'],
            'prescription' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if ($appointment->dentalRecord()->exists()) {
            throw ValidationException::withMessages(['appointment' => 'A dental record already exists for this appointment.']);
        }

        $record = DentalRecord::create([
            ...$data,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'created_by' => $request->user()->id,
        ]);
        $auditTrail->record($request->user(), 'dental_record_created', 'dental_records', $record);
        $notifications->notifyClinicStaff(
            $request->user(),
            'patient.dental_record_created',
            'Dental Record Created',
            "{$request->user()->name} created a dental record for {$appointment->patient->user->name}.",
            route('clinic.patients.show', $appointment->patient_id, false),
            'dental_record',
            $record->id,
        );

        return redirect()->route('clinic.patients.show', $appointment->patient_id)->with('status', 'Dental record saved.');
    }

    public function createBill(Appointment $appointment): View
    {
        abort_unless($appointment->status === 'in_consultation', 422);
        abort_if($appointment->dentalRecord()->doesntExist(), 422, 'A dental record is required before billing.');
        abort_if($appointment->bill()->exists(), 409, 'A bill already exists for this appointment.');

        return view('billing.create', [
            'appointment' => $appointment->load(['patient.user', 'service']),
        ]);
    }

    public function storeBill(
        Request $request,
        Appointment $appointment,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        abort_unless($appointment->status === 'in_consultation', 422);
        abort_if($appointment->dentalRecord()->doesntExist(), 422, 'A dental record is required before billing.');
        abort_if($appointment->bill()->exists(), 409, 'A bill already exists for this appointment.');

        $data = $request->validate([
            'discount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
        ]);
        $subtotalCents = (int) round((float) $appointment->service->price * 100);
        $discountCents = (int) round((float) $data['discount'] * 100);

        if ($discountCents > $subtotalCents) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the subtotal.']);
        }

        $bill = DB::transaction(function () use ($appointment, $request, $subtotalCents, $discountCents): Bill {
            $totalCents = $subtotalCents - $discountCents;
            $bill = Bill::create([
                'bill_number' => 'B-PENDING-'.Str::uuid(),
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'created_by' => $request->user()->id,
                'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                'discount' => number_format($discountCents / 100, 2, '.', ''),
                'total' => number_format($totalCents / 100, 2, '.', ''),
                'payment_status' => $totalCents === 0 ? 'paid' : 'unpaid',
            ]);
            $bill->update(['bill_number' => sprintf('B-%s-%06d', now()->format('Y'), $bill->id)]);
            $bill->items()->create([
                'service_id' => $appointment->service_id,
                'service_name' => $appointment->service->name,
                'quantity' => 1,
                'unit_price' => number_format($subtotalCents / 100, 2, '.', ''),
                'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
            ]);
            $appointment->update(['status' => 'billed']);

            return $bill;
        });

        $auditTrail->record($request->user(), 'bill_created', 'billing', $bill);
        $notifications->notifyClinicStaff(
            $request->user(),
            'billing.bill_created',
            'Bill Created',
            "{$request->user()->name} created bill {$bill->bill_number} for {$appointment->patient->user->name}.",
            route('clinic.billing.index', ['bill' => $bill->id], false).'#bill-'.$bill->id,
            'bill',
            $bill->id,
        );

        return redirect()->route('clinic.billing.index')->with('status', 'Bill created.');
    }

    public function billing(Request $request): View
    {
        $filters = $request->validate([
            'bill' => ['nullable', 'integer', 'exists:bills,id'],
        ]);

        return view('billing.index', [
            'bills' => Bill::query()
                ->with(['patient.user', 'appointment', 'items', 'payments.receipt'])
                ->when(
                    $filters['bill'] ?? null,
                    fn ($query, $billId) => $query->orderByRaw('CASE WHEN bills.id = ? THEN 0 ELSE 1 END', [$billId]),
                )
                ->latest()
                ->paginate(20),
            'selectedBillId' => isset($filters['bill']) ? (int) $filters['bill'] : null,
        ]);
    }

    public function records(Request $request): View
    {
        $patient = $request->user()->patient()->firstOrFail();

        return view('records.index', [
            'records' => $patient->dentalRecords()->with('creator')->latest()->paginate(20),
        ]);
    }

    public function patientBilling(Request $request): View
    {
        $patient = $request->user()->patient()->firstOrFail();

        return view('billing.patient', [
            'bills' => Bill::query()
                ->with(['appointment', 'items', 'payments.receipt'])
                ->whereBelongsTo($patient)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function patientNotifications(Request $request): View
    {
        $patient = $request->user()->patient()->firstOrFail();

        return view('logs.patient-notifications', [
            'logs' => SmsLog::query()->whereBelongsTo($patient)->latest()->paginate(20),
        ]);
    }

    public function reports(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = $filters['from'] ?? today()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'appointments' => Appointment::whereBetween('starts_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'walkIns' => Appointment::where('type', 'walk_in')->whereBetween('starts_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'completed' => Appointment::where('status', 'completed')->whereBetween('starts_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->whereBetween('starts_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'revenue' => DB::table('payments')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('applied_amount'),
            'newPatients' => Patient::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
        ]);
    }

    public function auditLogs(): View
    {
        return view('logs.audit', [
            'logs' => AuditLog::with('user')->latest()->paginate(40),
        ]);
    }

    public function smsLogs(): View
    {
        return view('logs.sms', [
            'logs' => SmsLog::with('patient.user')->latest()->paginate(40),
        ]);
    }
}
