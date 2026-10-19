<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>
    @page { margin: 24px 24px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1e293b; }
    h1 { font-size: 16px; margin: 0 0 5px; }
    p { margin: 4px 0 10px; }
    .neighborhood { page-break-before: always; }
    .neighborhood:first-child { page-break-before: auto; }
    table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { padding: 6px 4px; background: #0369a1; color: white; text-align: left; font-size: 7px; }
    td { padding: 5px 4px; border-bottom: 1px solid #dbe3ed; vertical-align: top; overflow-wrap: break-word; }
    tbody tr:nth-child(even) { background: #f1f5f9; }
    .group-heading { background: #e0f2fe; color: #0f172a; font-size: 13px; padding: 9px 4px; }
    .summary { color: #475569; }
</style></head>
<body>
@php($widths = $report['pdf_widths'] ?? [8,8,10,19,8,21,9,6,6,5])
@php($rowLabel = $report['row_label'] ?? 'conexiones')
@forelse ($report['groups'] as $group)
    <div class="neighborhood">
        <h1>{{ config('jass.name') }} · {{ $report['title'] }}</h1>
        <p>{{ $report['subtitle'] }}</p>
        @if ($loop->first)<p class="summary">Total: {{ $report['register_total'] ?? count($report['rows']) }} {{ $rowLabel }} · {{ count($report['groups']) }} barrios</p>@endif
        <table>
            <colgroup>@foreach ($widths as $width)<col style="width:{{ $width }}%">@endforeach</colgroup>
            <thead>
                <tr><th colspan="{{ count($report['headers']) - 1 }}" class="group-heading">Barrio: {{ $group['name'] }} · {{ count($group['rows']) }} {{ $rowLabel }}</th></tr>
                <tr>@foreach (array_slice($report['headers'], 1) as $header)<th style="width: {{ $widths[$loop->index] }}%">{{ $header }}</th>@endforeach</tr>
            </thead>
            <tbody>@foreach ($group['rows'] as $row)<tr>@foreach ($row as $value)<td style="width: {{ $widths[$loop->index] }}%">{{ $value }}</td>@endforeach</tr>@endforeach</tbody>
        </table>
    </div>
@empty
    <h1>{{ $report['title'] }}</h1><p>{{ $report['empty_message'] ?? 'No hay conexiones registradas.' }}</p>
@endforelse
</body></html>
