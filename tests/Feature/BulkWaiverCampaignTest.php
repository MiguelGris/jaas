<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyType;
use App\Models\AuditLog;
use App\Models\Fine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\WaiverCampaign;
use App\Services\BillingService;
use App\Services\BulkWaiverService;
use App\Services\DebtService;
use App\Services\PaymentCancellationService;
use App\Services\PaymentCollectionService;
use App\Services\PaymentConceptService;
use App\Support\ResourceAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesBillingContext;
use Tests\TestCase;

class BulkWaiverCampaignTest extends TestCase
{
    use CreatesBillingContext;
    use RefreshDatabase;

    private function debts(int $count = 2): array
    {
        [$user, $customers, , $method] = $this->context($count);
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            app(BillingService::class)->generateForMonth($month);
        }
        $assembly = Assembly::query()->create(['assembly_type_id' => AssemblyType::query()->create(['name' => 'Ordinaria'])->id, 'held_on' => '2026-08-01', 'absence_fine' => 10, 'status' => 'HELD']);
        foreach ($customers as $customer) {
            Fine::query()->updateOrCreate(['customer_id' => $customer->id, 'assembly_id' => $assembly->id], ['reason' => 'Inasistencia', 'amount' => 10, 'generated_on' => '2026-08-01', 'status' => 'PENDING']);
        }
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));

        return [$user, $customers, $method, $assembly];
    }

    public function test_full_mass_campaign_is_admin_only_previewed_and_audited_without_cash_income(): void
    {
        [$user, $customers] = $this->debts();
        $this->actingAs($user)->get(route('waivers.index', ['per_page' => 25, 'kind' => 'both']))->assertOk()->assertSee('Campañas de condonación')->assertViewHas('rows', fn ($rows) => $rows->total() === 4);
        $this->post(route('waivers.preview'), ['scope' => 'filtered', 'kind' => 'both', 'mode' => 'all', 'reason' => 'Campaña urgente'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $snapshot = session('bulk_waiver_preview');
        $this->assertCount(4, $snapshot['rows']);
        $this->assertDatabaseCount('waiver_campaigns', 0);
        $this->get(route('waivers.review', ['token' => $snapshot['token']]))->assertOk()->assertSee('44.00');
        $this->post(route('waivers.store'), ['token' => $snapshot['token'], 'confirmed' => 1])->assertSessionHasNoErrors()->assertRedirect(route('waivers.index'));
        $campaign = WaiverCampaign::query()->firstOrFail();
        $this->assertSame('24.00', $campaign->total_late_fees);
        $this->assertSame('20.00', $campaign->total_fines);
        $this->assertSame(2, $campaign->customer_count);
        $this->assertDatabaseCount('late_fee_waivers', 2);
        $this->assertDatabaseCount('fine_waivers', 2);
        $this->assertSame(5, AuditLog::query()->whereIn('table_name', ['waiver_campaigns', 'late_fee_waivers', 'fine_waivers'])->count());
        foreach ($customers as $customer) {
            $this->assertSame(45.0, app(DebtService::class)->pendingForCustomer($customer)['total']);
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0.0, app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31')['fines']);
        $same = app(BulkWaiverService::class)->apply($snapshot, $user);
        $this->assertSame($campaign->id, $same->id);
        $this->assertDatabaseCount('fine_waivers', 2);
        $this->get(route('resources.index', ['resource' => 'audit-logs', 'q' => 'condonaciones']))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 5);
    }

    public function test_non_administrator_with_all_permissions_cannot_use_mass_campaigns(): void
    {
        [$user] = $this->debts();
        $role = Role::query()->create(['name' => 'TESORERO']);
        $role->permissions()->sync(Permission::query()->pluck('id'));
        $user->update(['role_id' => $role->id]);
        $this->actingAs($user->fresh());
        $this->get(route('waivers.index'))->assertForbidden();
        $this->post(route('waivers.preview'), ['scope' => 'filtered', 'mode' => 'all', 'reason' => 'Campaña'])->assertForbidden();
        $this->get(route('waivers.review', ['token' => (string) Str::uuid()]))->assertForbidden();
        $this->post(route('waivers.store'), ['token' => (string) Str::uuid(), 'confirmed' => 1])->assertForbidden();
        $item = ['route_name' => 'waivers.index', 'admin_only' => true];
        $this->assertFalse(ResourceAccess::allowsNavigation($user->fresh(), $item));
        $this->assertDatabaseCount('waiver_campaigns', 0);
    }

    public function test_selected_percentage_campaign_only_changes_selected_debt_and_remaining_fine_can_be_paid(): void
    {
        [$user, $customers, $method] = $this->debts();
        $fine = Fine::query()->where('customer_id', $customers->first()->id)->firstOrFail();
        $this->actingAs($user)->post(route('waivers.preview'), ['scope' => 'selected', 'debts' => ['fine:'.$fine->id], 'mode' => 'percentage', 'value' => 50, 'reason' => 'Multa parcial'])->assertSessionHasNoErrors();
        $snapshot = session('bulk_waiver_preview');
        $this->post(route('waivers.store'), ['token' => $snapshot['token'], 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame(5.0, app(DebtService::class)->decorateFine($fine->fresh())->balance_due);
        $this->assertSame(67.0, app(DebtService::class)->pendingForCustomer($customers->last())['total']);
        $payment = app(PaymentCollectionService::class)->collect($customers->first(), [], [$fine->id], ['payment_method_id' => $method->id], $user);
        $this->assertSame('5.00', $payment->amount);
        $this->assertSame(5.0, app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31')['fines']);
        app(PaymentCancellationService::class)->cancel($payment, $user, 'Corrección de cobro');
        $this->assertSame(5.0, app(DebtService::class)->decorateFine($fine->fresh())->balance_due);
    }

    public function test_stale_campaign_is_rejected_atomically_after_payment_changes_a_selected_balance(): void
    {
        [$user, $customers, $method] = $this->debts();
        $this->actingAs($user)->post(route('waivers.preview'), ['scope' => 'filtered', 'mode' => 'all', 'reason' => 'Revisión de saldos'])->assertSessionHasNoErrors();
        $snapshot = session('bulk_waiver_preview');
        $fine = Fine::query()->where('customer_id', $customers->last()->id)->firstOrFail();
        app(PaymentCollectionService::class)->collect($customers->last(), [], [$fine->id], ['payment_method_id' => $method->id], $user);
        $this->post(route('waivers.store'), ['token' => $snapshot['token'], 'confirmed' => 1])->assertSessionHasErrors('campaign');
        $this->assertDatabaseCount('waiver_campaigns', 0);
        $this->assertDatabaseCount('fine_waivers', 0);
        $this->assertDatabaseCount('late_fee_waivers', 0);
    }

    public function test_mass_filters_amount_limits_and_scope_include_other_pages(): void
    {
        [$user, $customers] = $this->debts(14);
        $service = app(BulkWaiverService::class);
        $filtered = $service->eligible(['kind' => 'fines', 'q' => $customers->first()->customer_code]);
        $this->assertCount(1, $filtered);
        $this->assertSame(3.0, (float) collect($service->plan($filtered, 'amount', 3))->sum('waive'));
        $this->assertCount(0, $service->eligible(['kind' => 'mora', 'until' => '2026-03-01']));
        $this->actingAs($user)->post(route('waivers.preview'), ['scope' => 'filtered', 'mode' => 'percentage', 'value' => 101, 'reason' => 'Inválido'])->assertSessionHasErrors('campaign');
        $this->post(route('waivers.preview'), ['scope' => 'filtered', 'mode' => 'amount', 'value' => 1000, 'reason' => 'Por cliente'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('waiver_campaigns', 0);
        $this->get(route('waivers.index', ['per_page' => 25, 'kind' => 'both']))->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 28 && $rows->count() === 25);
        $this->post(route('waivers.preview'), ['scope' => 'filtered', 'mode' => 'all', 'reason' => 'Todas las páginas'])->assertSessionHasNoErrors();
        $this->assertCount(28, session('bulk_waiver_preview')['rows']);
    }

    public function test_ten_customer_campaign_caps_five_smaller_debts_and_discounts_five_larger_debts(): void
    {
        [$user, $customers] = $this->debts(10);
        foreach ($customers as $index => $customer) {
            Fine::query()->where('customer_id', $customer->id)->update(['amount' => $index < 5 ? 6 : 14]);
        }
        $this->actingAs($user)->post(route('waivers.preview'), ['scope' => 'filtered', 'kind' => 'fines', 'mode' => 'amount', 'value' => 10, 'reason' => 'S/ 10 por cliente'])->assertSessionHasNoErrors();
        $snapshot = session('bulk_waiver_preview');
        $this->assertSame('amount_per_customer', $snapshot['mode']);
        $this->assertCount(10, $snapshot['rows']);
        $this->assertSame(80.0, (float) collect($snapshot['rows'])->sum('waive'));
        $this->get(route('waivers.review', ['token' => $snapshot['token']]))->assertOk()->assertSee('por cliente')->assertSee('80.00');
        $this->post(route('waivers.store'), ['token' => $snapshot['token'], 'confirmed' => 1])->assertSessionHasNoErrors();
        foreach ($customers as $index => $customer) {
            $fine = Fine::query()->where('customer_id', $customer->id)->firstOrFail();
            $this->assertSame($index < 5 ? 0.0 : 4.0, (float) app(DebtService::class)->decorateFine($fine)->balance_due);
            $this->assertSame($index < 5 ? 6.0 : 10.0, (float) $fine->waivers()->sum('amount'));
            $this->assertLessThanOrEqual((float) $fine->amount, (float) $fine->waivers()->sum('amount'));
        }
        $campaign = WaiverCampaign::query()->firstOrFail();
        $this->assertSame('amount_per_customer', $campaign->mode);
        $this->assertSame('80.00', $campaign->total_fines);
        $this->assertSame(10, $campaign->customer_count);
        $this->assertSame(11, AuditLog::query()->whereIn('table_name', ['fine_waivers', 'waiver_campaigns'])->count());
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_per_customer_limit_is_shared_across_selected_debts_and_unused_amount_is_not_transferred(): void
    {
        $rows = collect([
            ['customer_id' => 1, 'id' => 1, 'type' => 'service', 'balance' => 2.0],
            ['customer_id' => 2, 'id' => 2, 'type' => 'mora', 'balance' => 7.0],
            ['customer_id' => 1, 'id' => 3, 'type' => 'fine', 'balance' => 3.0],
            ['customer_id' => 2, 'id' => 4, 'type' => 'service', 'balance' => 8.0],
        ]);
        $plan = collect(app(BulkWaiverService::class)->plan($rows, 'amount', 10, perCustomer: true));
        $this->assertSame(5.0, (float) $plan->where('customer_id', 1)->sum('waive'));
        $this->assertSame(10.0, (float) $plan->where('customer_id', 2)->sum('waive'));
        $this->assertSame(3.0, (float) $plan->firstWhere('id', 4)['waive']);
        foreach ($plan as $row) {
            $this->assertLessThanOrEqual($row['balance'], $row['waive']);
        }
        $exact = collect(app(BulkWaiverService::class)->plan(collect([['customer_id' => 1, 'balance' => 0.55], ['customer_id' => 2, 'balance' => 1.05]]), 'amount', 1, perCustomer: true));
        $this->assertSame(1.55, round((float) $exact->sum('waive'), 2));
    }
}
