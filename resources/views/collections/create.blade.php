@extends('layouts.app')

@section('title', 'Cobrar cuotas')
@section('heading', 'Cobrar cuotas')

@section('content')
    <div class="mb-6">
        <p class="text-sm font-medium uppercase tracking-widest text-sky-700">Cobranza</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Registrar pago</h2>
        <p class="mt-1 text-sm text-slate-500">Busca por DNI, RUC, nombre o razón social. Selecciona las cuotas y multas que se cancelarán en un único recibo.</p>
    </div>

    @if (session('receipt_id'))
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="font-semibold text-sky-950">Recibo listo para imprimir</p><p class="mt-0.5 text-sm text-sky-800">Se abrirá en formato térmico de 58 mm.</p></div>
            <a href="{{ route('receipts.thermal', ['payment' => session('receipt_id')]) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-800">Imprimir recibo</a>
        </div>
    @endif

    <form method="GET" action="{{ route('collections.create') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <label for="collection-customer-search" class="block text-sm font-semibold text-slate-700">Cliente</label>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="min-w-0 flex-1">
                @include('searchable-select', [
                    'name' => 'customer',
                    'searchId' => 'collection-customer-search',
                    'options' => $customers->mapWithKeys(fn ($item) => [
                        $item->id => $item->customer_code.' · '.$item->document_label.' '.($item->national_id ?: 'sin registrar').' · '.$item->display_name,
                    ])->all(),
                    'value' => $customer?->id,
                    'required' => true,
                    'placeholder' => 'DNI, código, nombres o apellidos; RUC o razón social',
                ])
                <p class="mt-1 text-xs text-slate-500">Selecciona el resultado correcto verificando su código y documento.</p>
            </div>
            <button class="rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-900">Consultar deudas</button>
        </div>
    </form>

    @if ($customer)
        <form method="POST" action="{{ route('collections.store') }}" id="collection-form" data-warn-unsaved class="mt-6 space-y-5">
            @csrf
            @if ($errors->any())
                <div id="collection-errors" role="alert" tabindex="-1" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                    <p class="font-semibold">El pago no se registró. Corrige lo siguiente:</p>
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    <p class="mt-2 text-sm">Tus selecciones y observaciones se conservaron.</p>
                </div>
            @endif
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium uppercase tracking-widest text-sky-700">Cliente seleccionado</p><h3 class="mt-1 text-xl font-bold text-slate-900">{{ $customer->display_name }}</h3><p class="text-sm text-slate-500">{{ $customer->document_label }} {{ $customer->national_id ?: 'sin registrar' }} · {{ $customer->customer_code }}</p></div>

            @if ($invoices->isEmpty() && $fines->isEmpty())
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center text-emerald-900"><p class="font-bold">No tiene cargos pendientes.</p></div>
            @else
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-select-all class="rounded-lg border bg-white px-4 py-3 font-semibold text-sky-700">Seleccionar todos los cargos</button>
                    <button type="button" data-clear-charges class="rounded-lg border bg-white px-4 py-3 font-semibold">Limpiar selección</button>
                    @foreach ($invoices->groupBy(fn ($invoice) => $invoice->cycleKey()) as $cycle => $cycleInvoices)
                        @php
                            $first = $cycleInvoices->first();
                        @endphp
                        <button type="button" data-select-cycle="{{ $cycle }}" class="rounded-lg border bg-white px-4 py-3 font-semibold text-sky-700">Seleccionar {{ $first->cycleLabel() }} · {{ $first->connection->supply_code }}</button>
                    @endforeach
                </div>
                <p class="text-sm text-slate-600">Cada botón añade un ciclo a tu selección; no desmarca los anteriores. Los botones seleccionan solo las cuotas disponibles. Revisa los meses incluidos; las multas se seleccionan aparte. La mora del ciclo aparece una sola vez, en su cuota pendiente más antigua. Los importes de mora ya registrados se descuentan del total del ciclo.</p>
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-900">Cuotas mensuales</h3><p class="mt-1 text-sm text-slate-500">Puedes pagar un mes, varios meses o adelantar todas las cuotas disponibles.</p></div>
                    <div class="divide-y divide-slate-100">@forelse ($invoices as $invoice)<label class="flex cursor-pointer items-center gap-4 px-5 py-4 hover:bg-slate-50"><input type="checkbox" name="invoices[]" value="{{ $invoice->id }}" @checked(in_array((string) $invoice->id, array_map('strval', old('invoices', [])), true)) data-label="{{ $invoice->period_starts_on->translatedFormat('F Y') }} · {{ $invoice->connection->supply_code }}" data-cycle="{{ $invoice->cycleKey() }}" data-amount="{{ $invoice->balance_due }}" class="charge-checkbox size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"><span class="min-w-0 flex-1"><span class="block font-semibold text-slate-800">{{ $invoice->period_starts_on->translatedFormat('F Y') }} · {{ $invoice->connection->supply_code }}</span><span class="block text-sm text-slate-500">Cuota S/ {{ number_format($invoice->service_amount, 2) }} @if($invoice->calculated_late_fee > 0) · Mora del ciclo {{ $invoice->cycleLabel() }} ({{ $invoice->late_fee_months }} {{ $invoice->late_fee_months === 1 ? 'mes' : 'meses' }}): S/ {{ number_format($invoice->calculated_late_fee, 2) }} @endif</span></span><span class="font-bold text-slate-900">S/ {{ number_format($invoice->balance_due, 2) }}</span></label>@empty <p class="px-5 py-5 text-sm text-slate-500">No hay cuotas pendientes.</p>@endforelse</div>
                </div>

                @if ($fines->isNotEmpty())
                <div class="rounded-xl border border-rose-200 bg-white shadow-sm"><div class="border-b border-rose-100 bg-rose-50 px-5 py-4"><h3 class="font-bold text-rose-900">Multas pendientes</h3></div><div class="divide-y divide-slate-100">@foreach ($fines as $fine)<label class="flex cursor-pointer items-center gap-4 px-5 py-4 hover:bg-slate-50"><input type="checkbox" name="fines[]" value="{{ $fine->id }}" @checked(in_array((string) $fine->id, array_map('strval', old('fines', [])), true)) data-label="Multa: {{ $fine->reason }}" data-amount="{{ $fine->balance_due }}" class="charge-checkbox size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"><span class="min-w-0 flex-1"><span class="block font-semibold text-slate-800">{{ $fine->reason ?? 'Multa' }}</span><span class="block text-sm text-slate-500">Generada el {{ $fine->generated_on->format('d/m/Y') }}</span></span><span class="font-bold text-slate-900">S/ {{ number_format($fine->balance_due, 2) }}</span></label>@endforeach</div></div>
                @endif

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="grid gap-5 md:grid-cols-2"><div><label for="payment_method_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Medio de pago</label><select id="payment_method_id" name="payment_method_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm" required><option value="">Selecciona una opción</option>@foreach($paymentMethods as $method)<option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ App\Support\CatalogLabel::value($method->name) }}</option>@endforeach</select></div><div class="rounded-lg border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900"><span class="block font-semibold">Número de operación</span><span class="text-sky-700">Se generará automáticamente al registrar el pago.</span></div><div class="md:col-span-2"><label for="external_reference" class="block font-semibold">Referencia de la transferencia (opcional)</label><input id="external_reference" name="external_reference" maxlength="100" value="{{ old('external_reference') }}" class="mt-2 w-full rounded-lg border-slate-300" aria-describedby="reference-help"><p id="reference-help" class="mt-1 text-sm text-slate-500">Código del comprobante de Yape, Plin o banco. Es distinto del número interno generado por {{ config('jass.name') }}.</p>@error('external_reference')<p class="text-rose-700">{{ $message }}</p>@enderror</div><div class="md:col-span-2"><label for="notes" class="mb-1.5 block text-sm font-semibold text-slate-700">Observaciones</label><textarea id="notes" name="notes" rows="2" maxlength="250" aria-describedby="notes-help" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm">{{ old('notes') }}</textarea><p id="notes-help" class="mt-1 text-xs text-slate-500">Máximo 250 caracteres · <span id="notes-count">0</span>/250</p>@error('notes')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror</div></div><div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-5"><span class="text-sm font-semibold text-slate-600">Total seleccionado</span><strong id="selected-total" class="text-2xl text-slate-900">S/ 0.00</strong></div><button class="mt-5 w-full rounded-lg bg-sky-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-sky-700">Registrar pago y emitir recibo</button>@error('charges')<p class="mt-3 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
            @endif
        </form>
        <dialog id="confirm-payment" class="w-full max-w-lg rounded-xl border p-6 shadow-xl">
            <h2 class="text-xl font-bold">Revisar pago antes de confirmar</h2>
            <p class="mt-2 font-semibold">{{ $customer->display_name }} · {{ $customer->document_label }} {{ $customer->national_id }}</p>
            <ul id="confirm-charges" class="my-4 space-y-2 text-sm"></ul>
            <p id="confirm-method" class="text-sm"></p>
            <p class="mt-3 text-lg font-bold">Total: <span id="confirm-total"></span></p>
            <div class="mt-5 flex flex-wrap gap-3"><button type="button" id="back-to-payment" class="rounded-lg border px-4 py-3">Volver y corregir</button><button type="button" id="confirm-payment-submit" class="rounded-lg bg-sky-700 px-4 py-3 font-semibold text-white">Confirmar pago y emitir recibo</button></div>
        </dialog>
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('collection-form');
    if (!form) return;
    const boxes = [...form.querySelectorAll('.charge-checkbox')];
    const total = document.getElementById('selected-total');
    const update = () => { if (total) total.textContent = `S/ ${boxes.filter(box => box.checked).reduce((sum, box) => sum + Number(box.dataset.amount), 0).toFixed(2)}`; };
    boxes.forEach(box => box.addEventListener('change', update));
    document.querySelector('[data-select-all]')?.addEventListener('click', () => { boxes.forEach(box => box.checked = true); form.dispatchEvent(new Event('change', {bubbles:true})); update(); });
    document.querySelector('[data-clear-charges]')?.addEventListener('click', () => { boxes.forEach(box => box.checked = false); form.dispatchEvent(new Event('change', {bubbles:true})); update(); });
    document.querySelectorAll('[data-select-cycle]').forEach(button => button.addEventListener('click', () => { boxes.filter(box => box.name === 'invoices[]' && box.dataset.cycle === button.dataset.selectCycle).forEach(box => box.checked = true); form.dispatchEvent(new Event('change', {bubbles:true})); update(); }));
    const notes = document.getElementById('notes');
    const count = document.getElementById('notes-count');
    const countNotes = () => { if (notes && count) count.textContent = [...notes.value].length; };
    notes?.addEventListener('input', countNotes); countNotes(); update();
    document.getElementById('collection-errors')?.focus();
    const dialog = document.getElementById('confirm-payment');
    let confirmed = false;
    form.addEventListener('submit', event => {
        if (confirmed || !dialog?.showModal) return;
        event.preventDefault();
        const selected = boxes.filter(box => box.checked);
        if (!selected.length) { alert('Selecciona al menos una cuota o multa.'); return; }
        const list = document.getElementById('confirm-charges'); list.replaceChildren();
        selected.forEach(box => { const item = document.createElement('li'); item.textContent = `${box.dataset.label}: S/ ${Number(box.dataset.amount).toFixed(2)}`; list.append(item); });
        const method = document.getElementById('payment_method_id');
        document.getElementById('confirm-method').textContent = `Medio de pago: ${method.selectedOptions[0].textContent}`;
        document.getElementById('confirm-total').textContent = total.textContent;
        const reference = document.getElementById('external_reference').value.trim();
        if (reference) document.getElementById('confirm-method').textContent += ` · Referencia: ${reference}`;
        dialog.showModal();
    });
    document.getElementById('back-to-payment')?.addEventListener('click', () => dialog.close());
    document.getElementById('confirm-payment-submit')?.addEventListener('click', event => { confirmed = true; event.target.disabled = true; dialog.close(); form.requestSubmit(); });
})();
</script>
@endpush
