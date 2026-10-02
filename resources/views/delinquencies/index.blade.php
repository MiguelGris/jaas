@extends('layouts.app')

@section('title', 'Morosidad')
@section('heading', 'Morosidad')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="text-sm font-medium uppercase tracking-widest text-amber-700">Cobranza vencida</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Lista de morosos</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">Solo se muestran cuotas cuya fecha de vencimiento ya pasó y multas pendientes. Las cuotas dentro de su periodo de pago no aparecen aquí.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.download', ['report' => 'debtors', 'format' => 'xlsx']) }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">Descargar Excel</a>
            <a href="{{ route('reports.download', ['report' => 'debtors', 'format' => 'pdf']) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">Ver PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('delinquencies.index') }}" class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="debtor-search" class="mb-1.5 block text-sm font-semibold text-slate-700">Buscar cliente moroso</label>
                <input id="debtor-search" type="search" name="q" value="{{ $search }}" placeholder="DNI, RUC, código, nombre o razón social" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Buscar</button>
                @if ($search !== '')
                    <a href="{{ route('delinquencies.index') }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Limpiar</a>
                @endif
            </div>
        </div>
    </form>

    <div class="mb-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-medium text-amber-700">Clientes morosos</p>
            <p class="mt-1 text-2xl font-bold text-amber-900">{{ number_format($debtors->count()) }}</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-sm font-medium text-rose-700">Deuda vencida total</p>
            <p class="mt-1 text-2xl font-bold text-rose-900">S/ {{ number_format($total, 2) }}</p>
        </div>
    </div>

    <div class="space-y-4">
        @forelse ($debtors as $debtor)
            @php($customer = $debtor['customer'])
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex flex-col justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
                    <div>
                        <h3 class="font-bold text-slate-900">{{ $customer->display_name }}</h3>
                        <p class="mt-0.5 text-sm text-slate-500">{{ $customer->document_label }} {{ $customer->national_id ?: 'sin registrar' }} · {{ $customer->customer_code }}@if ($customer->phone) · {{ $customer->phone }}@endif</p>
                    </div>
                    <p class="text-lg font-bold text-rose-700">S/ {{ number_format($debtor['total'], 2) }}</p>
                </header>

                <div class="grid gap-5 p-4 lg:grid-cols-2 lg:p-5">
                    <section>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Cuotas vencidas</h4>
                        <div class="space-y-2">
                            @forelse ($debtor['invoices'] as $invoice)
                                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-800">{{ $invoice->invoice_code }} · {{ $invoice->connection?->supply_code }}</p>
                                            <p class="mt-0.5 text-slate-500">{{ $invoice->period_starts_on?->translatedFormat('F Y') }} · venció {{ $invoice->due_on?->format('d/m/Y') }}</p>
                                            @if ($invoice->late_fee_months > 0)<p class="mt-1 text-xs font-medium text-amber-700">{{ $invoice->late_fee_months }} {{ $invoice->late_fee_months === 1 ? 'mes' : 'meses' }} de mora</p>@endif
                                        </div>
                                        <span class="whitespace-nowrap font-bold text-rose-700">S/ {{ number_format($invoice->balance_due, 2) }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-lg border border-dashed border-slate-200 p-3 text-sm text-slate-500">Sin cuotas vencidas.</p>
                            @endforelse
                        </div>
                    </section>

                    <section>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Multas pendientes</h4>
                        <div class="space-y-2">
                            @forelse ($debtor['fines'] as $fine)
                                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-800">{{ $fine->fine_code }}</p>
                                            <p class="mt-0.5 text-slate-500">{{ $fine->reason ?: 'Multa pendiente' }} · {{ $fine->generated_on?->format('d/m/Y') }}</p>
                                        </div>
                                        <span class="whitespace-nowrap font-bold text-rose-700">S/ {{ number_format($fine->balance_due, 2) }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-lg border border-dashed border-slate-200 p-3 text-sm text-slate-500">Sin multas pendientes.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center">
                <p class="font-semibold text-slate-700">No se encontraron clientes morosos.</p>
                <p class="mt-1 text-sm text-slate-500">Las cuotas vigentes dentro de su plazo de pago no se incluyen.</p>
            </div>
        @endforelse
    </div>
@endsection
