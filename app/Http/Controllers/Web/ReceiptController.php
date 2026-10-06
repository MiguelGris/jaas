<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditService;
use App\Services\PaymentCancellationService;
use App\Services\PaymentConceptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ReceiptController extends Controller
{
    public function thermal(Payment $payment): View
    {
        $payment->load([
            'customer',
            'paymentMethod',
            'user',
            'voidedBy',
            'invoice.connection.property.customer',
            'allocations.invoice',
            'allocations.fine.assembly',
        ]);

        $customer = $payment->customer ?? $payment->invoice?->connection?->property?->customer;

        $paymentConcepts = app(PaymentConceptService::class)->forPayment($payment);

        return view('receipts.thermal', compact('payment', 'customer', 'paymentConcepts'));
    }

    public function annulForm(Payment $payment): View|RedirectResponse
    {
        if ($payment->status === Payment::STATUS_VOIDED) {
            return redirect()
                ->route('resources.show', ['resource' => 'payments', 'record' => $payment->getKey()])
                ->with('error', 'Este pago ya fue anulado.');
        }

        $payment->load(['customer', 'paymentMethod', 'user', 'allocations.invoice', 'allocations.fine']);

        return view('receipts.annul', compact('payment'));
    }

    public function annul(
        Request $request,
        Payment $payment,
        PaymentCancellationService $cancellations,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:3', 'max:250'],
        ]);
        $before = $audit->snapshot($payment);
        $payment = $cancellations->cancel($payment, $request->user(), $data['void_reason']);
        $audit->updated($request->user(), $payment, $before);

        return redirect()
            ->route('resources.show', ['resource' => 'payments', 'record' => $payment->getKey()])
            ->with('success', "Pago {$payment->receipt_code} anulado. Las deudas asociadas volvieron a quedar pendientes.");
    }
}
