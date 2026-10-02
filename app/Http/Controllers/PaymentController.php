<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\AuditTrail;
use App\Services\SmsNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function store(Request $request, Bill $bill, AuditTrail $auditTrail, SmsNotifier $smsNotifier): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'in:cash,gcash,card,other'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
            'applied_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ]);

        $payment = DB::transaction(function () use ($bill, $data, $request): Payment {
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
                'receipt_number' => 'OR-PENDING-'.Str::uuid(),
            ]);
            $receipt->update(['receipt_number' => sprintf('OR-%s-%06d', now()->format('Y'), $receipt->id)]);

            $newPaid = $paid + $applied;
            $lockedBill->update([
                'payment_status' => $newPaid >= $total ? 'paid' : 'partially_paid',
            ]);

            return $payment;
        });

        $payment->load(['bill.patient.user', 'receipt']);
        $auditTrail->record($request->user(), 'payment_processed', 'payments', $payment);
        $smsNotifier->send(
            $payment->bill->patient,
            'payment',
            'Payment received for bill '.$payment->bill->bill_number.'. Receipt '.$payment->receipt->receipt_number.'.',
        );

        return redirect()->route('receipts.show', $payment->receipt)->with('status', 'Payment recorded and receipt generated.');
    }

    public function showReceipt(Request $request, Receipt $receipt): View
    {
        $receipt->load(['payment.processor', 'payment.bill.patient.user', 'payment.bill.items', 'payment.bill.appointment']);

        if ($request->user()->role === 'patient' && $receipt->payment->bill->patient->user_id !== $request->user()->id) {
            abort(404);
        }

        return view('receipts.show', compact('receipt'));
    }
}
