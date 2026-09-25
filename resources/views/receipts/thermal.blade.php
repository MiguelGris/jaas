<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $payment->receipt_code }}</title>
    <style>
        @page { size: 70mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { width: 70mm; margin: 0; background: #f1f5f9; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
        .receipt { width: 70mm; min-height: 100mm; padding: 5mm; background: #fff; font-size: 10px; line-height: 1.35; }
        .center { text-align: center; }
        .title { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: .4px; }
        .muted { color: #475569; }
        .divider { margin: 3mm 0; border: 0; border-top: 1px dashed #64748b; }
        .data { display: flex; justify-content: space-between; gap: 3mm; margin: 1mm 0; }
        .data span:last-child { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        th { padding: 1.5mm 0; border-bottom: 1px solid #64748b; text-align: left; font-size: 9px; }
        th:last-child, td:last-child { text-align: right; }
        td { padding: 1.5mm 0; vertical-align: top; }
        .total { display: flex; justify-content: space-between; margin-top: 2mm; padding-top: 2mm; border-top: 1px solid #0f172a; font-size: 13px; font-weight: 800; }
        .voided { margin: 3mm 0; border: 2px solid #be123c; padding: 2mm; color: #be123c; font-size: 14px; font-weight: 900; text-align: center; }
        .print { display: block; width: 100%; margin: 5mm auto 0; border: 0; border-radius: 5px; padding: 3mm; background: #0369a1; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        @media print {
            html, body { width: 70mm; background: #fff; }
            .receipt { min-height: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @php
        $customerName = $customer ? trim($customer->first_name.' '.$customer->last_name) : 'Cliente no asociado';
    @endphp
    <main class="receipt">
        <header class="center">
            <p class="title">JASS</p>
            <p class="muted">Administración del servicio de agua</p>
            <p><strong>{{ $payment->status === App\Models\Payment::STATUS_VOIDED ? 'RECIBO ANULADO' : 'RECIBO DE PAGO' }}</strong><br>{{ $payment->receipt_code }}</p>
        </header>

        @if ($payment->status === App\Models\Payment::STATUS_VOIDED)
            <div class="voided">ANULADO</div>
            <p class="muted"><strong>Motivo:</strong> {{ $payment->void_reason }}<br><strong>Anulado:</strong> {{ $payment->voided_at?->format('d/m/Y H:i') }} por {{ $payment->voidedBy?->name ?? 'Sistema' }}</p>
        @endif

        <hr class="divider">
        <div class="data"><span>Fecha</span><span>{{ $payment->paid_at?->format('d/m/Y H:i') }}</span></div>
        <div class="data"><span>Cliente</span><span>{{ $customerName }}</span></div>
        @if ($customer?->national_id)<div class="data"><span>DNI</span><span>{{ $customer->national_id }}</span></div>@endif
        <div class="data"><span>Medio de pago</span><span>{{ App\Support\CatalogLabel::value($payment->paymentMethod?->name ?? '—') }}</span></div>
        @if ($payment->operation_number)<div class="data"><span>Operación</span><span>{{ $payment->operation_number }}</span></div>@endif

        <hr class="divider">
        <table>
            <thead><tr><th>Concepto</th><th>Importe</th></tr></thead>
            <tbody>
                @forelse ($payment->allocations as $allocation)
                    @if ($allocation->invoice)
                        <tr><td>Cuota {{ $allocation->invoice->invoice_code }}<br><span class="muted">{{ $allocation->invoice->period_starts_on?->format('m/Y') }} - {{ $allocation->invoice->period_ends_on?->format('m/Y') }}</span></td><td>S/ {{ number_format((float) $allocation->amount, 2) }}</td></tr>
                    @elseif ($allocation->fine)
                        <tr><td>Multa {{ $allocation->fine->fine_code }}<br><span class="muted">{{ $allocation->fine->reason ?: 'Asamblea' }}</span></td><td>S/ {{ number_format((float) $allocation->amount, 2) }}</td></tr>
                    @endif
                @empty
                    <tr><td>Pago registrado</td><td>S/ {{ number_format((float) $payment->amount, 2) }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="total"><span>TOTAL PAGADO</span><span>S/ {{ number_format((float) $payment->amount, 2) }}</span></div>
        @if ($payment->notes)<p class="muted">Obs.: {{ $payment->notes }}</p>@endif
        <hr class="divider">
        <p class="center muted">Atendido por: {{ $payment->user ? trim($payment->user->name.' '.$payment->user->last_name) : 'Sistema' }}<br>Conserve este recibo como constancia de pago.</p>
        <button type="button" class="print no-print" onclick="window.print()">Imprimir recibo</button>
        <p class="center muted no-print">En el diálogo de impresión, seleccione papel de 70 mm y márgenes: ninguno.</p>
    </main>
    <script>
        window.addEventListener('load', () => window.setTimeout(() => window.print(), 150));
    </script>
</body>
</html>
