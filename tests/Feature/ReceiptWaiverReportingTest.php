<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Services\BillingService;
use App\Services\PaymentCollectionService;
use App\Services\PaymentConceptService;
use App\Services\ReportService;
use App\Services\ServiceWaiverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\CreatesBillingContext;
use Tests\TestCase;

class ReceiptWaiverReportingTest extends TestCase
{
    use CreatesBillingContext;
    use RefreshDatabase;

    private function receipts(): array
    {
        [$user,$customers,,$method] = $this->context(2);
        foreach (['2026-01', '2026-02', '2026-03'] as $month) {
            app(BillingService::class)->generateForMonth($month);
        }
        $invoices = Invoice::query()->whereHas('connection.property', fn ($q) => $q->where('customer_id', $customers->first()->id))->orderBy('period_starts_on')->get();
        $this->travelTo(Carbon::parse('2026-09-15 10:00:00'));
        $waiver = app(ServiceWaiverService::class)->waive($customers->first(), [$invoices[0]->id], 'all', 0, 'Servicio de septiembre', $user)[0];
        $this->travelTo(Carbon::parse('2026-10-10 10:00:00'));
        $payment = app(PaymentCollectionService::class)->collect($customers->first(), [$invoices[1]->id, $invoices[2]->id], [], ['payment_method_id' => $method->id, 'separate_late_fees' => true], $user);
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
        $otherInvoice = Invoice::query()->whereHas('connection.property', fn ($q) => $q->where('customer_id', $customers->last()->id))->orderBy('id')->firstOrFail();
        $otherWaiver = app(ServiceWaiverService::class)->waive($customers->last(), [$otherInvoice->id], 'amount', 5, 'Servicio parcial de octubre', $user)[0];

        return [$user, $customers, $method, $waiver, $payment, $otherWaiver];
    }

    public function test_unified_receipts_keep_payment_and_waiver_identity_filters_and_print_links(): void
    {
        [$user,$customers,,$waiver,$payment,$otherWaiver] = $this->receipts();
        $this->assertSame($waiver->id, $payment->id);
        $this->actingAs($user)->get(route('resources.index', ['resource' => 'payments']))->assertOk()->assertSee($waiver->receipt_code)->assertSee($payment->receipt_code)->assertSee($otherWaiver->receipt_code)
            ->assertSee(route('service-waiver-receipts.show', $waiver), false)->assertSee(route('receipts.thermal', $payment), false)->assertViewHas('records', fn ($rows) => $rows->total() === 3);
        $this->get(route('resources.index', ['resource' => 'payments', 'kind' => 'payments']))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->kind === 'payment');
        $this->get(route('resources.index', ['resource' => 'payments', 'kind' => 'condonations', 'q' => $customers->first()->customer_code]))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->receipt_code === $waiver->receipt_code && $rows->first()->amount === 0.0 && ! $rows->first()->can_annul);
        $this->get(route('resources.index', ['resource' => 'payments', 'q' => $waiver->receipt_code]))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->print_url === route('service-waiver-receipts.show', $waiver));
        $this->get(route('resources.index', ['resource' => 'payments', 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->receipt_code === $waiver->receipt_code);
        $this->get(route('service-waiver-receipts.show', $waiver))->assertOk()->assertSee('DEUDA CONDONADA');
    }

    public function test_union_pagination_counts_both_types_without_fetching_two_separate_pages(): void
    {
        [$user,$customers,$method] = $this->receipts();
        for ($i = 0; $i < 23; $i++) {
            Payment::query()->create(['customer_id' => $customers->first()->id, 'paid_at' => '2026-10-20 10:00:00', 'amount' => 1, 'payment_method_id' => $method->id, 'source' => 'COUNTER', 'operation_number' => 'OP26-UNION-'.$i, 'status' => 'ACTIVE']);
        }
        $url = route('resources.index', ['resource' => 'payments', 'per_page' => 25]);
        $this->actingAs($user)->get($url)->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 26 && $rows->count() === 25);
        $this->get($url.'&page=2')->assertOk()->assertViewHas('records', fn ($rows) => $rows->count() === 1 && $rows->first()->kind === 'service_waiver');
        foreach ([25, 50, 100, 200] as $size) {
            $this->get(route('resources.index', ['resource' => 'payments', 'per_page' => $size]))->assertOk()->assertViewHas('records', fn ($rows) => $rows->perPage() === $size);
        }
    }

    public function test_reports_include_condoned_service_separately_and_preserve_cash_totals(): void
    {
        [$user,$customers,,$waiver,$payment,$otherWaiver] = $this->receipts();
        $reports = app(ReportService::class);
        $monthly = $reports->build('payment-concepts-monthly', ['month' => '2026-09']);
        $this->assertSame(15.0, $monthly['summary']['Servicio condonado (sin ingreso)']);
        $this->assertSame(0.0, $monthly['summary']['Recaudación por pagos']);
        $annual = $reports->build('payment-concepts-annual', ['year' => 2026]);
        $this->assertSame(20.0, $annual['summary']['Servicio condonado (sin ingreso)']);
        $this->assertSame(30.0, $annual['summary']['Servicios']);
        $this->assertSame(30.0, $annual['summary']['Saldo anual']);
        $this->assertSame(15.0, $annual['rows'][8][7]);
        $this->assertSame(5.0, $annual['rows'][9][7]);
        $history = $reports->build('customer-payment-history', ['customer_id' => $customers->first()->id, 'period_type' => 'year', 'year' => 2026]);
        $this->assertCount(2, $history['rows']);
        $this->assertSame($waiver->receipt_code, $history['rows'][0][1]);
        $this->assertSame(0.0, $history['rows'][0][8]);
        $this->assertSame(15.0, $history['rows'][0][10]);
        $this->assertSame($payment->receipt_code, $history['rows'][1][1]);
        $this->assertSame(30.0, $history['summary']['Total válido']);
        $range = $reports->build('customer-payment-history', ['customer_id' => $customers->first()->id, 'period_type' => 'range', 'from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertCount(1, $range['rows']);
        $this->assertSame(0.0, $range['summary']['Servicio condonado (sin ingreso)']);
        $details = $reports->build('service-waivers', ['year' => 2026]);
        $this->assertCount(2, $details['rows']);
        $this->assertSame(20.0, $details['summary']['Servicio condonado (sin ingreso)']);
        $this->assertSame('01/2026', $details['rows'][0][4]);
        $this->assertSame('Servicio de septiembre', $details['rows'][0][8]);
        $filtered = $reports->build('service-waivers', ['year' => 2026, 'customer_id' => $customers->last()->id]);
        $this->assertCount(1, $filtered['rows']);
        $this->assertSame($otherWaiver->receipt_code, $filtered['rows'][0][1]);
        $this->assertCount(0, $reports->build('service-waivers', ['year' => 2025])['rows']);
        $cash = app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31');
        $this->assertSame(30.0, $cash['services']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_condonation_report_exports_excel_and_pdf_and_receipts_require_read_permissions(): void
    {
        [$user,,$method,$waiver] = $this->receipts();
        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Condonaciones de servicio');
        $excel = $this->get(route('reports.download', ['report' => 'service-waivers', 'format' => 'xlsx', 'year' => 2026]));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'waiver-report-');
        try {
            file_put_contents($path, $excel->streamedContent());
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame($waiver->receipt_code, $sheet->getCell('B5')->getValue());
            $this->assertEquals(15, $sheet->getCell('G5')->getValue());
        } finally {
            unlink($path);
        }
        $pdf = $this->get(route('reports.download', ['report' => 'service-waivers', 'format' => 'pdf', 'year' => 2026]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $role = Role::query()->create(['name' => 'SIN_REPORTES']);
        $user->update(['role_id' => $role->id]);
        $this->actingAs($user->fresh())->get(route('resources.index', ['resource' => 'payments']))->assertForbidden();
        $this->get(route('reports.download', ['report' => 'service-waivers', 'format' => 'pdf', 'year' => 2026]))->assertForbidden();
    }
}
