@extends('layouts.app')

@section('title', $definition['singular'])
@section('heading', $definition['singular'])

@section('content')
    @php
        $canAnnulPayment = App\Support\ResourceAccess::isAdministrator(auth()->user())
            || auth()->user()->role?->permissions->contains('name', 'payments.create');
    @endphp
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-center gap-3"><a href="{{ route('resources.index', ['resource' => $resource === 'late-fee-settings' ? 'settings' : $resource]) }}" class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">← Volver</a><div><p class="text-sm font-medium uppercase tracking-widest text-sky-700">Detalle</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $definition['singular'] }} #{{ $record->getKey() }}</h2></div></div>
        <div class="flex flex-wrap gap-2">
            @if ($resource === 'payments')
                <a href="{{ route('receipts.thermal', ['payment' => $record->getKey()]) }}" target="_blank" rel="noopener" class="rounded-lg bg-slate-800 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900">Imprimir recibo</a>
                @if ($record->status === App\Models\Payment::STATUS_ACTIVE && $canAnnulPayment)
                    <a href="{{ route('receipts.annul-form', ['payment' => $record->getKey()]) }}" class="rounded-lg bg-rose-700 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-rose-800">Anular pago</a>
                @endif
            @endif
            @unless (($definition['read_only'] ?? false) || ($definition['immutable'] ?? false) || ! App\Support\ResourceAccess::allows(auth()->user(), $resource, 'edit') || ($resource === 'settings' && $record->isAutomatic()))
                <a href="{{ $resource === 'late-fee-settings' ? route('settings.mora.edit') : route('resources.edit', ['resource' => $resource, 'record' => $record->getKey()]) }}" class="rounded-lg bg-sky-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">{{ in_array($resource, ['rates', 'late-fee-settings'], true) ? 'Nueva versión' : 'Editar' }}</a>
            @endunless
        </div>
    </div>

    @if ($resource === 'late-fee-settings')
        @include('resources.mora-history')
    @endif
    @if ($resource === 'settings' && $record->key === 'billing_period_months')
        @include('resources.cycle-schedule')
    @endif

    @if (in_array($resource, ['customers','properties','connections'], true) && App\Support\ResourceAccess::allows(auth()->user(), match ($resource) { 'customers' => 'properties', 'properties' => 'connections', default => 'connection-usage-types' }, 'create'))
        <aside class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
            <h3 class="font-bold">Continuar el alta del servicio</h3>
            <p class="mt-1 text-sm">1. Cliente → 2. Predio activo → 3. Conexión activa → 4. Uso y tarifa vigente.</p>
            @if ($resource === 'customers')<a class="mt-3 inline-block rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white" href="{{ route('resources.create',['resource'=>'properties','customer_id'=>$record->id]) }}">Continuar con su casa o local</a>
            @elseif ($resource === 'properties')<a class="mt-3 inline-block rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white" href="{{ route('resources.create',['resource'=>'connections','property_id'=>$record->id]) }}">Continuar con la conexión</a>
            @else
                <p class="mt-3 text-sm">Al registrar una conexión se asigna el uso residencial automáticamente. Revisa las fechas antes de cambiarlo.</p>
                @foreach ($record->usageAssignments as $usage)<a class="mt-3 mr-4 inline-block font-semibold text-sky-700" href="{{ route('resources.edit',['resource'=>'connection-usage-types','record'=>$usage->id]) }}">Revisar uso existente →</a>@endforeach
                <a class="mt-3 inline-block font-semibold text-sky-700" href="{{ route('resources.create',['resource'=>'connection-usage-types','connection_id'=>$record->id]) }}">Asignar uso a esta conexión →</a>
            @endif
        </aside>
    @endif
    @if ($resource === 'customers') @include('resources.customer-dossier') @endif
    @if ($resource === 'connections' && App\Support\ResourceAccess::allows(auth()->user(), 'meters', 'create'))
        <aside class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4"><h3 class="font-bold">Completar este servicio</h3>
        @if ($record->payment_mode === 'METERED')
            <p class="mt-2 text-sm">Para facturar consumo necesitas un medidor, una lectura del mes y un precio por m³ en la tarifa.</p>
            <a class="mt-3 inline-block rounded-lg bg-sky-700 px-4 py-3 font-semibold text-white" href="{{ route('resources.create',['resource'=>'meters','connection_id'=>$record->id]) }}">Registrar medidor</a>
            @foreach ($record->meters as $meter)<a class="ml-3 inline-block py-3 font-semibold text-sky-700" href="{{ route('resources.create',['resource'=>'meter-readings','meter_id'=>$meter->id]) }}">Ingresar lectura de {{ $meter->meter_number }}</a>@endforeach
        @endif
        @if (App\Support\ResourceAccess::allows(auth()->user(), 'rates'))<a class="mt-3 ml-3 inline-block font-semibold text-sky-700" href="{{ route('resources.index',['resource'=>'rates']) }}">Revisar tarifa</a><a class="ml-3 font-semibold text-sky-700" href="{{ route('billing.index',['customer_id'=>$record->property->customer_id]) }}">Revisar cuotas</a>@endif
        </aside>
    @endif
    @if ($resource === 'connections')
        <details class="mb-6 rounded-xl border bg-white p-5"><summary class="cursor-pointer font-bold">Revisar fechas de instalación y uso</summary>
            <p class="mt-3 text-sm">Paso 1: confirma la instalación ({{ $record->installed_on?->format('d/m/Y') ?? 'Sin fecha' }}). Paso 2: revisa las vigencias siguientes. Paso 3: revisa la emisión antes de confirmar cuotas. Los cobros y cuotas históricos se conservan.</p>
            @foreach ($record->usageAssignments()->with('usageType')->orderBy('starts_on')->get() as $assignment)
                <p class="mt-3 text-sm">{{ App\Support\CatalogLabel::value($assignment->usageType?->name ?? 'Uso') }} · {{ $assignment->starts_on->format('d/m/Y') }} a {{ $assignment->ends_on?->format('d/m/Y') ?? 'sin fecha final' }}
                @if (App\Support\ResourceAccess::allows(auth()->user(), 'connection-usage-types', 'edit'))<a class="ml-2 font-semibold text-sky-700" href="{{ route('resources.edit', ['resource'=>'connection-usage-types','record'=>$assignment->id]) }}">Revisar esta vigencia</a>@endif</p>
            @endforeach
            @if (App\Support\ResourceAccess::allows(auth()->user(), 'rates'))<a class="mt-4 inline-block font-semibold text-sky-700" href="{{ route('billing.index', ['customer_id'=>$record->property->customer_id]) }}">Paso 3: revisar cuotas futuras</a>@endif
        </details>
    @endif
    @if ($resource === 'meters' && App\Support\ResourceAccess::allows(auth()->user(), 'meter-readings', 'create'))<a class="mb-5 inline-block rounded-lg bg-sky-700 px-4 py-3 font-semibold text-white" href="{{ route('resources.create',['resource'=>'meter-readings','meter_id'=>$record->id]) }}">Ingresar lectura de este medidor</a>@endif
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <dl class="divide-y divide-slate-100">
            @foreach ($fields as $name => $field)
                @continue($field['hide_on_show'] ?? false)
                @continue($resource === 'payments' && in_array($name, ['voided_at', 'voided_by', 'void_reason'], true) && blank($record->getAttribute($name)))
                @php
                    $value = $record->getAttribute($name);
                    if ($resource === 'settings' && $name === 'key') $value = $record->displayName();
                    if ($resource === 'settings' && $name === 'description') $value = $record->displayDescription();
                    if (isset($field['relation'])) $value = data_get($record, $field['relation'].'.'.$field['options']['label']);
                    if (isset($field['choices'])) $value = $field['choices'][$value] ?? $value;
                    elseif (is_string($value)) $value = App\Http\Controllers\Web\JassPageController::catalogValueLabel($value);
                    if ($field['type'] === 'checkbox') $value = $value ? 'Sí' : 'No';
                    elseif ($field['type'] === 'number' && $value !== null) $value = ($name === 'year' ? (string) (int) $value : number_format((float) $value, ($field['integer'] ?? false) ? 0 : 2));
                    elseif ($value instanceof DateTimeInterface) $value = match ($field['type']) { 'date' => $value->format('d/m/Y'), 'datetime-local' => $value->format('d/m/Y H:i'), default => $value->format('d/m/Y') };
                    if ($resource === 'audit-logs' && $name === 'table_name') $value = $record->moduleLabel();
                    if ($resource === 'cash-closings' && $name === 'month') $value = Carbon\Carbon::create((int)$record->year, (int)$record->month, 1)->translatedFormat('F Y');
                    if ($resource === 'cash-closings' && in_array($name, ['total_income','total_expense','balance'])) $value = 'S/ '.number_format((float)$record->getAttribute($name), 2);
                    $isStructuredValue = is_array($value);
                    if ($isStructuredValue) $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                @endphp
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3 sm:gap-6 sm:px-6"><dt class="text-sm font-semibold text-slate-500">{{ $field['label'] }}</dt><dd class="text-sm text-slate-800 sm:col-span-2">@if ($isStructuredValue)<pre class="overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs leading-5 text-slate-100">{{ $value }}</pre>@else<span class="whitespace-pre-wrap">{{ ($value === null || $value === '') ? '—' : $value }}</span>@endif</dd></div>
            @endforeach
        </dl>
        @unless (($definition['read_only'] ?? false) || ($definition['immutable'] ?? false) || in_array($resource, ['rates', 'settings', 'late-fee-settings'], true) || ! App\Support\ResourceAccess::allows(auth()->user(), $resource, 'destroy') || ($resource === 'settings' && $record->isAutomatic()))
            <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                <form method="POST" action="{{ route('resources.destroy', ['resource' => $resource, 'record' => $record->getKey()]) }}" onsubmit="return confirm('¿Eliminar este registro? Esta acción no se puede deshacer.');">@csrf @method('DELETE')<button type="submit" class="rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">Eliminar</button></form>
            </div>
        @endunless
    </div>

    @if ($resource === 'payments')
        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-semibold text-slate-800">Conceptos pagados</h2>
                <p class="mt-1 text-sm text-slate-500">Detalle de servicios, moras y multas incluidos en este recibo.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold sm:px-6">Tipo</th>
                            <th class="px-5 py-3 font-semibold">Concepto</th>
                            <th class="px-5 py-3 font-semibold">Detalle</th>
                            <th class="px-5 py-3 text-right font-semibold sm:px-6">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($paymentConcepts as $concept)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3.5 sm:px-6">
                                    <span @class([
                                        'rounded-full px-2 py-1 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-700' => $concept['category'] === 'Servicio',
                                        'bg-amber-50 text-amber-700' => $concept['category'] === 'Mora',
                                        'bg-rose-50 text-rose-700' => $concept['category'] === 'Multa',
                                    ])>{{ $concept['category'] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 font-semibold text-slate-700">{{ $concept['concept'] }}</td>
                                <td class="px-5 py-3.5 text-slate-600">{{ $concept['detail'] }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right font-semibold text-slate-800 sm:px-6">S/ {{ number_format($concept['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-5 py-3 text-right text-sm font-bold text-slate-700">Total del recibo</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right text-base font-bold text-slate-900 sm:px-6">S/ {{ number_format((float) $record->amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif
@endsection
