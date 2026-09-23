<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 24px; }
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #0f172a; font-size: 18px; margin: 0 0 4px; }
        .subtitle { color: #64748b; margin: 0 0 18px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #0369a1; color: white; font-size: 8px; padding: 7px 5px; text-align: left; }
        td { border-bottom: 1px solid #dbe3ed; padding: 6px 5px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .number { text-align: right; white-space: nowrap; }
        .summary { margin-top: 18px; width: 45%; }
        .summary td { border: 0; padding: 4px 0; }
        .summary td:last-child { font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <p class="subtitle">{{ $report['subtitle'] }}</p>
    <table>
        <thead><tr>@foreach ($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach ($row as $index => $value)<td @class(['number' => in_array($index, $report['currency_columns'], true)])>@if(in_array($index, $report['currency_columns'], true))S/ {{ number_format((float) $value, 2) }}@else{{ $value }}@endif</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['headers']) }}">No hay registros para este reporte.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($report['summary'] !== [])
        <table class="summary"><tbody>@foreach ($report['summary'] as $label => $value)<tr><td>{{ $label }}</td><td>@if (str_contains(Str::lower($label), 'deuda') || str_contains(Str::lower($label), 'ingresos') || str_contains(Str::lower($label), 'egresos') || str_contains(Str::lower($label), 'saldo'))S/ {{ number_format((float) $value, 2) }}@else{{ $value }}@endif</td></tr>@endforeach</tbody></table>
    @endif
</body>
</html>
