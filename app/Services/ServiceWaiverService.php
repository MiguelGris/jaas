<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceWaiver;
use App\Models\User;
use App\Support\ResourceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ServiceWaiverService
{
    public function waive(Customer $customer, array $ids, string $mode, float $value, string $reason, User $user, ?int $campaignId = null): array
    {
        abort_unless(ResourceAccess::allows($user, 'payments', 'store'), 403);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || trim($reason) === '' || mb_strlen($reason) > 250) {
            throw ValidationException::withMessages(['service_waiver' => 'Selecciona cuotas e indica el motivo.']);
        }

        return DB::transaction(function () use ($customer, $ids, $mode, $value, $reason, $user, $campaignId): array {
            $connectionIds = Invoice::query()->whereIn('id', $ids)->pluck('connection_id')->unique()->sort();
            Connection::query()->whereIn('id', $connectionIds)->orderBy('id')->lockForUpdate()->get();
            $invoices = Invoice::query()->whereIn('id', $ids)->where('status', '!=', 'CANCELLED')->whereHas('connection.property', fn ($q) => $q->where('customer_id', $customer->id))->orderBy('period_starts_on')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($invoices->count() !== count($ids)) {
                throw ValidationException::withMessages(['service_waiver' => 'Una cuota no pertenece al cliente o está anulada.']);
            }
            $debts = app(DebtService::class);
            $rows = $invoices->map(fn ($invoice) => ['id' => $invoice->id, 'balance' => $debts->decorateInvoice($invoice)->waivable_service_balance])->values();
            if ($rows->contains(fn ($row) => $row['balance'] <= 0)) {
                throw ValidationException::withMessages(['service_waiver' => 'Una cuota ya fue pagada o condonada.']);
            }
            $plan = app(WaiverPlanService::class)->plan($rows, $mode, $value);
            $waivers = [];
            foreach ($plan as $row) {
                $invoice = $invoices[$row['id']];
                $debts->synchroniseInvoice($invoice);
                $waiver = ServiceWaiver::query()->create(['invoice_id' => $invoice->id, 'campaign_id' => $campaignId, 'user_id' => $user->id, 'amount' => $row['waive'], 'balance_before' => $row['balance'], 'balance_after' => round($row['balance'] - $row['waive'], 2), 'reason' => trim($reason)]);
                app(AuditService::class)->created($user, $waiver, ['customer_id' => $customer->id]);
                $debts->synchroniseInvoice($invoice->fresh());
                $waivers[] = $waiver;
            }

            return $waivers;
        }, 3);
    }
}
