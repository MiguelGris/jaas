<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\DebtService;
use App\Services\AuditService;
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
        $search = trim((string) $request->query('q', ''));
        $customer = $request->filled('customer')
            ? Customer::query()->find($request->integer('customer'))
            : null;

        $customers = $search === ''
            ? collect()
            : Customer::query()
                ->where(function ($query) use ($search): void {
                    $query->where('national_id', $search)
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(12)
                ->get();

        $charges = $customer === null
            ? ['invoices' => collect(), 'fines' => collect(), 'total' => 0]
            : $debts->pendingForCustomer($customer);

        return view('collections.create', [
            'search' => $search,
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
