@extends('layouts.app')

@section('title', 'Cobrar cuotas')
@section('heading', 'Cobrar cuotas')

@section('content')
    <div class="mb-6">
        <p class="text-sm font-medium uppercase tracking-widest text-sky-700">Cobranza</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Registrar pago</h2>
        <p class="mt-1 text-sm text-slate-500">Busca por DNI, nombres o apellidos. Selecciona las cuotas y multas que se cancelarán en un único recibo.</p>
    </div>

    @if (session('receipt_id'))
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="font-semibold text-sky-950">Recibo listo para imprimir</p><p class="mt-0.5 text-sm text-sky-800">Se abrirá en formato térmico de 70 mm.</p></div>
            <a href="{{ route('receipts.thermal', ['payment' => session('receipt_id')]) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-800">Imprimir recibo</a>
        </div>
    @endif

    <form method="GET" action="{{ route('collections.create') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <label for="q" class="block text-sm font-semibold text-slate-700">Cliente</label>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row"><input id="q" name="q" value="{{ $search }}" placeholder="DNI, nombre o apellido" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"><button class="rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-900">Buscar</button></div>
    </form>

    @if ($customers->isNotEmpty())
        <div class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-bold text-slate-800">Resultados</p><div class="mt-3 grid gap-3 md:grid-cols-2">@foreach ($customers as $result)<a href="{{ route('collections.create', ['customer' => $result->id, 'q' => $search]) }}" class="rounded-lg border border-slate-200 px-4 py-3 transition hover:border-sky-300 hover:bg-sky-50"><span class="block font-semibold text-slate-800">{{ $result->last_name }}, {{ $result->first_name }}</span><span class="text-sm text-slate-500">DNI {{ $result->national_id ?? '—' }} · {{ $result->customer_code }}</span></a>@endforeach</div></div>
    @endif

    @if ($customer)
        <form method="POST" action="{{ route('collections.store') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium uppercase tracking-widest text-sky-700">Cliente seleccionado</p><h3 class="mt-1 text-xl font-bold text-slate-900">{{ $customer->last_name }}, {{ $customer->first_name }}</h3><p class="text-sm text-slate-500">DNI {{ $customer->national_id }} · {{ $customer->customer_code }}</p></div>

            @if ($invoices->isEmpty() && $fines->isEmpty())
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center text-emerald-900"><p class="font-bold">No tiene cargos pendientes.</p></div>
            @else
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-slate-900">Cuotas mensuales</h3><p class="mt-1 text-sm text-slate-500">Puedes pagar un mes, varios meses o adelantar todas las cuotas disponibles.</p></div>
                    <div class="divide-y divide-slate-100">@forelse ($invoices as $invoice)<label class="flex cursor-pointer items-center gap-4 px-5 py-4 hover:bg-slate-50"><input type="checkbox" name="invoices[]" value="{{ $invoice->id }}" data-amount="{{ $invoice->balance_due }}" class="charge-checkbox size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"><span class="min-w-0 flex-1"><span class="block font-semibold text-slate-800">{{ $invoice->period_starts_on->translatedFormat('F Y') }} · {{ $invoice->connection->supply_code }}</span><span class="block text-sm text-slate-500">Cuota S/ {{ number_format($invoice->service_amount, 2) }} @if($invoice->calculated_late_fee > 0) · Mora ({{ $invoice->late_fee_months }} mes): S/ {{ number_format($invoice->calculated_late_fee, 2) }} @endif</span></span><span class="font-bold text-slate-900">S/ {{ number_format($invoice->balance_due, 2) }}</span></label>@empty <p class="px-5 py-5 text-sm text-slate-500">No hay cuotas pendientes.</p>@endforelse</div>
                </div>

                @if ($fines->isNotEmpty())
                <div class="rounded-xl border border-rose-200 bg-white shadow-sm"><div class="border-b border-rose-100 bg-rose-50 px-5 py-4"><h3 class="font-bold text-rose-900">Multas pendientes</h3></div><div class="divide-y divide-slate-100">@foreach ($fines as $fine)<label class="flex cursor-pointer items-center gap-4 px-5 py-4 hover:bg-slate-50"><input type="checkbox" name="fines[]" value="{{ $fine->id }}" data-amount="{{ $fine->balance_due }}" class="charge-checkbox size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"><span class="min-w-0 flex-1"><span class="block font-semibold text-slate-800">{{ $fine->reason ?? 'Multa' }}</span><span class="block text-sm text-slate-500">Generada el {{ $fine->generated_on->format('d/m/Y') }}</span></span><span class="font-bold text-slate-900">S/ {{ number_format($fine->balance_due, 2) }}</span></label>@endforeach</div></div>
                @endif

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="grid gap-5 md:grid-cols-2"><div><label for="payment_method_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Medio de pago</label><select id="payment_method_id" name="payment_method_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm" required><option value="">Selecciona una opción</option>@foreach($paymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select></div><div class="rounded-lg border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900"><span class="block font-semibold">Número de operación</span><span class="text-sky-700">Se generará automáticamente al registrar el pago.</span></div><div class="md:col-span-2"><label for="notes" class="mb-1.5 block text-sm font-semibold text-slate-700">Observaciones</label><textarea id="notes" name="notes" rows="2" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm"></textarea></div></div><div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-5"><span class="text-sm font-semibold text-slate-600">Total seleccionado</span><strong id="selected-total" class="text-2xl text-slate-900">S/ 0.00</strong></div><button class="mt-5 w-full rounded-lg bg-sky-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-sky-700">Registrar pago y emitir recibo</button>@error('charges')<p class="mt-3 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
            @endif
        </form>
    @endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('.charge-checkbox').forEach((checkbox) => checkbox.addEventListener('change', () => {
    const total = [...document.querySelectorAll('.charge-checkbox:checked')].reduce((sum, item) => sum + Number(item.dataset.amount), 0);
    document.getElementById('selected-total').textContent = `S/ ${total.toFixed(2)}`;
}));
</script>
@endpush
