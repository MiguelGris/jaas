@extends('layouts.app')

@section('title', 'Panel principal')
@section('heading', 'Panel principal')

@section('content')
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Resumen operativo</h2>
            <p class="mt-1 text-sm text-slate-500">Consulta las cifras clave y continúa con la gestión diaria.</p>
        </div>
        <a href="{{ route('resources.create', ['resource' => 'customers']) }}" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">+ Registrar cliente</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="space-y-8">
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($financialMetrics as $metric)
                    @php
                        $colors = match ($metric['accent']) {
                            'rose' => 'border-rose-100 bg-rose-50 text-rose-700',
                            'amber' => 'border-amber-100 bg-amber-50 text-amber-700',
                            'sky' => 'border-sky-100 bg-sky-50 text-sky-700',
                            default => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                        };
                    @endphp
                    <a href="{{ route('resources.index', ['resource' => $metric['resource']]) }}" class="rounded-xl border p-5 transition hover:-translate-y-0.5 hover:shadow-md {{ $colors }}">
                        <p class="text-sm font-medium opacity-80">{{ $metric['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold tracking-tight">@if (($metric['format'] ?? 'currency') === 'currency')S/ {{ number_format($metric['value'], 2) }}@else{{ number_format($metric['value']) }}@endif</p>
                        @if (isset($metric['detail']))<p class="mt-1 text-xs font-medium opacity-80">{{ $metric['detail'] }}</p>@endif
                        <p class="mt-4 text-xs font-semibold">Ver detalle →</p>
                    </a>
                @endforeach
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($metrics as $metric)
                    @php
                        $colors = match ($metric['accent']) {
                            'violet' => 'border-violet-100 bg-violet-50 text-violet-700',
                            'amber' => 'border-amber-100 bg-amber-50 text-amber-700',
                            'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                            default => 'border-sky-100 bg-sky-50 text-sky-700',
                        };
                    @endphp
                    <a href="{{ route('resources.index', ['resource' => $metric['resource']]) }}" class="rounded-xl border p-5 transition hover:-translate-y-0.5 hover:shadow-md {{ $colors }}">
                        <p class="text-sm font-medium opacity-80">{{ $metric['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold tracking-tight">{{ number_format($metric['value']) }}</p>
                        @if (isset($metric['detail']))<p class="mt-1 text-xs font-medium opacity-80">{{ $metric['detail'] }}</p>@endif
                        <p class="mt-4 text-xs font-semibold">Ver módulo →</p>
                    </a>
                @endforeach
            </div>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="font-semibold text-slate-800">Últimos movimientos</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Cobros, ingresos y gastos registrados recientemente.</p>
                    </div>
                    <a href="{{ route('resources.index', ['resource' => 'payments']) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-800">Ver cobros</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-5 py-3 font-semibold sm:px-6">Fecha</th><th class="px-5 py-3 font-semibold">Tipo</th><th class="px-5 py-3 font-semibold">Detalle</th><th class="px-5 py-3 text-right font-semibold sm:px-6">Monto</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($movements as $movement)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-5 py-3.5 text-slate-600 sm:px-6">{{ $movement['occurred_at']->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3.5"><span @class(['rounded-full px-2 py-1 text-xs font-semibold', 'bg-rose-50 text-rose-700' => $movement['direction'] === 'expense', 'bg-emerald-50 text-emerald-700' => $movement['direction'] === 'income'])>{{ $movement['type'] }}</span></td>
                                    <td class="px-5 py-3.5 text-slate-600"><p class="font-medium text-slate-700">{{ $movement['concept'] }}</p><p class="mt-0.5 text-xs text-slate-400">{{ $movement['code'] }}</p></td>
                                    <td @class(['whitespace-nowrap px-5 py-3.5 text-right font-semibold sm:px-6', 'text-rose-700' => $movement['direction'] === 'expense', 'text-emerald-700' => $movement['direction'] === 'income'])>{{ $movement['direction'] === 'expense' ? '−' : '+' }} S/ {{ number_format($movement['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Todavía no hay movimientos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
            <div>
                <h2 class="font-semibold text-slate-800">Facturas recientes</h2>
                <p class="mt-0.5 text-sm text-slate-500">Últimos comprobantes emitidos.</p>
            </div>
            <a href="{{ route('resources.index', ['resource' => 'invoices']) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-800">Ver todas</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3 font-semibold sm:px-6">Suministro</th><th class="px-5 py-3 font-semibold">Cliente</th><th class="px-5 py-3 font-semibold">Vencimiento</th><th class="px-5 py-3 text-right font-semibold sm:px-6">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentInvoices as $invoice)
                        @php
                            $customer = $invoice->connection?->property?->customer;
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-3.5 font-medium text-slate-700 sm:px-6">{{ $invoice->connection?->supply_code ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $invoice->due_on?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right font-semibold text-slate-800 sm:px-6">S/ {{ number_format((float) $invoice->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Todavía no hay facturas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
            </section>
        </div>

        <aside id="reportes" class="self-start rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-6">
            <h2 class="font-semibold text-slate-800">Reportes</h2>
            <p class="mt-1 text-sm text-slate-500">Excel se descarga. PDF se abre en una nueva pestaña.</p>

            <div class="mt-4 space-y-3">
                <form id="reportes-financieros-comunitarios" method="GET" action="{{ route('reports.download', ['report' => 'cash-flow', 'format' => 'xlsx']) }}" class="rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Flujo de caja mensual</h3>
                    <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="cash-month">Mes</label>
                    <input id="cash-month" type="month" name="month" value="{{ now()->format('Y-m') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'cash-flow', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reportes-operativos" method="GET" action="{{ route('reports.download', ['report' => 'debtors', 'format' => 'xlsx']) }}" class="rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Lista de morosos</h3>
                    <p class="mt-1 text-xs text-slate-500">Cuotas, multas y saldo pendiente por titular.</p>
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'debtors', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                @if ($assemblies->isNotEmpty())
                    <form method="GET" action="{{ route('reports.download', ['report' => 'attendance', 'format' => 'xlsx']) }}" class="rounded-lg border border-slate-200 p-3">
                        <h3 class="text-sm font-semibold text-slate-800">Asistencias a asambleas</h3>
                        <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="report-assembly">Asamblea</label>
                        <select id="report-assembly" name="assembly_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@foreach ($assemblies as $assembly)<option value="{{ $assembly->id }}">{{ $assembly->assembly_code }} · {{ $assembly->held_on->format('d/m/Y') }}</option>@endforeach</select>
                        <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'attendance', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                    </form>
                @else
                    <div class="rounded-lg border border-dashed border-slate-300 p-3.5 text-xs text-slate-500">Registra una asamblea para generar su lista de asistencias.</div>
                @endif

                <form method="GET" action="{{ route('reports.download', ['report' => 'work-exemptions', 'format' => 'xlsx']) }}" class="rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Exonerados de faenas</h3>
                    <p class="mt-1 text-xs text-slate-500">Titulares activos con {{ $reports->workExemptionAge() }} años o más. Ajustable en Gestión → Configuración: <code>work_exemption_age</code>.</p>
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'work-exemptions', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>
            </div>
        </aside>
    </div>
@endsection
