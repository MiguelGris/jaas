<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function download(Request $request, string $report, string $format, ReportService $reports): StreamedResponse|
    \Illuminate\Http\Response
    {
        abort_unless(in_array($report, ['cash-flow', 'annual-balance', 'debtors', 'attendance', 'work-exemptions'], true), 404);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'assembly_id' => ['nullable', 'integer', 'exists:assemblies,id'],
        ]);
        $document = $reports->build($report, $data);

        return $format === 'xlsx'
            ? $this->excel($document)
            : $this->pdf($document);
    }

    /** @param array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>} $document */
    private function excel(array $document): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');
        $lastColumn = Coordinate::stringFromColumnIndex(count($document['headers']));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', $document['title']);
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', $document['subtitle']);
        $sheet->fromArray([$document['headers']], null, 'A4');
        $sheet->fromArray($document['rows'], null, 'A5');
        $summaryRow = max(6, 5 + count($document['rows']) + 2);

        foreach ($document['summary'] as $label => $value) {
            $sheet->setCellValue("A{$summaryRow}", $label);
            $sheet->setCellValue("B{$summaryRow}", $value);
            $summaryRow++;
        }

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0F172A');
        $sheet->getStyle("A1:{$lastColumn}2")->getFont()->setItalic(true)->getColor()->setRGB('475569');
        $sheet->getStyle("A4:{$lastColumn}4")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A4:{$lastColumn}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0369A1');
        $sheet->getStyle("A1:{$lastColumn}".max(4, 4 + count($document['rows'])))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->freezePane('A5');

        foreach ($document['headers'] as $index => $_header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setAutoSize(true);

            if (in_array($index, $document['currency_columns'], true)) {
                $sheet->getStyle("{$column}5:{$column}".(4 + max(1, count($document['rows']))))->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            }
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $document['filename'].'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** @param array{title: string, subtitle: string, filename: string, headers: list<string>, rows: list<list<string|int|float>>, summary: array<string, string|int|float>, currency_columns: list<int>} $document */
    private function pdf(array $document): \Illuminate\Http\Response
    {
        $options = new Options();
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
