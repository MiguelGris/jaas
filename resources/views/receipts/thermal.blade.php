<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $payment->receipt_code }} · {{ config('jass.name') }}</title>
    @include('partials.brand-icons')
    <style>
        @page { size: auto; margin: 0; }
        :root { --paper-width: 58mm; --receipt-font: 10px; --heading-font: 14px; --total-font: 12px; --amount-width: 17mm; }
        :root[data-paper-width="80"] { --paper-width: 80mm; --receipt-font: 12px; --heading-font: 16px; --total-font: 14px; --amount-width: 21mm; }
        .print-settings { max-width: 420px; margin: 16px auto; padding: 16px; background: #fff; border-radius: 8px; line-height: 1.5; }
        .print-settings label { display: block; margin-bottom: 6px; font-weight: bold; }
        .print-settings select { width: 100%; padding: 10px; font: inherit; }
        .print-settings p { margin-bottom: 0; font-size: 13px; color: #475569; }
        * { box-sizing: border-box; }
        html, body { width: 100%; margin: 0; background: #f1f5f9; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
        .receipt { width: min(100%, var(--paper-width)); margin: 16px auto; font-size: var(--receipt-font); min-height: 80mm; padding: 3mm; background: #fff; line-height: 1.5; }
        .center { text-align: center; }
        .title { margin: 0; font-size: var(--heading-font); font-weight: 800; letter-spacing: .3px; }
        .muted { color: #475569; }
        .divider { margin: 2.5mm 0; border: 0; border-top: 1px dashed #64748b; }
        .data { display: flex; justify-content: space-between; gap: 2mm; margin: .8mm 0; }
        .data span:first-child { flex: 0 0 17mm; }
        .data span:last-child { min-width: 0; overflow-wrap: anywhere; text-align: right; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 1.5mm; }
        th { padding: 1.2mm 0; border-bottom: 1px solid #64748b; text-align: left; font-size: var(--receipt-font); }
        th:last-child, td:last-child { width: var(--amount-width); text-align: right; white-space: nowrap; }
        td { padding: 1.2mm 0; overflow-wrap: anywhere; vertical-align: top; }
        td:first-child { padding-right: 2mm; }
        .total { display: flex; justify-content: space-between; gap: 2mm; margin-top: 1.5mm; padding-top: 1.5mm; border-top: 1px solid #0f172a; font-size: var(--total-font); font-weight: 800; }
        .voided { margin: 2.5mm 0; border: 2px solid #be123c; padding: 1.5mm; color: #be123c; font-size: 12px; font-weight: 900; text-align: center; }
        .print { display: block; width: 100%; margin: 4mm auto 0; border: 0; border-radius: 5px; padding: 2.5mm; background: #0369a1; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        @media print {
            html, body { width: var(--paper-width); background: #fff; }
            .receipt { width: var(--paper-width); min-height: 0; margin: 0; font-size: var(--receipt-font); line-height: 1.3; }
            th { font-size: var(--receipt-font); }
            .total { font-size: var(--total-font); }
            tr { break-inside: avoid; }
            .total, header { break-inside: avoid; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @php
        $customerName = $customer?->display_name ?? 'Cliente no asociado';
    @endphp
    <section class="print-settings no-print" aria-label="Opciones de impresión">
        <label for="paper-width">Ancho del papel térmico</label>
        <select id="paper-width">
            <option value="58">58 mm · impresora compacta</option>
            <option value="80">80 mm · impresora estándar</option>
        </select>
        <p>El recibo se ajusta al ancho elegido. Se recordará tu selección en este navegador.</p>
        <p>En el diálogo de impresión, selecciona papel de <strong id="paper-width-help">58 mm</strong>, escala 100 %, márgenes: ninguno, y desactiva encabezados y pies de página. El tamaño de papel también debe coincidir en la configuración de tu impresora.</p>
        <button type="button" class="print" onclick="window.print()">Imprimir recibo</button>
    </section>
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
    </main>

<script>
    (() => {
        const select = document.getElementById('paper-width');
        const key = 'jass.receipt-paper-width';
        const apply = value => {
            const width = value === '80' ? '80' : '58';
            select.value = width;
            document.documentElement.dataset.paperWidth = width;
            document.getElementById('paper-width-help').textContent = width + ' mm';
        };
        try { apply(localStorage.getItem(key)); } catch { apply('58'); }
        select.addEventListener('change', () => {
            apply(select.value);
            try { localStorage.setItem(key, select.value); } catch { /* La selección actual funciona sin almacenamiento. */ }
        });
    })();
</script>
</body>
</html>
