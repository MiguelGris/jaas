<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LateFeeWaiver;
use App\Models\User;
use App\Support\ResourceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LateFeeWaiverService
{
    public function waive(Customer $customer, array $ids, string $mode, float $value, string $reason, User $user, ?int $campaignId = null): float
    {
        abort_unless(ResourceAccess::allows($user, 'late-fee-settings'), 403);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || trim($reason) === '' || mb_strlen($reason) > 250 || ! is_finite($value) || ! in_array($mode, ['all', 'percentage', 'amount'], true)
            || ($mode !== 'all' && ($value <= 0 || ($mode === 'percentage' && $value > 100)))) {
            throw ValidationException::withMessages(['waiver' => 'Selecciona moras e indica un motivo y un importe o porcentaje válido.']);
        }

        return DB::transaction(function () use ($customer, $ids, $mode, $value, $reason, $user, $campaignId): float {
            $connections = Invoice::query()->whereIn('id', $ids)->pluck('connection_id')->unique()->sort();
            Connection::query()->whereIn('id', $connections)->orderBy('id')->lockForUpdate()->get();
            $invoices = Invoice::query()->whereIn('id', $ids)->where('status', '!=', 'CANCELLED')
                ->whereHas('connection.property', fn ($q) => $q->where('customer_id', $customer->id))
                ->orderBy('period_starts_on')->orderBy('id')->lockForUpdate()->get();
            if ($invoices->count() !== count($ids)) {
                throw ValidationException::withMessages(['waiver' => 'Una mora no pertenece al cliente o ya no está disponible.']);
            }
            $debts = app(DebtService::class);
            $balances = $invoices->map(fn ($invoice) => $debts->decorateInvoice($invoice)->late_fee_balance);
            if ($balances->contains(fn ($balance) => $balance <= 0) || ($mode === 'amount' && $value > round($balances->sum(), 2))) {
                throw ValidationException::withMessages(['waiver' => 'La condonación supera la deuda pendiente o incluye una mora ya cancelada.']);
            }
            $total = 0.0;
            $remaining = $value;
            foreach ($invoices as $index => $invoice) {
                $balance = $balances[$index];
                $amount = round(match ($mode) {
                    'all' => $balance,
                    'percentage' => $balance * $value / 100,
                    default => min($balance, $remaining),
                }, 2);
                if ($amount <= 0) {
                    continue;
                }
                $debts->synchroniseInvoice($invoice);
                $waiver = LateFeeWaiver::query()->create(['invoice_id' => $invoice->id, 'user_id' => $user->id, 'amount' => $amount, 'reason' => trim($reason), 'campaign_id' => $campaignId]);
                app(AuditService::class)->created($user, $waiver, ['customer_id' => $customer->id]);
                $total += $amount;
                $remaining = round($remaining - $amount, 2);
            }
            if ($total <= 0) {
                throw ValidationException::withMessages(['waiver' => 'El importe de condonación debe ser al menos S/ 0.01.']);
            }

            return round($total, 2);
        }, 3);
    }
}
