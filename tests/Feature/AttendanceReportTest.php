<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\AssemblyType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Neighborhood;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_report_starts_with_general_and_neighborhood_summaries(): void
    {
        [$administrator, $assembly] = $this->context();
        $report = app(ReportService::class)->build('attendance', ['assembly_id' => $assembly->id]);

        $this->assertSame('Resumen general', $report['intro_tables'][0]['title']);
        $this->assertSame([[3, 1, 2, '33.33 %']], $report['intro_tables'][0]['rows']);
        $this->assertSame('Resumen por barrio', $report['intro_tables'][1]['title']);
        $this->assertSame([
            ['Barrio Norte', 1, 1, 0, '100.00 %'],
            ['Barrio Sur', 1, 0, 1, '0.00 %'],
            ['Sin barrio', 1, 0, 1, '0.00 %'],
        ], $report['intro_tables'][1]['rows']);
        $this->assertSame('Barrio', $report['headers'][3]);

        $html = view('reports.pdf', ['report' => $report])->render();
        $this->assertStringContainsString('Resumen general', $html);
        $this->assertStringContainsString('Resumen por barrio', $html);
        $this->assertStringContainsString('Detalle de asistentes e inasistentes', $html);
        $this->assertLessThan(strpos($html, 'Resumen por barrio'), strpos($html, 'Resumen general'));
        $this->assertLessThan(strpos($html, 'Detalle de asistentes e inasistentes'), strpos($html, 'Resumen por barrio'));

        $excelResponse = $this->actingAs($administrator)->get(route('reports.download', [
            'report' => 'attendance',
            'format' => 'xlsx',
            'assembly_id' => $assembly->id,
        ]));
        $excelResponse->assertOk()->assertDownload('asistencia-'.$assembly->assembly_code.'.xlsx');

        $temporaryFile = tempnam(sys_get_temp_dir(), 'jass-attendance-report-');
        $this->assertNotFalse($temporaryFile);

        try {
            file_put_contents($temporaryFile, $excelResponse->streamedContent());
            $sheet = IOFactory::createReader('Xlsx')->load($temporaryFile)->getActiveSheet();
            $firstColumn = [];

            for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
                $firstColumn[] = $sheet->getCell("A{$row}")->getValue();
            }

            $this->assertContains('Resumen general', $firstColumn);
            $this->assertContains('Resumen por barrio', $firstColumn);
            $this->assertContains('Detalle de asistentes e inasistentes', $firstColumn);
            $this->assertLessThan(array_search('Resumen por barrio', $firstColumn, true), array_search('Resumen general', $firstColumn, true));
            $this->assertLessThan(array_search('Detalle de asistentes e inasistentes', $firstColumn, true), array_search('Resumen por barrio', $firstColumn, true));
        } finally {
            if (is_string($temporaryFile) && file_exists($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        $this->actingAs($administrator)
            ->get(route('reports.download', [
                'report' => 'attendance',
                'format' => 'pdf',
                'assembly_id' => $assembly->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /** @return array{User, Assembly} */
    private function context(): array
    {
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-attendance-report@example.test',
            'password' => 'Password123',
            'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
        $status = CustomerStatus::query()->create(['name' => 'ACTIVO']);
        $north = Neighborhood::query()->create(['name' => 'Barrio Norte']);
        $south = Neighborhood::query()->create(['name' => 'Barrio Sur']);
        $customers = collect([
            ['first_name' => 'Ana', 'last_name' => 'Norte', 'national_id' => '11111111'],
            ['first_name' => 'Bruno', 'last_name' => 'Sur', 'national_id' => '22222222'],
            ['first_name' => 'Carla', 'last_name' => 'Sin Barrio', 'national_id' => '33333333'],
        ])->map(fn (array $data): Customer => Customer::query()->create($data + [
            'customer_status_id' => $status->id,
            'registered_on' => '2026-09-01',
        ]));

        Property::query()->create([
            'customer_id' => $customers[0]->id,
            'neighborhood_id' => $north->id,
            'address' => 'Jirón Norte 100',
            'active' => true,
        ]);
        Property::query()->create([
            'customer_id' => $customers[1]->id,
            'neighborhood_id' => $south->id,
            'address' => 'Jirón Sur 200',
            'active' => true,
        ]);

        $assembly = Assembly::query()->create([
            'assembly_type_id' => AssemblyType::query()->create(['name' => 'Asamblea ordinaria'])->id,
            'held_on' => '2026-09-30',
            'held_at' => '18:00',
            'place' => 'Local comunal',
            'absence_fine' => 10,
            'status' => 'SCHEDULED',
        ]);
        AssemblyAttendance::query()
            ->where('assembly_id', $assembly->id)
            ->where('customer_id', $customers[0]->id)
            ->firstOrFail()
            ->update(['attended' => true]);

        return [$administrator, $assembly];
    }
}
