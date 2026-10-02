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
            <p class="mt-1 text-sm text-slate-500">{{ $records->total() }} {{ Str::lower($definition['label']) }} registrados.</p>
        </div>
        @unless ($definition['read_only'] ?? false)
            <a href="{{ route('resources.create', ['resource' => $resource]) }}" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">+ Nuevo {{ Str::lower($definition['singular']) }}</a>
        @endunless
    </div>

    @if ($resource === 'invoices')
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

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
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
                                        $value = number_format((float) $value, ($field['integer'] ?? false) ? 0 : 2);
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
        @if ($records->hasPages())
            <div class="border-t border-slate-100 px-5 py-4">{{ $records->links() }}</div>
        @endif
    </div>
@endsection
