@extends('layouts.app')

@section('title', 'Reportes')
@section('heading', 'Reportes')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-medium uppercase tracking-widest text-sky-700">Consultas y exportaciones</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Centro de reportes</h2>
        <p class="mt-1 text-sm text-slate-500">Descarga archivos Excel o abre los PDF directamente en el navegador.</p>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <form id="reporte-flujo-caja" method="GET" action="{{ route('reports.download', ['report' => 'cash-flow', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Flujo de caja mensual</h3>
            <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="reports-cash-month">Mes</label>
            <input id="reports-cash-month" type="month" name="month" value="{{ now()->format('Y-m') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'cash-flow', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-balance-anual" method="GET" action="{{ route('reports.download', ['report' => 'annual-balance', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Balance anual</h3>
            <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="reports-balance-year">Año</label>
            <input id="reports-balance-year" type="number" name="year" value="{{ now()->year }}" min="2000" max="2100" step="1" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'annual-balance', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-conceptos-mensual" method="GET" action="{{ route('reports.download', ['report' => 'payment-concepts-monthly', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Conceptos de pago mensuales</h3>
            <p class="mt-1 text-sm text-slate-500">Servicios, multas, moras, otros ingresos y egresos.</p>
            <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="reports-concept-month">Mes</label>
            <input id="reports-concept-month" type="month" name="month" value="{{ now()->format('Y-m') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'payment-concepts-monthly', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-conceptos-anual" method="GET" action="{{ route('reports.download', ['report' => 'payment-concepts-annual', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Conceptos de pago anuales</h3>
            <p class="mt-1 text-sm text-slate-500">Comparativo mensual por cada concepto.</p>
            <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="reports-concept-year">Año</label>
            <input id="reports-concept-year" type="number" name="year" value="{{ now()->year }}" min="2000" max="2100" step="1" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'payment-concepts-annual', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-morosos" method="GET" action="{{ route('reports.download', ['report' => 'debtors', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Lista de morosos</h3>
            <p class="mt-1 text-sm text-slate-500">Solo cuotas vencidas y multas pendientes por titular.</p>
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'debtors', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-antiguedad-deuda" method="GET" action="{{ route('reports.download', ['report' => 'debt-aging', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Antigüedad de deuda</h3>
            <p class="mt-1 text-sm text-slate-500">Clasifica la morosidad por cantidad de días vencidos.</p>
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'debt-aging', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-medios-pago" method="GET" action="{{ route('reports.download', ['report' => 'payment-methods', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Recaudación por medio de pago</h3>
            <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="reports-payment-method-month">Mes</label>
            <input id="reports-payment-method-month" type="month" name="month" value="{{ now()->format('Y-m') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'payment-methods', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-padron-conexiones" method="GET" action="{{ route('reports.download', ['report' => 'service-register', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Padrón de conexiones</h3>
            <p class="mt-1 text-sm text-slate-500">Clientes, predios, servicios, modalidad de cobro y medidores.</p>
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'service-register', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        @if ($assemblies->isNotEmpty())
            <form id="reporte-asistencias" method="GET" action="{{ route('reports.download', ['report' => 'attendance', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-800">Asistencias a asambleas</h3>
                @php
                    $reportAssemblyOptions = $assemblies->mapWithKeys(fn ($assembly) => [
                        $assembly->id => $assembly->assembly_code.' · '.$assembly->held_on->format('d/m/Y').' · '.($assembly->place ?: 'Sin lugar'),
                    ])->all();
                    $searchReportAssemblies = count($reportAssemblyOptions) > 20;
                @endphp
                <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $searchReportAssemblies ? 'reports-assembly-search' : 'reports-assembly' }}">Asamblea</label>
                <div class="mt-1">
                    @include('adaptive-select', [
                        'name' => 'assembly_id',
                        'selectId' => 'reports-assembly',
                        'searchId' => 'reports-assembly-search',
                        'options' => $reportAssemblyOptions,
                        'required' => true,
                        'placeholder' => $searchReportAssemblies ? 'Código, fecha o lugar' : 'Selecciona una asamblea',
                    ])
                </div>
                <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'attendance', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
            </form>
        @else
            <div id="reporte-asistencias" class="rounded-xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500">Registra una asamblea para generar su lista de asistencias.</div>
        @endif

        <form id="reporte-exonerados" method="GET" action="{{ route('reports.download', ['report' => 'work-exemptions', 'format' => 'xlsx']) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-800">Exonerados de faenas</h3>
            <p class="mt-1 text-sm text-slate-500">Titulares activos con {{ $reports->workExemptionAge() }} años o más.</p>
            <div class="mt-4 flex gap-2"><button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'work-exemptions', 'format' => 'pdf']) }}" formtarget="_blank" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700">Ver PDF</button></div>
        </form>

        <form id="reporte-historial-pagos" method="GET" action="{{ route('reports.download', ['report' => 'customer-payment-history', 'format' => 'xlsx']) }}" class="rounded-xl border border-sky-200 bg-white p-5 shadow-sm md:col-span-2 xl:col-span-3">
            <h3 class="font-semibold text-slate-800">Historial de pagos por cliente</h3>
            <p class="mt-1 text-sm text-slate-500">Escribe un dato y selecciona el cliente exacto por su código o DNI.</p>
            <div class="mt-2 grid gap-4 lg:grid-cols-2">
                @include('reports._customer_selector', ['selectorId' => 'reports-payment-history-customer'])
                @include('reports._payment_history_period', ['periodId' => 'reports-payment-history-period'])
            </div>
            <div class="mt-4 flex gap-2"><button type="submit" @disabled($reportCustomers->isEmpty()) class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Excel</button><button type="submit" formaction="{{ route('reports.download', ['report' => 'customer-payment-history', 'format' => 'pdf']) }}" formtarget="_blank" @disabled($reportCustomers->isEmpty()) class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">Ver PDF</button></div>
        </form>
    </div>
@endsection
