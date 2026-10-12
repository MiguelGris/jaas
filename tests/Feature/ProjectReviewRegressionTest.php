<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Rate;
use App\Services\BillingService;
use App\Services\BulkWaiverService;
use App\Services\DebtService;
use App\Services\PaymentCollectionService;
use App\Services\PaymentConceptService;
use App\Services\ServiceWaiverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\CreatesBillingContext;
use Tests\TestCase;

class ProjectReviewRegressionTest extends TestCase
{
    use CreatesBillingContext;
    use RefreshDatabase;

    private function invoices(): array
    {
        [$user,$customers,,$method] = $this->context();
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            app(BillingService::class)->generateForMonth($month);
        }
        $this->travelTo(Carbon::parse('2026-05-15 10:00:00'));

        return [$user, $customers->first(), $method, Invoice::query()->orderBy('period_starts_on')->get()];
    }

    public function test_service_waiver_does_not_forgive_a_fine_embedded_in_a_legacy_invoice(): void
    {
        [$user,$customer,,$invoices] = $this->invoices();
        $invoice = $invoices->first();
        $invoice->update(['fines' => 5, 'total' => 20]);
        $waiver = app(ServiceWaiverService::class)->waive($customer, [$invoice->id], 'all', 0, 'Solo servicio', $user)[0];
        $this->assertSame('15.00', $waiver->amount);
        $parts = app(DebtService::class)->decorateInvoice($invoice->fresh());
        $this->assertSame(5.0, (float) $parts->service_balance);
        $this->assertSame(0.0, (float) $parts->waivable_service_balance);
        $this->actingAs($user)->get(route('collections.create', ['customer' => $customer->id]))->assertOk()->assertViewHas('serviceWaiverInvoices', fn ($rows) => ! $rows->contains('id', $invoice->id));
    }

    public function test_paid_service_does_not_keep_accruing_mora_for_an_embedded_unpaid_fine(): void
    {
        [$user,$customer,$method,$invoices] = $this->invoices();
        $invoices->first()->update(['fines' => 5, 'total' => 20]);
        $payment = Payment::query()->create(['customer_id' => $customer->id, 'paid_at' => now(), 'amount' => 45, 'payment_method_id' => $method->id, 'source' => 'COUNTER', 'operation_number' => 'OP26-LEGACY-REVIEW', 'status' => 'ACTIVE']);
        foreach ($invoices as $invoice) {
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'charge_type' => 'INVOICE', 'amount' => 15]);
            app(DebtService::class)->synchroniseInvoice($invoice->fresh());
        }
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
        $charges = app(DebtService::class)->pendingForCustomer($customer);
        $this->assertSame(2.0, (float) $charges['invoices']->sum('late_fee_balance'));
        $this->assertSame(7.0, $charges['total']);
    }

    public function test_zero_service_charges_do_not_generate_mora_without_any_unpaid_service(): void
    {
        [, $customers] = $this->context();
        Rate::query()->update(['amount' => 0]);
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            app(BillingService::class)->generateForMonth($month);
        }
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
        $this->assertSame(0.0, app(DebtService::class)->pendingForCustomer($customers->first())['total']);
    }

    public function test_invalid_customer_query_is_validated_instead_of_throwing_a_server_error(): void
    {
        [$user] = $this->context();
        $this->actingAs($user)->getJson(route('collections.create', ['customer' => ['bad']]))->assertRedirect()->assertSessionHasErrors('customer');
    }

    public function test_invalid_customer_restored_from_old_form_input_is_validated(): void
    {
        [$user] = $this->context();
        $this->actingAs($user)->withSession(['_old_input' => ['customer_id' => ['bad']]])
            ->get(route('collections.create'))->assertRedirect()->assertSessionHasErrors('customer');
    }
    public function test_one_waived_invoice_does_not_change_the_collection_mode_of_another_invoice(): void
    {
        [$user,$customer,$method,$invoices] = $this->invoices();
        app(BillingService::class)->generateForMonth('2026-04');
        $other = Invoice::query()->whereDate('period_starts_on', '2026-04-01')->firstOrFail();
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
        app(ServiceWaiverService::class)->waive($customer, [$invoices->first()->id], 'amount', 5, 'Parcial', $user);
        $payment = app(PaymentCollectionService::class)->collect($customer, [$invoices->first()->id, $other->id], [], ['payment_method_id' => $method->id], $user);
        $this->assertSame('31.00', $payment->amount);
        $this->assertSame(6.0, app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31')['late_fees']);
    }

    public function test_fine_waiver_history_prevents_repricing_its_assembly(): void
    {
        [$user] = $this->context();
        $assembly = Assembly::query()->create(['assembly_type_id' => AssemblyType::query()->create(['name' => 'Ordinaria'])->id, 'held_on' => '2026-01-10', 'absence_fine' => 10, 'status' => 'HELD']);
        $bulk = app(BulkWaiverService::class);
        $bulk->apply(['token' => (string) Str::uuid(), 'rows' => $bulk->plan($bulk->eligible(['kind' => 'fines']), 'all', 0), 'mode' => 'all', 'value' => 0, 'reason' => 'Multa condonada'], $user);
        $this->expectException(ValidationException::class);
        $assembly->update(['absence_fine' => 5]);
    }

    public function test_untrusted_report_text_is_not_written_as_an_excel_formula(): void
    {
        [$user,$customer,,$invoices] = $this->invoices();
        $customer->update(['first_name' => '=2+2', 'last_name' => '']);
        app(ServiceWaiverService::class)->waive($customer, [$invoices->first()->id], 'amount', 2, '=1+1', $user);
        $response = $this->actingAs($user)->get(route('reports.download', ['report' => 'service-waivers', 'format' => 'xlsx', 'year' => 2026]));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'safe-report-');
        try {
            file_put_contents($path, $response->streamedContent());
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('I5')->getDataType());
            $this->assertSame('=1+1', $sheet->getCell('I5')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D5')->getDataType());
        } finally {
            unlink($path);
        }
    }
}
