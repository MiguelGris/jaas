<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\Connection;
use App\Models\Customer;
use App\Models\Fine;
use App\Models\FineWaiver;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WaiverCampaign;
use App\Support\ResourceAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BulkWaiverService
{
    public function eligible(array $filters): Collection
    {
        $rows = collect();
        $debts = app(DebtService::class);
        $matchesCustomer = static function ($query) use ($filters): void {
            foreach (preg_split('/\s+/', trim($filters['q'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('business_name', 'like', "%{$term}%")->orWhere('national_id', 'like', "%{$term}%")->orWhere('customer_code', 'like', "%{$term}%"));
            }
        };
        if (($filters['kind'] ?? 'both') !== 'fines') {
            $invoices = Invoice::query()->where('status', '!=', 'CANCELLED')
                ->whereHas('connection.property.customer', $matchesCustomer)
                ->when(! empty($filters['neighborhood_id']), fn ($q) => $q->whereHas('connection.property', fn ($q) => $q->where('neighborhood_id', $filters['neighborhood_id'])))
                ->when(! empty($filters['until']), fn ($q) => $q->whereDate('due_on', '<=', $filters['until']))
                ->with(['connection.property.customer', 'billingPeriod'])->get();
            foreach ($invoices as $invoice) {
                $invoice = $debts->decorateInvoice($invoice);
                if (in_array($filters['kind'] ?? 'both', ['service', 'all'], true) && $invoice->waivable_service_balance > 0) {
                    $rows->push($this->row('service', $invoice, $invoice->connection->property->customer, $invoice->waivable_service_balance));
                }
                if (in_array($filters['kind'] ?? 'both', ['both', 'mora', 'all'], true) && $invoice->late_fee_balance > 0) {
                    $rows->push($this->row('mora', $invoice, $invoice->connection->property->customer, $invoice->late_fee_balance));
                }
            }
        }
        if (in_array($filters['kind'] ?? 'both', ['both', 'fines', 'all'], true)) {
            $fines = Fine::query()->where('status', 'PENDING')->whereHas('assembly', fn ($q) => $q->where('status', 'HELD'))
                ->whereHas('customer', $matchesCustomer)
                ->when(! empty($filters['neighborhood_id']), fn ($q) => $q->whereHas('customer.properties', fn ($q) => $q->where('neighborhood_id', $filters['neighborhood_id'])))
                ->when(! empty($filters['until']), fn ($q) => $q->whereDate('generated_on', '<=', $filters['until']))
                ->with(['customer', 'assembly'])->get();
            foreach ($fines as $fine) {
                $fine = $debts->decorateFine($fine);
                if ($fine->balance_due > 0) {
                    $rows->push($this->row('fine', $fine, $fine->customer, $fine->balance_due));
                }
            }
        }

        return $rows->sortBy(fn ($row) => $row['date'].'-'.$row['key'])->values();
    }

    private function row(string $type, $debt, Customer $customer, float $balance): array
    {
        return ['key' => $type.':'.$debt->id, 'type' => $type, 'id' => $debt->id, 'customer_id' => $customer->id,
            'customer' => $customer->display_name, 'customer_code' => $customer->customer_code,
            'code' => $type !== 'fine' ? $debt->invoice_code : $debt->fine_code,
            'detail' => $type === 'service' ? 'Cuota mensual '.$debt->period_starts_on->format('m/Y') : ($type === 'mora' ? $debt->cycleLabel() : $debt->reason),
            'date' => ($type !== 'fine' ? $debt->due_on : $debt->generated_on)->toDateString(), 'balance' => $balance];
    }

    public function plan(Collection $rows, string $mode, float $value, bool $perCustomer = false): array
    {
        return app(WaiverPlanService::class)->plan($rows, $mode, $value, $perCustomer);
    }

    public function apply(array $snapshot, User $user): WaiverCampaign
    {
        abort_unless(ResourceAccess::isAdministrator($user), 403);

        return DB::transaction(function () use ($snapshot, $user): WaiverCampaign {
            $existing = WaiverCampaign::query()->where('request_token', $snapshot['token'])->first();
            if ($existing) {
                return $existing;
            }
            $rows = collect($snapshot['rows']);
            $fineIds = $rows->where('type', 'fine')->pluck('id');
            $invoiceIds = $rows->whereIn('type', ['mora', 'service'])->pluck('id')->unique();
            $assemblyIds = Fine::query()->whereIn('id', $fineIds)->pluck('assembly_id')->unique()->sort();
            Assembly::query()->whereIn('id', $assemblyIds)->orderBy('id')->lockForUpdate()->get();
            $connectionIds = Invoice::query()->whereIn('id', $invoiceIds)->pluck('connection_id')->unique()->sort();
            Connection::query()->whereIn('id', $connectionIds)->orderBy('id')->lockForUpdate()->get();
            $invoices = Invoice::query()->whereIn('id', $invoiceIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $fines = Fine::query()->whereIn('id', $fineIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $debts = app(DebtService::class);
            foreach ($rows as $row) {
                if (in_array($row['type'], ['mora', 'service'], true)) {
                    $debt = $invoices->get($row['id']);
                    $valid = $debt && $debt->status !== 'CANCELLED' && $debt->connection->property->customer_id === $row['customer_id'];
                    $balance = $valid ? ($row['type'] === 'service' ? $debts->decorateInvoice($debt)->waivable_service_balance : $debts->decorateInvoice($debt)->late_fee_balance) : -1;
                } else {
                    $debt = $fines->get($row['id']);
                    $valid = $debt && $debt->status === 'PENDING' && $debt->assembly->status === 'HELD' && $debt->customer_id === $row['customer_id'];
                    $balance = $valid ? $debts->decorateFine($debt)->balance_due : -1;
                }
                if ($row['waive'] <= 0 || $row['waive'] > $balance || (int) round($balance * 100) !== (int) round($row['balance'] * 100)) {
                    throw ValidationException::withMessages(['campaign' => 'Una deuda cambió desde la revisión. Vuelve a filtrar y revisar la campaña; no se aplicó ninguna condonación.']);
                }
            }
            $campaign = WaiverCampaign::query()->create([
                'request_token' => $snapshot['token'], 'user_id' => $user->id, 'reason' => $snapshot['reason'], 'mode' => $snapshot['mode'], 'value' => $snapshot['value'],
                'total_services' => $rows->where('type', 'service')->sum('waive'), 'total_late_fees' => $rows->where('type', 'mora')->sum('waive'), 'total_fines' => $rows->where('type', 'fine')->sum('waive'),
                'customer_count' => $rows->pluck('customer_id')->unique()->count(), 'debt_count' => $rows->count(),
            ]);
            foreach ($rows as $row) {
                if ($row['type'] === 'service') {
                    app(ServiceWaiverService::class)->waive(Customer::query()->findOrFail($row['customer_id']), [$row['id']], 'amount', $row['waive'], $snapshot['reason'], $user, $campaign->id);
                } elseif ($row['type'] === 'mora') {
                    app(LateFeeWaiverService::class)->waive(Customer::query()->findOrFail($row['customer_id']), [$row['id']], 'amount', $row['waive'], $snapshot['reason'], $user, $campaign->id);
                } else {
                    $waiver = FineWaiver::query()->create(['fine_id' => $row['id'], 'campaign_id' => $campaign->id, 'user_id' => $user->id, 'amount' => $row['waive'], 'reason' => $snapshot['reason']]);
                    app(AuditService::class)->created($user, $waiver, ['customer_id' => $row['customer_id']]);
                    $debts->synchroniseFine($fines[$row['id']]);
                }
            }
            app(AuditService::class)->created($user, $campaign);

            return $campaign;
        }, 3);
    }
}
