<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\BillingCycleService;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingController extends Controller
{
    private function period(Request $request): array
    {
        return $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'scope' => ['nullable', 'in:month,cycle'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
    }

    private function summarize(array $data, BillingService $billing, bool $generate): array
    {
        $months = [$data['month']];
        if (($data['scope'] ?? 'month') === 'cycle') {
            $cycle = app(BillingCycleService::class)->forMonth($data['month']);
            if ($cycle['months'] > 24) {
                throw ValidationException::withMessages(['scope' => 'Para ciclos de más de 24 meses, emite un mes a la vez.']);
            }
            $months = [];
            for ($date = $cycle['start']->copy(); $date->lte($cycle['end']); $date->addMonth()) {
                $months[] = $date->format('Y-m');
            }
        }
        $result = ['month' => $data['month'], 'scope' => $data['scope'] ?? 'month', 'months' => $months, 'rows' => [], 'ready' => 0, 'existing' => 0, 'omitted' => 0, 'created' => 0, 'invoice_ids' => []];
        foreach ($months as $month) {
            $summary = $generate ? $billing->generateWithSummary($month, isset($data['customer_id']) ? (int) $data['customer_id'] : null) : $billing->previewForMonth($month, isset($data['customer_id']) ? (int) $data['customer_id'] : null);
            foreach ($summary['rows'] as $row) {
                $result['rows'][] = $row + ['month' => $month];
            }
            foreach (['ready', 'existing', 'omitted', 'created'] as $key) {
                $result[$key] += $summary[$key] ?? 0;
            }
            $result['invoice_ids'] = array_merge($result['invoice_ids'], $summary['invoice_ids'] ?? []);
        }

        return $result;
    }

    public function index(Request $request, BillingService $billing)
    {
        if (! $request->has('month')) {
            $request->merge(['month' => now()->format('Y-m')]);
        }
        $data = $this->period($request);
        $summary = $this->summarize($data, $billing, false);

        return view('billing.generate', ['summary' => $summary, 'customers' => Customer::query()->orderBy('last_name')->get(), 'customerId' => $data['customer_id'] ?? null]);
    }

    public function store(Request $request, BillingService $billing, AuditService $audit)
    {
        $data = $this->period($request);
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:250']]);
        $summary = DB::transaction(function () use ($request, $data, $billing, $audit) {
            $summary = $this->summarize($data, $billing, true);
            foreach (Invoice::query()->whereIn('id', $summary['invoice_ids'])->get() as $invoice) {
                $audit->created($request->user(), $invoice, ['emission_reason' => $request->input('reason'), 'emission_period' => $invoice->period_starts_on->format('Y-m'), 'emission_scope' => $data['scope'] ?? 'month']);
            }
            if ($summary['created'] > 0) {
                Setting::query()->updateOrCreate(['key' => 'billing_last_manual_run'], ['value' => now()->format('Y-m-d H:i:s').' · '.$data['month'].' · '.$summary['created'].' cuotas', 'description' => 'Última emisión manual completada']);
            }

            return $summary;
        });

        return redirect()->route('billing.index', $data)->with('success', "Emisión completada: {$summary['created']} cuotas nuevas; {$summary['existing']} ya existentes; {$summary['omitted']} omitidas. Consulta las causas en el detalle.");
    }
}
