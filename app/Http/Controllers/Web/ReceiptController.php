<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\View\View;

final class ReceiptController extends Controller
{
    public function thermal(Payment $payment): View
    {
        $payment->load([
            'customer',
            'paymentMethod',
            'user',
            'invoice.connection.property.customer',
            'allocations.invoice',
            'allocations.fine.assembly',
        ]);

        $customer = $payment->customer ?? $payment->invoice?->connection?->property?->customer;

        return view('receipts.thermal', compact('payment', 'customer'));
    }
}
