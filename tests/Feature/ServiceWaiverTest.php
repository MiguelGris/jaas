<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\ConnectionStatus;
use App\Models\ConnectionType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Invoice;
use App\Models\LateFeeSetting;
use App\Models\Neighborhood;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Property;
use App\Models\Rate;
use App\Models\Role;
use App\Models\ServiceWaiver;
use App\Models\UsageType;
use App\Models\User;
use App\Models\WaiverCampaign;
use App\Services\BillingService;
use App\Services\DebtService;
use App\Services\PaymentCancellationService;
use App\Services\PaymentCollectionService;
use App\Services\PaymentConceptService;
use App\Services\ServiceWaiverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceWaiverTest extends TestCase
{
    use RefreshDatabase;

    private function context(int $clients = 1): array
    {
        $this->travelTo(Carbon::parse('2026-01-10 10:00:00'));
        $user = User::query()->create(['name' => 'Administrador', 'email' => 'cycles@example.test', 'password' => 'test-password', 'active' => true, 'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id]);
        $usage = UsageType::query()->create(['name' => 'RESIDENCIAL']);
        foreach ([2026, 2027, 2028] as $year) {
            Rate::query()->create(['usage_type_id' => $usage->id, 'year' => $year, 'amount' => 15, 'starts_on' => $year.'-01-01', 'approved_by_assembly' => true]);
        }
        $fee = LateFeeSetting::query()->create(['monthly_amount' => 2, 'grace_months' => 1, 'starts_on' => '2026-01-01']);
        $status = CustomerStatus::query()->create(['name' => 'ACTIVE']);
        $neighborhood = Neighborhood::query()->create(['name' => 'Centro', 'active' => true]);
        $type = ConnectionType::query()->create(['name' => 'Agua']);
        $connectionStatus = ConnectionStatus::query()->create(['name' => 'ACTIVE']);
        $customers = collect();
        for ($i = 0; $i < $clients; $i++) {
            $customer = Customer::query()->create(['first_name' => 'Cliente', 'last_name' => 'Ciclo '.$i, 'customer_status_id' => $status->id, 'registered_on' => '2026-01-01']);
            $property = Property::query()->create(['customer_id' => $customer->id, 'neighborhood_id' => $neighborhood->id, 'address' => 'Dirección '.$i, 'active' => true]);
            Connection::query()->create(['property_id' => $property->id, 'connection_type_id' => $type->id, 'connection_status_id' => $connectionStatus->id, 'installed_on' => '2026-01-01']);
            $customers->push($customer);
        }
        $method = PaymentMethod::query()->create(['name' => 'CASH']);

        return [$user, $customers, $fee, $method];
    }

    private function unpaid(): array
    {
        [$user,$customers,,$method] = $this->context(2);
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            app(BillingService::class)->generateForMonth($month);
        }
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
        $ids = Invoice::query()->whereHas('connection.property', fn ($q) => $q->where('customer_id', $customers->first()->id))->orderBy('period_starts_on')->pluck('id')->all();

        return [$user, $customers, $method, $ids];
    }

    public function test_individual_full_service_waiver_issues_monthly_receipts_and_freezes_cycle_mora(): void
    {
        [$user,$customers,,$ids] = $this->unpaid();
        $customer = $customers->first();
        $this->actingAs($user)->post(route('collections.waive-service'), ['customer_id' => $customer->id, 'service_invoices' => $ids, 'service_mode' => 'all', 'service_reason' => 'Condonación de servicio'])->assertSessionHasNoErrors();
        $waivers = ServiceWaiver::query()->orderBy('id')->get();
        $this->assertCount(3, $waivers);
        $this->assertSame(45.0, (float) $waivers->sum('amount'));
        $this->assertSame(0.0, (float) $waivers->sum('balance_after'));
        foreach ($waivers as $waiver) {
            $this->get(route('service-waiver-receipts.show', $waiver))->assertOk()->assertSee('RECIBO DE CUOTA MENSUAL')->assertSee('DEUDA CONDONADA')->assertSee($waiver->receipt_code)->assertSee($waiver->invoice->period_starts_on->format('m/Y'))->assertSee('Dinero recibido')->assertSee('S/ 0.00');
        }
        $this->assertSame(12.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $this->travelTo(Carbon::parse('2027-02-12 10:00:00'));
        $this->assertSame(12.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $this->get(route('collections.create', ['customer' => $customer->id]))->assertOk()->assertSee('Recibos de servicio condonado');
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(3, AuditLog::query()->where('table_name', 'service_waivers')->count());
    }

    public function test_partial_waiver_then_service_and_mora_payment_and_annulment_preserve_accounting(): void
    {
        [$user,$customers,$method,$ids] = $this->unpaid();
        $customer = $customers->first();
        $waivers = app(ServiceWaiverService::class)->waive($customer, $ids, 'amount', 20, 'Apoyo parcial', $user);
        $this->assertCount(2, $waivers);
        $this->actingAs($user)->get(route('service-waiver-receipts.show', $waivers[1]))->assertOk()->assertSee('DEUDA PARCIALMENTE CONDONADA')->assertSee('10.00');
        $debt = app(DebtService::class)->pendingForCustomer($customer);
        $this->assertSame(25.0, (float) $debt['invoices']->sum('service_balance'));
        $this->assertSame(12.0, (float) $debt['invoices']->sum('late_fee_balance'));
        $remaining = $debt['invoices']->filter(fn ($invoice) => $invoice->service_balance > 0)->pluck('id')->all();
        $fees = $debt['invoices']->filter(fn ($invoice) => $invoice->late_fee_balance > 0)->pluck('id')->all();
        $payment = app(PaymentCollectionService::class)->collect($customer, $remaining, [], ['payment_method_id' => $method->id, 'separate_late_fees' => true, 'late_fees' => $fees], $user);
        $this->assertSame('37.00', $payment->amount);
        $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $totals = app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31');
        $this->assertSame(25.0, $totals['services']);
        $this->assertSame(12.0, $totals['late_fees']);
        app(PaymentCancellationService::class)->cancel($payment, $user, 'Corrección');
        $this->assertSame(37.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $this->assertSame(20.0, (float) ServiceWaiver::query()->sum('amount'));
        $this->assertSame(0.0, app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31')['services']);
    }

    public function test_over_debt_amount_or_percentage_is_rejected_without_credit_or_receipt(): void
    {
        [$user,$customers,,$ids] = $this->unpaid();
        $customer = $customers->first();
        $data = ['customer_id' => $customer->id, 'service_invoices' => $ids, 'service_mode' => 'amount', 'service_value' => 100, 'service_reason' => 'Importe excesivo'];
        $this->actingAs($user)->post(route('collections.waive-service'), $data)->assertSessionHasErrors('campaign');
        $data['service_mode'] = 'percentage';
        $data['service_value'] = 101;
        $this->post(route('collections.waive-service'), $data)->assertSessionHasErrors('campaign');
        $this->assertDatabaseCount('service_waivers', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(57.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        $data['service_mode'] = 'amount';
        $data['service_value'] = 45;
        $this->post(route('collections.waive-service'), $data)->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) ServiceWaiver::query()->sum('balance_after'));
        $this->post(route('collections.waive-service'), $data)->assertSessionHasErrors('service_waiver');
        $this->assertDatabaseCount('service_waivers', 3);
    }

    public function test_administrator_campaign_can_waive_service_and_expose_all_monthly_receipts(): void
    {
        [$user,$customers] = $this->unpaid();
        $this->actingAs($user)->post(route('waivers.preview'), ['kind' => 'service', 'scope' => 'filtered', 'mode' => 'all', 'reason' => 'Campaña de servicio'])->assertSessionHasNoErrors();
        $snapshot = session('bulk_waiver_preview');
        $this->assertCount(6, $snapshot['rows']);
        $this->get(route('waivers.review', ['token' => $snapshot['token']]))->assertOk()->assertSee('Servicio a condonar');
        $this->post(route('waivers.store'), ['token' => $snapshot['token'], 'confirmed' => 1])->assertSessionHasNoErrors();
        $campaign = WaiverCampaign::query()->firstOrFail();
        $this->assertSame('90.00', $campaign->total_services);
        $this->assertSame('0.00', $campaign->total_late_fees);
        $this->assertDatabaseCount('service_waivers', 6);
        $this->get(route('waivers.receipts', $campaign))->assertOk()->assertSee('Recibos mensuales');
        foreach ($customers as $customer) {
            $this->assertSame(12.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(7, AuditLog::query()->whereIn('table_name', ['service_waivers', 'waiver_campaigns'])->count());
        $this->get(route('waivers.index'))->assertOk()->assertSee('Recibos mensuales');
    }

    public function test_payment_operator_can_waive_service_but_cannot_apply_mass_campaigns(): void
    {
        [$user,$customers,,$ids] = $this->unpaid();
        $role = Role::query()->create(['name' => 'CAJERO']);
        $permission = Permission::query()->firstOrCreate(['name' => 'payments.create']);
        $role->permissions()->sync([$permission->id]);
        $user->update(['role_id' => $role->id]);
        $this->actingAs($user->fresh())->post(route('collections.waive-service'), ['customer_id' => $customers->first()->id, 'service_invoices' => [$ids[0]], 'service_mode' => 'all', 'service_reason' => 'Autorización de pago'])->assertSessionHasNoErrors();
        $waiver = ServiceWaiver::query()->firstOrFail();
        $this->get(route('service-waiver-receipts.show', $waiver))->assertOk();
        $this->post(route('waivers.preview'), ['scope' => 'filtered', 'kind' => 'service', 'mode' => 'all', 'reason' => 'Campaña'])->assertForbidden();
        $this->get(route('waivers.index'))->assertForbidden();
    }

    public function test_mixed_service_and_mora_campaign_fully_clears_debt_without_future_mora_or_credit(): void
    {
        [$user,$customers] = $this->unpaid();
        $this->actingAs($user)->post(route('waivers.preview'), ['kind' => 'all', 'scope' => 'filtered', 'mode' => 'all', 'reason' => 'Condonación completa'])->assertSessionHasNoErrors();
        $snapshot = session('bulk_waiver_preview');
        $this->assertCount(8, $snapshot['rows']);
        $this->post(route('waivers.store'), ['token' => $snapshot['token'], 'confirmed' => 1])->assertSessionHasNoErrors();
        foreach ($customers as $customer) {
            $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        }
        $this->travelTo(Carbon::parse('2027-02-01 10:00:00'));
        foreach ($customers as $customer) {
            $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        }
        $this->assertSame(0, ServiceWaiver::query()->where('balance_after', '<', 0)->count());
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_mass_service_amount_is_capped_to_debt_and_receipts_are_read_only(): void
    {
        [$user] = $this->unpaid();
        $this->actingAs($user)->post(route('waivers.preview'), ['kind' => 'service', 'scope' => 'filtered', 'mode' => 'amount', 'value' => 91, 'reason' => 'Importe por cliente'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('service_waivers', 0);
        $this->assertDatabaseCount('waiver_campaigns', 0);
        $this->post(route('waivers.preview'), ['kind' => 'service', 'scope' => 'filtered', 'mode' => 'amount', 'value' => 90, 'reason' => 'Saldo exacto'])->assertSessionHasNoErrors();
        $snapshot = session('bulk_waiver_preview');
        $this->post(route('waivers.store'), ['token' => $snapshot['token'], 'confirmed' => 1])->assertSessionHasNoErrors();
        $receipt = ServiceWaiver::query()->firstOrFail();
        $this->get(route('service-waiver-receipts.show',$receipt))->assertOk();
        $this->get(route('service-waiver-receipts.show',$receipt))->assertOk();
        $this->assertDatabaseCount('service_waivers',6);
        $this->assertSame(0,ServiceWaiver::query()->where('balance_after','<',0)->count());
    }
}
