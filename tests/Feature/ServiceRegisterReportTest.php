<?php

namespace Tests\Feature;

use App\Models\Neighborhood;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBillingContext;
use Tests\TestCase;

class ServiceRegisterReportTest extends TestCase
{
    use RefreshDatabase, CreatesBillingContext;

    public function test_register_groups_every_connection_by_neighborhood_and_exports_pdf(): void
    {
        [$user, $customers] = $this->context(3);
        $north = Neighborhood::query()->create(['name' => 'Norte', 'active' => true]);
        $customers->first()->properties()->update(['neighborhood_id' => $north->id]);

        $report = app(ReportService::class)->build('service-register');
        $this->assertSame('Barrio', $report['headers'][0]);
        $this->assertCount(3, $report['rows']);
        $this->assertSame(['Centro', 'Norte'], array_column($report['groups'], 'name'));
        $this->assertSame([2, 1], array_map(fn ($group) => count($group['rows']), $report['groups']));
        $html = view($report['pdf_view'], ['report' => $report])->render();
        $this->assertStringContainsString('Barrio: Centro', $html);
        $this->assertStringContainsString('Barrio: Norte', $html);
        foreach ($report['rows'] as $row) {
            $this->assertSame(1, substr_count($html, $row[1]));
        }
        $response = $this->actingAs($user)->get(route('reports.download', ['report' => 'service-register', 'format' => 'pdf']));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_customer_register_deduplicates_within_each_neighborhood_and_can_be_selected(): void
    {
        [$user, $customers] = $this->context(2);
        $property = $customers->first()->properties()->firstOrFail();
        $property->replicate(['property_code'])->save();
        $north = Neighborhood::query()->create(['name' => 'Norte', 'active' => true]);
        $other = $property->replicate(['property_code']);
        $other->neighborhood_id = $north->id;
        $other->save();
        \App\Models\Customer::query()->create(['first_name' => 'Sin', 'last_name' => 'Predio',
            'customer_status_id' => $customers->first()->customer_status_id, 'registered_on' => '2026-01-01']);

        $report = app(ReportService::class)->build('customer-register');
        $this->assertSame(3, $report['summary']['Clientes únicos']);
        $this->assertCount(4, $report['rows']);
        $this->assertSame(['Centro', 'Norte', 'Sin barrio'], array_column($report['groups'], 'name'));
        $first = collect($report['rows'])->first(fn ($row) => $row[0] === 'Centro' && $row[1] === $customers->first()->customer_code);
        $this->assertSame(2, $first[6]);
        $this->assertSame([2, 1, 1], array_map(fn ($group) => count($group['rows']), $report['groups']));
        $response = $this->actingAs($user)->get(route('reports.download', ['report' => 'service-register', 'format' => 'pdf', 'register_type' => 'customers']));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('padron-clientes', $response->headers->get('Content-Disposition'));
        $this->get(route('reports.download', ['report' => 'customer-register', 'format' => 'xlsx']))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get(route('reports.index'))->assertOk()->assertSee('Padrón a descargar')->assertSee('name="register_type"', false);
    }

    public function test_empty_register_can_be_exported(): void
    {
        [$user] = $this->context(0);
        $report = app(ReportService::class)->build('service-register');
        $this->assertSame([], $report['groups']);
        $this->assertStringContainsString('No hay conexiones registradas.', view($report['pdf_view'], ['report' => $report])->render());
        $this->actingAs($user)->get(route('reports.download', ['report' => 'service-register', 'format' => 'pdf']))->assertOk();
    }
}
