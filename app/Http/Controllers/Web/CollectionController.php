<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\AuditService;
use App\Services\DebtService;
use App\Services\PaymentCollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CollectionController extends Controller
{
    public function __construct()
    {
        view()->share('navigation', JassPageController::navigation());
    }

    public function create(Request $request, DebtService $debts): View
    {
        $customer = $request->filled('customer')
            ? Customer::query()->find($request->integer('customer'))
            : null;

        $customers = Customer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'customer_code', 'national_id', 'first_name', 'last_name']);

        $charges = $customer === null
            ? ['invoices' => collect(), 'fines' => collect(), 'total' => 0]
            : $debts->pendingForCustomer($customer);

        return view('collections.create', [
            'customers' => $customers,
            'customer' => $customer,
            'invoices' => $charges['invoices'],
            'fines' => $charges['fines'],
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PaymentCollectionService $collections, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoices' => ['nullable', 'array'],
            'invoices.*' => ['integer', 'distinct', 'exists:invoices,id'],
            'fines' => ['nullable', 'array'],
            'fines.*' => ['integer', 'distinct', 'exists:fines,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $payment = $collections->collect(
            $customer,
            $data['invoices'] ?? [],
            $data['fines'] ?? [],
            $data,
            $request->user(),
        );
        $audit->created($request->user(), $payment);

        return redirect()
            ->route('collections.create', ['customer' => $customer->getKey()])
            ->with('success', "Pago registrado. Recibo {$payment->receipt_code} emitido por S/ ".number_format((float) $payment->amount, 2).'.')
            ->with('receipt_id', $payment->getKey());
    }
}
