<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\Customer;
use App\Services\ReportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function index(ReportService $reports): View
    {
        $assemblies = Assembly::query()->orderByDesc('held_on')->limit(100)->get();
        $reportCustomers = Customer::query()
            ->orderBy('customer_type')
            ->orderBy('business_name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'customer_code', 'customer_type', 'national_id', 'first_name', 'last_name', 'business_name']);

        return view('reports.index', compact('assemblies', 'reportCustomers', 'reports'));
    }

    public function download(Request $request, string $report, string $format, ReportService $reports): StreamedResponse|
    Response
    {
        abort_unless(in_array($report, [
            'cash-flow',
            'annual-balance',
            'payment-concepts-monthly',
            'payment-concepts-annual',
            'customer-payment-history',
            'debtors',
            'debt-aging',
            'payment-methods',
            'service-register',
            'attendance',
            'work-exemptions',
        ], true), 404);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $isPaymentHistory = $report === 'customer-payment-history';
        $periodType = $request->input('period_type');
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'period_type' => [$isPaymentHistory ? 'required' : 'nullable', 'in:all,year,range'],
            'year' => [$isPaymentHistory && $periodType === 'year' ? 'required' : 'nullable', 'integer', 'between:2000,2100'],
            'assembly_id' => ['nullable', 'integer', 'exists:assemblies,id'],
            'customer_id' => [$isPaymentHistory ? 'required' : 'nullable', 'integer', 'exists:customers,id'],
            'from' => [$isPaymentHistory && $periodType === 'range' ? 'required' : 'nullable', 'date'],
            'to' => [$isPaymentHistory && $periodType === 'range' ? 'required' : 'nullable', 'date', 'after_or_equal:from'],
        ]);
        $document = $reports->build($report, $data);

        return $format === 'xlsx'
            ? $this->excel($document)
            : $this->pdf($document);
    }

    /** @param array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>, intro_tables?: list<array{title: string, headers: list<string>, rows: list<list<string|int|float>>}>} $document */
    private function excel(array $document): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');
        $lastColumn = Coordinate::stringFromColumnIndex(count($document['headers']));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', $document['title']);
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', $document['subtitle']);
        $currentRow = 4;

        // Algunos reportes incluyen tablas de resumen antes del detalle. La
        // fila se calcula dinámicamente para que Excel conserve ese mismo orden.
        foreach ($document['intro_tables'] ?? [] as $table) {
            $titleRow = $currentRow;
            $sheet->mergeCells("A{$titleRow}:{$lastColumn}{$titleRow}");
            $sheet->setCellValue("A{$titleRow}", $table['title']);
            $sheet->getStyle("A{$titleRow}:{$lastColumn}{$titleRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('0F172A');
            $sheet->getStyle("A{$titleRow}:{$lastColumn}{$titleRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F2FE');

            $headerRow = ++$currentRow;
            $sheet->fromArray([$table['headers']], null, "A{$headerRow}");
            $sheet->getStyle("A{$headerRow}:".Coordinate::stringFromColumnIndex(count($table['headers'])).$headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A{$headerRow}:".Coordinate::stringFromColumnIndex(count($table['headers'])).$headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0284C7');

            if ($table['rows'] !== []) {
                $sheet->fromArray($table['rows'], null, 'A'.($headerRow + 1));
            }

            $currentRow = $headerRow + max(1, count($table['rows'])) + 2;
        }

        if (($document['intro_tables'] ?? []) !== []) {
            $sheet->mergeCells("A{$currentRow}:{$lastColumn}{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'Detalle de asistentes e inasistentes');
            $sheet->getStyle("A{$currentRow}:{$lastColumn}{$currentRow}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('0F172A');
            $currentRow++;
        }

        $mainHeaderRow = $currentRow;
        $mainDataRow = $mainHeaderRow + 1;
        $mainDataLastRow = $mainDataRow + max(1, count($document['rows'])) - 1;
        $sheet->fromArray([$document['headers']], null, "A{$mainHeaderRow}");

        if ($document['rows'] !== []) {
            $sheet->fromArray($document['rows'], null, "A{$mainDataRow}");
        }

        $summaryRow = $mainDataRow + count($document['rows']) + 2;

        foreach ($document['summary'] as $label => $value) {
            $sheet->setCellValue("A{$summaryRow}", $label);
            $sheet->setCellValue("B{$summaryRow}", $value);
            $summaryRow++;
        }

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0F172A');
        $sheet->getStyle("A1:{$lastColumn}2")->getFont()->setItalic(true)->getColor()->setRGB('475569');
        $sheet->getStyle("A{$mainHeaderRow}:{$lastColumn}{$mainHeaderRow}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$mainHeaderRow}:{$lastColumn}{$mainHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0369A1');
        $sheet->getStyle("A1:{$lastColumn}{$mainDataLastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        // Al congelar justo debajo del encabezado principal, el usuario puede
        // recorrer listados extensos sin perder los nombres de las columnas.
        $sheet->freezePane("A{$mainDataRow}");

        foreach ($document['headers'] as $index => $_header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setAutoSize(true);

            if (in_array($index, $document['currency_columns'], true)) {
                $sheet->getStyle("{$column}{$mainDataRow}:{$column}{$mainDataLastRow}")->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            }
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $document['filename'].'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** @param array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>, intro_tables?: list<array{title: string, headers: list<string>, rows: list<list<string|int|float>>}>} $document */
    private function pdf(array $document): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.pdf', ['report' => $document])->render());
        $dompdf->setPaper('A4', count($document['headers']) > 5 ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document['filename'].'.pdf"',
        ]);
    }
}
