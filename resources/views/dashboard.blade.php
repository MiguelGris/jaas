@extends('layouts.app')

@section('title', 'Panel principal')
@section('heading', 'Panel principal')

@section('content')
    @php
        $dashboardUser = auth()->user();
        $isAdministrator = App\Support\ResourceAccess::isAdministrator($dashboardUser);
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
            @if ($canUse('rates.manage'))<a href="{{ route('billing.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Revisar cuotas</a>@endif
            @if ($canUse('reports.view'))<a href="{{ route('resources.index',['resource'=>'payments']) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Buscar o reimprimir recibo</a>@endif
            @if ($canUse('customers.view'))<a href="{{ route('resources.index',['resource'=>'customers']) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold">Buscar cliente</a>@endif
            @if ($canUse('assemblies.manage'))
                <a href="{{ route('attendance.scanner') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900">✓ Registrar asistencia</a>
            @endif
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="min-w-0 space-y-8">
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($financialMetrics as $metric)
                    @continue(! App\Support\ResourceAccess::allows($dashboardUser, $metric['resource'] ?? 'reports'))
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
                    @continue(! App\Support\ResourceAccess::allows($dashboardUser, $metric['resource'] ?? 'reports'))
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
                <div class="max-w-full overflow-x-auto">
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

        <div class="max-w-full overflow-x-auto">
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
                            <td class="px-5 py-3.5 text-slate-600">{{ $customer?->display_name ?? '—' }}</td>
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

        <aside id="reportes" class="self-start rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Tu trabajo de hoy</h2>
            @if ($billingReminder)
                <div role="status" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                @if (isset($billingReminder['message'])){{ $billingReminder['message'] }}
                @else<strong>Facturación {{ $billingReminder['month'] }}</strong><p class="mt-2">{{ $billingReminder['ready'] }} cuotas por emitir · {{ $billingReminder['existing'] }} emitidas · {{ $billingReminder['omitted'] }} servicios omitidos.</p><p class="mt-2">{{ $billingReminder['missing_readings'] }} servicios necesitan lecturas del medidor.</p>@endif
                </div>
            @endif
            @if ($canUse('payments.create'))<p class="mt-3 text-sm">Busca al titular, elige las deudas y revisa el importe antes de emitir el recibo.</p>@endif
            @if ($canUse('services.manage'))<p class="mt-3 text-sm">Completa las conexiones y registra las lecturas del mes antes de facturar.</p><a class="mt-3 block font-semibold text-sky-700" href="{{ route('resources.index',['resource'=>'meter-readings']) }}">Revisar lecturas →</a>@endif
            @if ($canUse('rates.manage'))<p class="mt-3 text-sm">Revisa los servicios que están listos y los que necesitan datos para emitir cuotas.</p><a class="mt-3 block font-semibold text-sky-700" href="{{ route('billing.index') }}">Revisar facturación del mes →</a>@endif
            @if ($canUse('audit.view'))<p class="mt-3 text-sm">Consulta quién hizo cada cambio y revisa los reportes del periodo.</p>@endif
            <h3 class="mt-5 font-bold">Reportes</h3><p class="mt-2 text-sm">Elige el periodo y descarga Excel o PDF desde el centro de reportes.</p><a class="mt-3 inline-block rounded-lg bg-sky-700 px-4 py-3 font-semibold text-white" href="{{ route('reports.index') }}">Abrir centro de reportes</a>
        </aside>
    </div>
@endsection
