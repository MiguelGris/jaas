<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $payment->receipt_code }} · {{ config('jass.name') }}</title>
    @include('partials.brand-icons')
    <style>
        @page { size: 58mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { width: 100%; margin: 0; background: #f1f5f9; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
        .receipt { width: min(100%, 360px); margin: 16px auto; font-size: 14px; min-height: 80mm; padding: 3mm; background: #fff; line-height: 1.5; }
        .center { text-align: center; }
        .title { margin: 0; font-size: 14px; font-weight: 800; letter-spacing: .3px; }
        .muted { color: #475569; }
        .divider { margin: 2.5mm 0; border: 0; border-top: 1px dashed #64748b; }
        .data { display: flex; justify-content: space-between; gap: 2mm; margin: .8mm 0; }
        .data span:first-child { flex: 0 0 17mm; }
        .data span:last-child { min-width: 0; overflow-wrap: anywhere; text-align: right; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 1.5mm; }
        th { padding: 1.2mm 0; border-bottom: 1px solid #64748b; text-align: left; font-size: 12px; }
        th:last-child, td:last-child { width: 17mm; text-align: right; }
        td { padding: 1.2mm 0; overflow-wrap: anywhere; vertical-align: top; }
        td:first-child { padding-right: 2mm; }
        .total { display: flex; justify-content: space-between; gap: 2mm; margin-top: 1.5mm; padding-top: 1.5mm; border-top: 1px solid #0f172a; font-size: 16px; font-weight: 800; }
        .voided { margin: 2.5mm 0; border: 2px solid #be123c; padding: 1.5mm; color: #be123c; font-size: 12px; font-weight: 900; text-align: center; }
        .print { display: block; width: 100%; margin: 4mm auto 0; border: 0; border-radius: 5px; padding: 2.5mm; background: #0369a1; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        @media print {
            html, body { width: 58mm; background: #fff; }
            .receipt { width: 58mm; min-height: 0; margin: 0; font-size: 9px; line-height: 1.3; }
            th { font-size: 8px; }
            .total { font-size: 11px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @php
        $customerName = $customer?->display_name ?? 'Cliente no asociado';
    @endphp
    <main class="receipt">
        <header class="center">
            <img src="{{ asset('brand/symbol.png') }}" alt="" width="40" height="40" style="display:block;margin:0 auto;filter:grayscale(1)">
            <p class="title">{{ config('jass.name') }}</p>
            <p class="muted">{{ config('jass.full_name') }}</p>
            <p><strong>{{ $payment->status === App\Models\Payment::STATUS_VOIDED ? 'RECIBO ANULADO' : 'RECIBO DE PAGO' }}</strong><br>{{ $payment->receipt_code }}</p>
        </header>

        @if ($payment->status === App\Models\Payment::STATUS_VOIDED)
            <div class="voided">ANULADO</div>
            <p class="muted"><strong>Motivo:</strong> {{ $payment->void_reason }}<br><strong>Anulado:</strong> {{ $payment->voided_at?->format('d/m/Y H:i') }} por {{ $payment->voidedBy?->name ?? 'Sistema' }}</p>
        @endif

        <hr class="divider">
        <div class="data"><span>Fecha</span><span>{{ $payment->paid_at?->format('d/m/Y H:i') }}</span></div>
        <div class="data"><span>Cliente</span><span>{{ $customerName }}</span></div>
        @if ($customer?->national_id)<div class="data"><span>{{ $customer->document_label }}</span><span>{{ $customer->national_id }}</span></div>@endif
        <div class="data"><span>Medio de pago</span><span>{{ App\Support\CatalogLabel::value($payment->paymentMethod?->name ?? '—') }}</span></div>
        @if ($payment->operation_number)<div class="data"><span>Operación</span><span>{{ $payment->operation_number }}</span></div>@endif

        @if ($payment->external_reference)<p class="muted">Referencia de transferencia: {{ $payment->external_reference }}</p>@endif
        <hr class="divider">
        <table>
            <thead><tr><th>Concepto</th><th>Importe</th></tr></thead>
            <tbody>
                @foreach ($paymentConcepts as $concept)
                    <tr><td><strong>{{ $concept['category'] }}</strong> {{ $concept['concept'] }}<br><span class="muted">{{ $concept['detail'] }}</span></td><td>S/ {{ number_format($concept['amount'], 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="total"><span>TOTAL PAGADO</span><span>S/ {{ number_format((float) $payment->amount, 2) }}</span></div>
        @if ($payment->notes)<p class="muted">Obs.: {{ $payment->notes }}</p>@endif
        <hr class="divider">
        <p class="center muted">Atendido por: {{ $payment->user ? trim($payment->user->name.' '.$payment->user->last_name) : 'Sistema' }}<br>Conserve este recibo como constancia de pago.</p>
        <button type="button" class="print no-print" onclick="window.print()">Imprimir recibo</button>
        <p class="center muted no-print">En el diálogo de impresión, seleccione papel de 58 mm, escala 100 % y márgenes: ninguno.</p>
    </main>

</body>
</html>
