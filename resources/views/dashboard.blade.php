@extends('layouts.app')

@section('title', 'Panel principal')
@section('heading', 'Panel principal')

@section('content')
    @php
        $dashboardUser = auth()->user();
        $isAdministrator = $dashboardUser->role?->name === 'ADMINISTRATOR';
        $canUse = static fn (string $permission): bool => $isAdministrator
            || $dashboardUser->role?->permissions->contains('name', $permission);
    @endphp
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Resumen operativo</h2>
            <p class="mt-1 text-sm text-slate-500">Consulta las cifras clave y continúa con la gestión diaria.</p>
        </div>
        <div class="flex flex-wrap gap-2 sm:justify-end">
            @if ($canUse('customers.create'))
                <a href="{{ route('resources.create', ['resource' => 'customers']) }}" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">+ Registrar cliente</a>
            @endif
            @if ($canUse('payments.create'))
                <a href="{{ route('collections.create') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">S/ Realizar pago</a>
            @endif
            @if ($canUse('assemblies.manage'))
                <a href="{{ route('attendance.scanner') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900">✓ Registrar asistencia</a>
            @endif
        </div>
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
                    @php
                        $metricUrl = isset($metric['route_name'])
                            ? route($metric['route_name'])
                            : route('resources.index', ['resource' => $metric['resource'], ...($metric['query'] ?? [])]);
                    @endphp
                    <a href="{{ $metricUrl }}" @class([
                        'rounded-xl border p-5 transition hover:-translate-y-0.5 hover:shadow-md',
                        $colors,
                        'md:col-span-3' => $metric['wide'] ?? false,
                    ])>
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
                        $metricUrl = isset($metric['route_name'])
                            ? route($metric['route_name'])
                            : route('resources.index', ['resource' => $metric['resource'], ...($metric['query'] ?? [])]);
                    @endphp
                    <a href="{{ $metricUrl }}" class="rounded-xl border p-5 transition hover:-translate-y-0.5 hover:shadow-md {{ $colors }}">
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
                        <p class="mt-0.5 text-sm text-slate-500">Los 5 cobros, ingresos o gastos más recientes.</p>
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
                <h2 class="font-semibold text-slate-800">Cuotas recientes</h2>
                <p class="mt-0.5 text-sm text-slate-500">Las 5 cuotas mensuales emitidas más recientemente.</p>
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
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Todavía no hay cuotas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
            </section>
        </div>

        <aside id="reportes" class="self-start rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto">
            <h2 class="font-semibold text-slate-800">Reportes</h2>
            <p class="mt-1 text-sm text-slate-500">Excel se descarga. PDF se abre en una nueva pestaña.</p>

            <div class="mt-4 space-y-3">
                <form id="reporte-flujo-caja" method="GET" action="{{ route('reports.download', ['report' => 'cash-flow', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Flujo de caja mensual</h3>
                    <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="cash-month">Mes</label>
                    <input id="cash-month" type="month" name="month" value="{{ now()->format('Y-m') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'cash-flow', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reporte-balance-anual" method="GET" action="{{ route('reports.download', ['report' => 'annual-balance', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Balance anual</h3>
                    <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="balance-year">Año</label>
                    <input id="balance-year" type="number" name="year" value="{{ now()->year }}" min="2000" max="2100" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'annual-balance', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reporte-conceptos-mensual" method="GET" action="{{ route('reports.download', ['report' => 'payment-concepts-monthly', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Conceptos de pago mensuales</h3>
                    <p class="mt-1 text-xs text-slate-500">Servicios, multas, moras, otros ingresos y egresos.</p>
                    <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="concept-month">Mes</label>
                    <input id="concept-month" type="month" name="month" value="{{ now()->format('Y-m') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'payment-concepts-monthly', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reporte-conceptos-anual" method="GET" action="{{ route('reports.download', ['report' => 'payment-concepts-annual', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Conceptos de pago anuales</h3>
                    <p class="mt-1 text-xs text-slate-500">Comparativo mensual por cada concepto.</p>
                    <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="concept-year">Año</label>
                    <input id="concept-year" type="number" name="year" value="{{ now()->year }}" min="2000" max="2100" step="1" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'payment-concepts-annual', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reporte-historial-pagos" method="GET" action="{{ route('reports.download', ['report' => 'customer-payment-history', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Historial de pagos por cliente</h3>
                    <p class="mt-1 text-xs text-slate-500">Busca en el padrón y selecciona por código, DNI y nombre.</p>
                    @include('reports._customer_selector', ['selectorId' => 'dashboard-payment-history-customer'])
                    @include('reports._payment_history_period', ['periodId' => 'dashboard-payment-history-period'])
                    <div class="mt-2 flex gap-2"><button type="submit" @disabled($reportCustomers->isEmpty()) class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'customer-payment-history', 'format' => 'pdf']) }}" formtarget="_blank" @disabled($reportCustomers->isEmpty()) class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">Ver PDF</button></div>
                </form>

                <form id="reporte-morosos" method="GET" action="{{ route('reports.download', ['report' => 'debtors', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Lista de morosos</h3>
                    <p class="mt-1 text-xs text-slate-500">Solo cuotas vencidas y multas pendientes por titular.</p>
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'debtors', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reporte-antiguedad-deuda" method="GET" action="{{ route('reports.download', ['report' => 'debt-aging', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Antigüedad de deuda</h3>
                    <p class="mt-1 text-xs text-slate-500">Clasifica la morosidad en 0–30, 31–60, 61–90 y más de 90 días.</p>
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'debt-aging', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reporte-medios-pago" method="GET" action="{{ route('reports.download', ['report' => 'payment-methods', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Recaudación por medio de pago</h3>
                    <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="payment-method-month">Mes</label>
                    <input id="payment-method-month" type="month" name="month" value="{{ now()->format('Y-m') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'payment-methods', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                <form id="reporte-padron-conexiones" method="GET" action="{{ route('reports.download', ['report' => 'service-register', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Padrón de conexiones</h3>
                    <p class="mt-1 text-xs text-slate-500">Clientes, predios, servicios, modalidad de cobro y medidores.</p>
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'service-register', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>

                @if ($assemblies->isNotEmpty())
                    <form id="reporte-asistencias" method="GET" action="{{ route('reports.download', ['report' => 'attendance', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                        <h3 class="text-sm font-semibold text-slate-800">Asistencias a asambleas</h3>
                        @php
                            $dashboardAssemblyOptions = $assemblies->mapWithKeys(fn ($assembly) => [
                                $assembly->id => $assembly->assembly_code.' · '.$assembly->held_on->format('d/m/Y').' · '.($assembly->place ?: 'Sin lugar'),
                            ])->all();
                            $searchDashboardAssemblies = count($dashboardAssemblyOptions) > 20;
                        @endphp
                        <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $searchDashboardAssemblies ? 'dashboard-report-assembly-search' : 'dashboard-report-assembly' }}">Asamblea</label>
                        <div class="mt-1">
                            @include('adaptive-select', [
                                'name' => 'assembly_id',
                                'selectId' => 'dashboard-report-assembly',
                                'searchId' => 'dashboard-report-assembly-search',
                                'options' => $dashboardAssemblyOptions,
                                'required' => true,
                                'placeholder' => $searchDashboardAssemblies ? 'Código, fecha o lugar' : 'Selecciona una asamblea',
                            ])
                        </div>
                        <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'attendance', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                    </form>
                @else
                    <div id="reporte-asistencias" class="scroll-mt-6 rounded-lg border border-dashed border-slate-300 p-3.5 text-xs text-slate-500">Registra una asamblea para generar su lista de asistencias.</div>
                @endif

                <form id="reporte-exonerados" method="GET" action="{{ route('reports.download', ['report' => 'work-exemptions', 'format' => 'xlsx']) }}" class="scroll-mt-6 rounded-lg border border-slate-200 p-3">
                    <h3 class="text-sm font-semibold text-slate-800">Exonerados de faenas</h3>
                    <p class="mt-1 text-xs text-slate-500">Titulares activos con {{ $reports->workExemptionAge() }} años o más. Puedes ajustar la edad desde Administración → Configuraciones.</p>
                    <div class="mt-2 flex gap-2"><button type="submit" class="cursor-pointer rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'work-exemptions', 'format' => 'pdf']) }}" formtarget="_blank" class="cursor-pointer rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
                </form>
            </div>
        </aside>
    </div>
@endsection
