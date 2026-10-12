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
    public function create(Request $request, DebtService $debts): View
    {
        $customerId = $request->input('customer', old('customer_id'));
        $customer = $customerId ? Customer::query()->find($customerId) : null;

        $customers = Customer::query()
            ->orderBy('customer_type')
            ->orderBy('business_name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'customer_code', 'customer_type', 'national_id', 'first_name', 'last_name', 'business_name']);

        $charges = $customer === null
            ? ['invoices' => collect(), 'fines' => collect(), 'total' => 0]
            : $debts->pendingForCustomer($customer);

        return view('collections.create', [
            'customers' => $customers,
            'customer' => $customer,
            'invoices' => $charges['invoices']->filter(fn ($invoice) => $invoice->service_balance > 0),
            'lateFees' => $charges['invoices']->filter(fn ($invoice) => $invoice->late_fee_balance > 0),
            'serviceWaiverHistory' => \App\Models\ServiceWaiver::query()->with(['invoice.connection.property.customer', 'user'])->when($customer,fn($q)=>$q->whereHas('invoice.connection.property',fn($q)=>$q->where('customer_id',$customer->id)))->when(! $customer,fn($q)=>$q->whereRaw('1=0'))->latest()->limit(20)->get(),
            'waiverHistory' => \App\Models\LateFeeWaiver::query()->with(['invoice.billingPeriod', 'user'])->when($customer, fn ($q) => $q->whereHas('invoice.connection.property', fn ($q) => $q->where('customer_id', $customer->id)))->when(! $customer, fn ($q) => $q->whereRaw('1 = 0'))->latest()->limit(20)->get(),
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
            'late_fees' => ['nullable', 'array'],
            'late_fees.*' => ['integer', 'distinct', 'exists:invoices,id'],
            'separate_late_fees' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:250'],
            'external_reference' => ['nullable', 'string', 'max:100'],
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
    public function waive(Request $request, \App\Services\LateFeeWaiverService $waivers): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'waiver_invoices' => ['required', 'array', 'min:1'],
            'waiver_invoices.*' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
            'waiver_mode' => ['required', 'in:all,percentage,amount'],
            'waiver_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'waiver_reason' => ['required', 'string', 'max:250'],
        ]);
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $amount = $waivers->waive($customer, $data['waiver_invoices'], $data['waiver_mode'], (float) ($data['waiver_value'] ?? 0), $data['waiver_reason'], $request->user());
        return redirect()->route('collections.create', ['customer' => $customer->id])
            ->with('success', 'Mora condonada: S/ '.number_format($amount, 2).'. La condonación quedó registrada y no es un ingreso de caja.');
    }
    public function waiveService(Request $request, \App\Services\ServiceWaiverService $service): RedirectResponse {
        $data=$request->validate(['customer_id'=>['required','integer','exists:customers,id'],'service_invoices'=>['required','array','min:1'],'service_invoices.*'=>['integer','distinct','exists:invoices,id'],'service_mode'=>['required','in:all,percentage,amount'],'service_value'=>['nullable','numeric','min:0','max:99999999.99'],'service_reason'=>['required','string','max:250']]);
        $customer=Customer::query()->findOrFail($data['customer_id']);
        $waivers=$service->waive($customer,$data['service_invoices'],$data['service_mode'],(float)($data['service_value']??0),$data['service_reason'],$request->user());
        return redirect()->route('collections.create',['customer'=>$customer->id])->with('success','Servicio condonado: S/ '.number_format(collect($waivers)->sum('amount'),2).'. Los recibos mensuales están disponibles en el historial de condonaciones.');
    }

}
