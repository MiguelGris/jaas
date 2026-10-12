<?php

namespace Database\Seeders;

use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\Property;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\DebtService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Run manually only: this fixture is never included in DatabaseSeeder. */
final class DemoUnpaidCyclesSeeder extends Seeder
{
    public function run(): void
    {
        $customer = DB::transaction(function (): Customer {
            $marker = 'CLIENTE_DE_PRUEBA_CICLOS_IMPAGOS_2026';
            $status = CustomerStatus::query()->whereIn('name', ['ACTIVE', 'ACTIVO'])->firstOrFail();
            $connectionStatus = ConnectionStatus::query()->whereIn('name', ['ACTIVE', 'ACTIVO'])->firstOrFail();
            $neighborhood = Neighborhood::query()->where('active', true)->firstOrFail();
            $type = ConnectionType::query()->firstOrFail();
            $customer = Customer::query()->firstOrCreate(['notes' => $marker], [
                'first_name' => 'Prueba', 'last_name' => 'Ciclos impagos', 'national_id' => null,
                'customer_status_id' => $status->id, 'registered_on' => '2026-01-01',
                'address' => 'Predio de prueba para cobranza y condonaciones',
            ]);
            $this->auditNew($customer);
            $property = Property::query()->firstOrCreate(['customer_id' => $customer->id, 'reference' => $marker], [
                'neighborhood_id' => $neighborhood->id, 'address' => 'Predio de prueba — ciclos impagos', 'active' => true,
            ]);
            $this->auditNew($property);
            $connection = Connection::query()->firstOrCreate(['property_id' => $property->id, 'notes' => $marker], [
                'connection_type_id' => $type->id, 'connection_status_id' => $connectionStatus->id,
                'payment_mode' => Connection::PAYMENT_FIXED, 'installed_on' => '2026-01-01',
            ]);
            $this->auditNew($connection);
            foreach (['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'] as $month) {
                $summary = app(BillingService::class)->generateWithSummary($month, $customer->id);
                if ($summary['omitted'] > 0) {
                    throw new RuntimeException('No se pudo emitir el mes '.$month.': '.collect($summary['rows'])->pluck('reason')->filter()->implode('; '));
                }
                foreach ($summary['invoice_ids'] ?? [] as $id) {
                    app(AuditService::class)->created(null, Invoice::query()->findOrFail($id), ['reason' => $marker]);
                }
            }

            return $customer;
        });
        $charges = app(DebtService::class)->pendingForCustomer($customer);
        $this->command?->info(json_encode([
            'customer_id' => $customer->id, 'code' => $customer->customer_code, 'name' => $customer->display_name,
            'invoices' => $charges['invoices']->count(), 'cycles' => $charges['invoices']->groupBy(fn ($invoice) => $invoice->cycleKey())->count(),
            'service' => $charges['invoices']->sum('service_balance'), 'mora' => $charges['invoices']->sum('late_fee_balance'),
            'total' => $charges['total'],
        ], JSON_UNESCAPED_UNICODE));
    }

    private function auditNew(Model $model): void
    {
        if ($model->wasRecentlyCreated) {
            app(AuditService::class)->created(null, $model, ['reason' => 'Cliente de prueba solicitado para comprobar ciclos impagos']);
        }
    }
}
