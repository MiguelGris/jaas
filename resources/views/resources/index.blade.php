@extends('layouts.app')

@section('title', $definition['label'])
@section('heading', $definition['label'])

@section('content')
    @php
        $canAnnulPayments = auth()->user()->role?->name === 'ADMINISTRATOR'
            || auth()->user()->role?->permissions->contains('name', 'payments.create');
        $deleteConfirmation = match ($resource) {
            'incomes' => '¿Eliminar este ingreso? El saldo de caja se actualizará y la acción quedará en la bitácora.',
            'expenses' => '¿Eliminar este egreso? El saldo de caja se actualizará y la acción quedará en la bitácora.',
            default => '¿Eliminar este elemento del catálogo? Si está en uso, el sistema conservará el registro.',
        };
    @endphp
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-medium uppercase tracking-widest text-sky-700">Gestión</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $definition['label'] }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $records->total() }} {{ $records->total() === 1 ? 'registro' : 'registros' }} en {{ Str::lower($definition['label']) }}.</p>
        </div>
        @unless ($definition['read_only'] ?? false)
            <a href="{{ route('resources.create', ['resource' => $resource]) }}" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">+ Registrar {{ Str::lower($definition['singular']) }}</a>
        @endunless
    </div>

    @if ($resource === 'invoices')
        @if (auth()->user()->role?->name === 'ADMINISTRATOR' || auth()->user()->role?->permissions->contains('name', 'rates.manage'))
            <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4"><a href="{{ route('billing.index') }}" class="font-semibold text-sky-700">Revisar y generar cuotas →</a><p class="mt-2 text-sm">Selecciona el mes y revisa las causas de omisión antes de confirmar. Las cuotas existentes se conservan.</p></div>
        @endif
        @php
            $invoiceTabs = [
                '' => 'Todas',
                'pending' => 'Pendientes',
                'overdue' => 'Vencidas',
                'paid' => 'Pagadas',
                'cancelled' => 'Anuladas',
            ];
        @endphp
        <nav class="mb-6 flex gap-2 overflow-x-auto pb-1" aria-label="Filtros de cuotas">
            @foreach ($invoiceTabs as $state => $label)
                <a href="{{ route('resources.index', array_filter(['resource' => 'invoices', 'state' => $state])) }}"
                   class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-semibold transition {{ $invoiceState === $state ? 'bg-sky-600 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-sky-300 hover:text-sky-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    @endif

    @if ($resource === 'payments')
        <form method="GET" action="{{ route('resources.index', ['resource' => 'payments']) }}" class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_10rem_10rem_auto] lg:items-end">
                <div><label for="payment-search" class="mb-1.5 block text-sm font-semibold text-slate-700">Cliente</label><input id="payment-search" type="search" name="q" value="{{ $paymentFilters['q'] }}" placeholder="DNI, RUC, nombre o razón social" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"></div>
                <div><label for="payment-from" class="mb-1.5 block text-sm font-semibold text-slate-700">Desde</label><input id="payment-from" type="date" name="from" value="{{ $paymentFilters['from'] }}" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"></div>
                <div><label for="payment-to" class="mb-1.5 block text-sm font-semibold text-slate-700">Hasta</label><input id="payment-to" type="date" name="to" value="{{ $paymentFilters['to'] }}" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"></div>
                <div class="flex gap-2"><button type="submit" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Buscar</button>@if (array_filter($paymentFilters))<a href="{{ route('resources.index', ['resource' => 'payments']) }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Limpiar</a>@endif</div>
            </div>
            @error('from')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
            @error('to')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
        </form>
    @endif

    @if ($resource === 'customers')
        <form method="GET" class="mb-6 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4">
            <label for="customer-search" class="w-full text-sm font-semibold">Buscar cliente por nombre, DNI, RUC o código</label>
            <input id="customer-search" type="search" name="q" maxlength="150" value="{{ $customerSearch }}" class="min-w-0 flex-1 rounded-lg border-slate-300" placeholder="Escribe un nombre o documento">
            <button class="rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white">Buscar</button>
            @if ($customerSearch !== '')<a href="{{ route('resources.index', ['resource'=>'customers']) }}" class="px-3 py-3">Limpiar</a>@endif
        </form>
        <div class="mb-4 space-y-3 md:hidden" aria-label="Lista de clientes para teléfono">
            @forelse ($records as $record)
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="break-words font-bold">{{ $record->display_name }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $record->customer_code }} · {{ $record->document_label }} {{ $record->national_id ?: 'sin registrar' }}</p>
                    <p class="mt-1 text-sm">{{ App\Support\CatalogLabel::value($record->customerStatus?->name ?? '') }}</p>
                    <div class="mt-3 flex flex-wrap gap-3"><a class="rounded-lg bg-sky-50 px-4 py-3 font-semibold text-sky-700" href="{{ route('resources.show', ['resource'=>'customers','record'=>$record->id]) }}">Ver cliente</a><a class="rounded-lg px-4 py-3 font-semibold text-sky-700" href="{{ route('resources.edit', ['resource'=>'customers','record'=>$record->id]) }}">Editar</a></div>
                </article>
            @empty<p class="rounded-xl bg-white p-4">No se encontraron clientes.</p>@endforelse
        </div>
    @endif
    @if ($resource === 'settings')
        <aside class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm">
            <h3 class="font-bold">Configuración que usa la facturación</h3>
            <p class="mt-2">Ciclo de pago: billing_period_months (3 o 6 meses). Día de emisión: billing_issue_day (1 a 28). La gracia y el importe de mora se cambian en Configuración de mora.</p>
            <p class="mt-2">Las claves antiguas con otros nombres se conservan como referencia y no controlan la emisión actual. billing_last_manual_run es un registro informativo de la última emisión manual.</p>
            <a href="{{ route('resources.index',['resource'=>'late-fee-settings']) }}" class="mt-3 inline-block font-semibold text-sky-700">Consultar configuración de mora →</a>
        </aside>
    @endif
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($resource === 'customers')<div class="hidden md:block">@endif
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        @foreach ($columns as $field)
                            <th class="whitespace-nowrap px-5 py-3 font-semibold">{{ $field['label'] }}</th>
                        @endforeach
                        <th class="px-5 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($records as $record)
                        <tr class="transition hover:bg-slate-50/80">
                            @foreach ($columns as $name => $field)
                                @php
                                    $value = $record->getAttribute($name);
                                    if (isset($field['relation'])) {
                                        $value = data_get($record, $field['relation'].'.'.$field['options']['label']);
                                    }
                                    if (isset($field['choices'])) {
                                        $value = $field['choices'][$value] ?? $value;
                                    } elseif (is_string($value)) {
                                        $value = App\Http\Controllers\Web\JassPageController::catalogValueLabel($value);
                                    }
                                    if ($field['type'] === 'checkbox') {
                                        $value = $value ? 'Sí' : 'No';
                                    } elseif ($field['type'] === 'number' && $value !== null) {
                                        $value = ($name === 'year' ? (string) (int) $value : number_format((float) $value, ($field['integer'] ?? false) ? 0 : 2));
                                    } elseif ($value instanceof DateTimeInterface) {
                                        $value = match ($field['type']) {
                                            'date' => $value->format('d/m/Y'),
                                            'datetime-local' => $value->format('d/m/Y H:i'),
                                            default => $value->format('d/m/Y'),
                                        };
                                    }
                                @endphp
                                <td class="max-w-xs px-5 py-3.5 text-slate-600"><span class="block truncate" title="{{ is_scalar($value) ? $value : '' }}">{{ ($value === null || $value === '') ? '—' : $value }}</span></td>
                            @endforeach
                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                <a href="{{ route('resources.show', ['resource' => $resource, 'record' => $record->getKey()]) }}" class="text-sm font-semibold text-slate-600 hover:text-sky-700">Ver</a>
                                @if ($resource === 'payments')
                                    <a href="{{ route('receipts.thermal', ['payment' => $record->getKey()]) }}" target="_blank" rel="noopener" class="ml-3 text-sm font-semibold text-sky-700 hover:text-sky-800">Imprimir</a>
                                    @if ($record->status === App\Models\Payment::STATUS_ACTIVE && $canAnnulPayments)
                                        <a href="{{ route('receipts.annul-form', ['payment' => $record->getKey()]) }}" class="ml-3 text-sm font-semibold text-rose-700 hover:text-rose-800">Anular</a>
                                    @endif
                                @endif
                                @unless (($definition['read_only'] ?? false) || ($definition['immutable'] ?? false))
                                    <a href="{{ route('resources.edit', ['resource' => $resource, 'record' => $record->getKey()]) }}" class="ml-3 text-sm font-semibold text-sky-700 hover:text-sky-800">{{ $resource === 'rates' ? 'Nueva versión' : 'Editar' }}</a>
                                @endunless
                                @if ($definition['delete_on_index'] ?? false)
                                    <form method="POST" action="{{ route('resources.destroy', ['resource' => $resource, 'record' => $record->getKey()]) }}" class="inline" onsubmit="return confirm({{ Js::from($deleteConfirmation) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-3 text-sm font-semibold text-rose-700 hover:text-rose-800">Eliminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($columns) + 1 }}" class="px-5 py-14 text-center text-sm text-slate-500">No hay registros todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($resource === 'customers')</div>@endif
        @if ($records->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $records->links() }}</div>
        @endif
    </div>
@endsection
