<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyType;
use App\Models\Fine;
use App\Models\Role;
use App\Services\BillingService;
use App\Services\BulkWaiverService;
use App\Services\DebtService;
use App\Services\LateFeeWaiverService;
use App\Services\PaymentConceptService;
use App\Services\ReportService;
use App\Services\ServiceWaiverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\CreatesBillingContext;
use Tests\TestCase;

class AnnualWaiverReportTest extends TestCase
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

    private function annualData(): array
    {
        [$user,$customers] = $this->debts();
        $this->travelTo(Carbon::parse('2026-09-15 10:00:00'));
        $invoice = app(DebtService::class)->pendingForCustomer($customers->first())['invoices']->first();
        app(LateFeeWaiverService::class)->waive($customers->first(), [$invoice->id], 'amount', 3, 'Condonación individual de septiembre', $user);
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
        $service = app(BulkWaiverService::class);
        $campaign = $service->apply(['token' => (string) Str::uuid(), 'rows' => $service->plan($service->eligible(['kind' => 'both']), 'all', 0), 'mode' => 'all', 'value' => 0, 'reason' => 'Campaña de octubre'], $user);
        app(ServiceWaiverService::class)->waive($customers->first(), [$invoice->id], 'amount', 2, 'Servicio excluido del reporte', $user);
        $this->travelTo(Carbon::parse('2027-01-10 10:00:00'));
        app(LateFeeWaiverService::class)->waive($customers->first(), [$invoice->id], 'amount', 1, 'Condonación del año siguiente', $user);

        return [$user, $customers, $campaign];
    }

    public function test_annual_report_groups_actual_waiver_dates_and_counts_individual_and_campaign_details_once(): void
    {
        [, $customers] = $this->annualData();
        $reports = app(ReportService::class);
        $report = $reports->build('late-fee-fine-waivers-annual', ['year' => 2026]);
        $this->assertCount(5, $report['rows']);
        $this->assertSame(24.0, $report['summary']['Moras condonadas']);
        $this->assertSame(20.0, $report['summary']['Multas condonadas']);
        $this->assertSame(44.0, $report['summary']['Total condonado']);
        $this->assertSame(2, $report['summary']['Clientes']);
        $monthly = $report['intro_tables'][0]['rows'];
        $this->assertCount(12, $monthly);
        $this->assertSame(['enero', 0.0, 0.0, 0.0], $monthly[0]);
        $this->assertSame(['septiembre', 3.0, 0.0, 3.0], $monthly[8]);
        $this->assertSame(['octubre', 21.0, 20.0, 41.0], $monthly[9]);
        $this->assertSame('Individual', $report['rows'][0][9]);
        $this->assertSame(44.0, round((float) collect($report['rows'])->sum(6), 2));
        $this->assertSame('Detalle de condonaciones', $report['detail_title']);
        $next = $reports->build('late-fee-fine-waivers-annual', ['year' => 2027]);
        $this->assertCount(1, $next['rows']);
        $this->assertSame(1.0, $next['summary']['Total condonado']);
        $filtered = $reports->build('late-fee-fine-waivers-annual', ['year' => 2026, 'customer_id' => $customers->first()->id]);
        $this->assertCount(3, $filtered['rows']);
        $this->assertSame(22.0, $filtered['summary']['Total condonado']);
        $this->assertSame(0.0, app(PaymentConceptService::class)->totals('2026-01-01', '2026-12-31')['late_fees']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_empty_year_has_twelve_zero_months_and_no_operations(): void
    {
        $this->annualData();
        $report = app(ReportService::class)->build('late-fee-fine-waivers-annual', ['year' => 2025]);
        $this->assertSame([], $report['rows']);
        $this->assertCount(12, $report['intro_tables'][0]['rows']);
        $this->assertSame(0.0, $report['summary']['Total condonado']);
        $this->assertSame(0, $report['summary']['Clientes']);
    }

    public function test_yearly_waiver_report_exports_both_formats_with_monthly_currency_and_permission_checks(): void
    {
        [$user] = $this->annualData();
        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Condonaciones anuales de moras y multas');
        $params = ['report' => 'late-fee-fine-waivers-annual', 'format' => 'xlsx', 'year' => 2026];
        $url = route('reports.download', $params);
        $this->assertStringContainsString('condonaciones-anuales-de-moras-y-multas', $url);
        $excel = $this->get($url);
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'annual-waivers-');
        try {
            file_put_contents($path, $excel->streamedContent());
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('septiembre', $sheet->getCell('A14')->getValue());
            $this->assertEquals(3, $sheet->getCell('B14')->getValue());
            $this->assertEquals(41, $sheet->getCell('D15')->getValue());
            $this->assertStringContainsString('S/', $sheet->getStyle('B14')->getNumberFormat()->getFormatCode());
            $this->assertSame('Detalle de condonaciones', $sheet->getCell('A19')->getValue());
        } finally {
            unlink($path);
        }
        $params['format'] = 'pdf';
        $pdf = $this->get(route('reports.download', $params));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get(route('reports.download', ['report' => 'late-fee-fine-waivers-annual', 'format' => 'xlsx', 'year' => 1999]))->assertSessionHasErrors('year');
        $role = Role::query()->create(['name' => 'SIN_REPORTES']);
        $user->update(['role_id' => $role->id]);
        $this->actingAs($user->fresh())->get(route('reports.download', $params))->assertForbidden();
    }
}
