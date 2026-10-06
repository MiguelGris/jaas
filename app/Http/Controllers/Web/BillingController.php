<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BillingController extends Controller
{
    private function period(Request $request): array
    {
        return $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
    }

    public function index(Request $request, BillingService $billing)
    {
        if (! $request->has('month')) {
            $request->merge(['month' => now()->format('Y-m')]);
        }
        $data = $this->period($request);
        $summary = $billing->previewForMonth($data['month'], isset($data['customer_id']) ? (int) $data['customer_id'] : null);

        return view('billing.generate', ['summary' => $summary, 'customers' => Customer::query()->orderBy('last_name')->get(), 'customerId' => $data['customer_id'] ?? null]);
    }

    public function store(Request $request, BillingService $billing, AuditService $audit)
    {
        $data = $this->period($request);
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:250']]);
        $summary = DB::transaction(function () use ($request, $data, $billing, $audit) {
            $summary = $billing->generateWithSummary($data['month'], isset($data['customer_id']) ? (int) $data['customer_id'] : null);
            foreach (Invoice::query()->whereIn('id', $summary['invoice_ids'])->get() as $invoice) {
                $audit->created($request->user(), $invoice, ['emission_reason' => $request->input('reason'), 'emission_period' => $data['month']]);
            }
            if ($summary['created'] > 0) {
                Setting::query()->updateOrCreate(['key' => 'billing_last_manual_run'], ['value' => now()->format('Y-m-d H:i:s').' · '.$data['month'].' · '.$summary['created'].' cuotas', 'description' => 'Última emisión manual completada']);
            }

            return $summary;
        });

        return redirect()->route('billing.index', $data)->with('success', "Emisión completada: {$summary['created']} cuotas nuevas; {$summary['existing']} ya existentes; {$summary['omitted']} omitidas. Consulta las causas en el detalle.");
    }
}
