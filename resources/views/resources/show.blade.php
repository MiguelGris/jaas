@extends('layouts.app')

@section('title', $definition['singular'])
@section('heading', $definition['singular'])

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-center gap-3"><a href="{{ route('resources.index', ['resource' => $resource]) }}" class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">← Volver</a><div><p class="text-sm font-medium uppercase tracking-widest text-sky-700">Detalle</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $definition['singular'] }} #{{ $record->getKey() }}</h2></div></div>
        <div class="flex flex-wrap gap-2">
            @if ($resource === 'payments')
                <a href="{{ route('receipts.thermal', ['payment' => $record->getKey()]) }}" target="_blank" rel="noopener" class="rounded-lg bg-slate-800 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900">Imprimir recibo</a>
            @endif
            @unless ($definition['read_only'] ?? false)
                <a href="{{ route('resources.edit', ['resource' => $resource, 'record' => $record->getKey()]) }}" class="rounded-lg bg-sky-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Editar</a>
            @endunless
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <dl class="divide-y divide-slate-100">
            @foreach ($fields as $name => $field)
                @php
                    $value = $record->getAttribute($name);
                    if (isset($field['relation'])) $value = data_get($record, $field['relation'].'.'.$field['options']['label']);
                    if (isset($field['choices'])) $value = $field['choices'][$value] ?? $value;
                    if ($field['type'] === 'checkbox') $value = $value ? 'Sí' : 'No';
                    elseif ($field['type'] === 'number' && $value !== null) $value = number_format((float) $value, 2);
                    elseif ($value instanceof DateTimeInterface) $value = match ($field['type']) { 'date' => $value->format('d/m/Y'), 'datetime-local' => $value->format('d/m/Y H:i'), default => $value->format('d/m/Y') };
                    $isStructuredValue = is_array($value);
                    if ($isStructuredValue) $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                @endphp
                <div class="grid gap-1 px-5 py-4 sm:grid-cols-3 sm:gap-6 sm:px-6"><dt class="text-sm font-semibold text-slate-500">{{ $field['label'] }}</dt><dd class="text-sm text-slate-800 sm:col-span-2">@if ($isStructuredValue)<pre class="overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs leading-5 text-slate-100">{{ $value }}</pre>@else<span class="whitespace-pre-wrap">{{ ($value === null || $value === '') ? '—' : $value }}</span>@endif</dd></div>
            @endforeach
        </dl>
        @unless ($definition['read_only'] ?? false)
            <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                <form method="POST" action="{{ route('resources.destroy', ['resource' => $resource, 'record' => $record->getKey()]) }}" onsubmit="return confirm('¿Eliminar este registro? Esta acción no se puede deshacer.');">@csrf @method('DELETE')<button type="submit" class="rounded-lg px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">Eliminar</button></form>
            </div>
        @endunless
    </div>
@endsection
