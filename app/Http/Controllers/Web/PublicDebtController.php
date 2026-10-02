<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\DebtService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicDebtController extends Controller
{
    public function home(): View
    {
        return view('public.home');
    }

    public function lookup(Request $request, DebtService $debts): View
    {
        $data = $request->validate([
            'national_id' => ['required', 'regex:/^(?:\d{8}|\d{11})$/'],
        ], [
            'national_id.required' => 'Ingresa tu DNI o RUC.',
            'national_id.regex' => 'El documento debe contener 8 dígitos para DNI o 11 dígitos para RUC.',
        ]);

        $customer = Customer::query()
            ->where('national_id', $data['national_id'])
            ->first();

        $charges = $customer === null
            ? ['invoices' => collect(), 'fines' => collect(), 'total' => 0]
            : $debts->pendingForCustomer($customer);

        return view('public.debt', [
            'customer' => $customer,
            'invoices' => $charges['invoices'],
            'fines' => $charges['fines'],
            'totalDue' => $charges['total'],
            'nationalId' => $data['national_id'],
            'notFound' => $customer === null,
        ]);
    }
}
