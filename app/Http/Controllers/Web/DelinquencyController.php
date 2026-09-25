<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DebtService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class DelinquencyController extends Controller
{
    public function __construct()
    {
        view()->share('navigation', JassPageController::navigation());
    }

    public function index(Request $request, DebtService $debts): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $debtors = $debts->debtors();

        if ($search !== '') {
            $needle = Str::lower($search);
            $debtors = $debtors
                ->filter(function (array $debtor) use ($needle): bool {
                    $customer = $debtor['customer'];
                    $searchable = Str::lower(implode(' ', [
                        $customer->customer_code,
                        $customer->national_id,
                        $customer->first_name,
                        $customer->last_name,
                    ]));

                    return Str::contains($searchable, $needle);
                })
                ->values();
        }

        return view('delinquencies.index', [
            'debtors' => $debtors,
            'search' => $search,
            'total' => (float) $debtors->sum('total'),
        ]);
    }
}
