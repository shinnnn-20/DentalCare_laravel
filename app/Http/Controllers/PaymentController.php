<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\AppointmentCompletionService;
use App\Services\AuditTrail;
use App\Services\ClinicNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function store(
        Request $request,
        Bill $bill,
        AppointmentCompletionService $appointmentCompletion,
        AuditTrail $auditTrail,
        ClinicNotificationService $notifications,
    ): RedirectResponse {
        $this->authorizeBillBranch($request, $bill);

        foreach (['amount', 'applied_amount'] as $field) {
            $value = $request->input($field);

            if (is_string($value)
                && preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $value) === 1) {
                $request->merge([$field => str_replace(',', '', $value)]);
            }
        }

        $data = $request->validate([
            'method' => ['required', 'in:cash,gcash,other'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
            'applied_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ]);

        $payment = DB::transaction(function () use ($bill, $data, $request, $appointmentCompletion): Payment {
            $lockedBill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $paid = (int) round((float) $lockedBill->payments()->sum('applied_amount') * 100);
            $total = (int) round((float) $lockedBill->total * 100);
            $remaining = $total - $paid;
            $applied = (int) round((float) $data['applied_amount'] * 100);
            $received = (int) round((float) $data['amount'] * 100);

            if ($applied > $remaining) {
                throw ValidationException::withMessages(['applied_amount' => 'The applied amount cannot exceed the remaining balance.']);
            }

            if ($received < $applied || ($data['method'] !== 'cash' && $received !== $applied)) {
                throw ValidationException::withMessages(['amount' => 'Amount received must equal the applied amount, except for cash where it may be higher.']);
            }

            $payment = Payment::create([
                'bill_id' => $lockedBill->id,
                'transaction_id' => 'TXN-PENDING-'.Str::uuid(),
                'amount' => number_format($received / 100, 2, '.', ''),
                'applied_amount' => number_format($applied / 100, 2, '.', ''),
                'change_amount' => number_format(($received - $applied) / 100, 2, '.', ''),
                'method' => $data['method'],
                'processed_by' => $request->user()->id,
            ]);
            $payment->update(['transaction_id' => sprintf('TXN-%s-%06d', now()->format('Ymd'), $payment->id)]);
            $receipt = Receipt::create([
                'payment_id' => $payment->id,
                'receipt_number' => 'AR-PENDING-'.Str::uuid(),
            ]);
            $receipt->update(['receipt_number' => sprintf('AR-%s-%06d', now()->format('Y'), $receipt->id)]);

            $newPaid = $paid + $applied;
            $lockedBill->update([
                'payment_status' => $newPaid >= $total ? 'paid' : 'partially_paid',
            ]);
            $appointmentCompletion->sync($lockedBill);

            return $payment;
        });

        $payment->load(['bill.patient.user', 'receipt']);
        $auditTrail->record($request->user(), 'payment_processed', 'payments', $payment);
        $notifications->notifyClinicStaff(
            $request->user(),
            'billing.payment_recorded',
            'Payment Recorded',
            "{$request->user()->name} recorded a payment of ₱".number_format((float) $payment->applied_amount, 2)
                ." for {$payment->bill->patient->user->name}.",
            route('clinic.billing.index', ['bill' => $payment->bill_id], false).'#bill-'.$payment->bill_id,
            'bill',
            $payment->bill_id,
        );

        return redirect()->route('receipts.show', $payment->receipt)->with('status', 'Payment recorded and receipt generated.');
    }

    public function showReceipt(Request $request, Receipt $receipt): View
    {
        abort_if($request->user()->role === 'patient', 404);
        $this->authorizeBillBranch($request, $receipt->payment->bill);

        return $this->receiptView($receipt, false);
    }

    public function showPatientReceiptPreview(Request $request, Bill $bill, Receipt $receipt): View
    {
        if ($bill->patient->user_id !== $request->user()->id || $receipt->payment->bill_id !== $bill->id) {
            abort(404);
        }

        return $this->receiptView($receipt, true);
    }

    private function receiptView(Receipt $receipt, bool $isPreview): View
    {
        $receipt->load([
            'payment.processor',
            'payment.bill.patient.user',
            'payment.bill.items',
            'payment.bill.payments',
            'payment.bill.appointment',
        ]);

        return view('receipts.show', compact('receipt', 'isPreview'));
    }

    private function authorizeBillBranch(Request $request, Bill $bill): void
    {
        $user = $request->user();

        if ($user->hasRole('admin') || $user->branch_id === null) {
            return;
        }

        abort_unless(
            $bill->appointment()->where('branch_id', $user->branch_id)->exists(),
            404,
        );
    }
}
