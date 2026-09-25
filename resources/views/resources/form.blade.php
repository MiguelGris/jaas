@extends('layouts.app')

@php
    $editing = $record !== null;
@endphp
@section('title', ($editing ? 'Editar ' : 'Nuevo ').Str::lower($definition['singular']))
@section('heading', $editing ? 'Editar '.$definition['singular'] : 'Nuevo '.$definition['singular'])

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('resources.index', ['resource' => $resource]) }}" class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">← Volver</a>
        <div><h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ $editing ? 'Editar '.$definition['singular'] : 'Registrar '.$definition['singular'] }}</h2><p class="mt-1 text-sm text-slate-500">Los campos marcados con <span class="text-rose-600">*</span> son obligatorios.</p></div>
    </div>

    <form method="POST" action="{{ $editing ? route('resources.update', ['resource' => $resource, 'record' => $record->getKey()]) : route('resources.store', ['resource' => $resource]) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
            @foreach ($definition['fields'] as $name => $field)
                @continue($field['readonly'] ?? false)
                @php
                    $value = $field['type'] === 'password'
                        ? null
                        : old($name, $editing ? $record->getAttribute($name) : ($field['default'] ?? null));
                    if ($value instanceof DateTimeInterface) {
                        $value = match ($field['type']) {
                            'date' => $value->format('Y-m-d'),
                            'time' => $value->format('H:i'),
                            'datetime-local' => $value->format('Y-m-d\\TH:i'),
                            default => (string) $value,
                        };
                    }
                    if ($field['type'] === 'time' && is_string($value) && preg_match('/^(\d{2}:\d{2})/', $value, $matches) === 1) {
                        $value = $matches[1];
                    }
                    $wide = $field['type'] === 'textarea';
                    $isRequired = $field['required'] && ! ($editing && ($field['optional_on_update'] ?? false));
                @endphp
                <div @class(['md:col-span-2' => $wide])>
                    @if ($field['type'] === 'checkbox')
                        <input type="hidden" name="{{ $name }}" value="0">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) old($name, $editing ? $record->getAttribute($name) : ($field['default'] ?? false))) class="size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            {{ $field['label'] }}
                        </label>
                    @else
                        <label for="{{ $name }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $field['label'] }} @if ($isRequired)<span class="text-rose-600">*</span>@endif</label>
                        @if ($editing && $field['type'] === 'password')<p class="mb-2 text-xs text-slate-500">Déjala en blanco para conservar la contraseña actual.</p>@endif
                        @if ($field['type'] === 'textarea')
                            <textarea id="{{ $name }}" name="{{ $name }}" rows="4" @required($isRequired) class="block w-full rounded-lg border-slate-300 text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">{{ $value }}</textarea>
                        @elseif ($field['type'] === 'select')
                            <div data-select-filter>
                                <input type="search" placeholder="Buscar en la lista…" aria-label="Buscar {{ Str::lower($field['label']) }}" class="mb-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                                <select id="{{ $name }}" name="{{ $name }}" @required($isRequired) class="block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                                    @if (! $field['required'])<option value="">Selecciona una opción</option>@endif
                                    @foreach ($options[$name] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] }}" value="{{ $value }}" @required($isRequired) @if ($field['type'] === 'password') autocomplete="new-password" @endif @if ($field['type'] === 'number') step="{{ $field['step'] ?? '0.01' }}" @endif @if (isset($field['min'])) min="{{ $field['min'] }}" @endif @if (isset($field['max_value'])) max="{{ $field['max_value'] }}" @endif @if (isset($field['max']) && $field['type'] !== 'number') maxlength="{{ $field['max'] }}" @endif class="block w-full rounded-lg border-slate-300 text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        @endif
                    @endif
                    @error($name)<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex flex-col-reverse justify-end gap-3 border-t border-slate-100 pt-5 sm:flex-row">
            <a href="{{ route('resources.index', ['resource' => $resource]) }}" class="rounded-lg px-4 py-2.5 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancelar</a>
            <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">{{ $editing ? 'Guardar cambios' : 'Registrar' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-select-filter]').forEach((wrapper) => {
    const search = wrapper.querySelector('input[type="search"]');
    const select = wrapper.querySelector('select');
    const options = [...select.options];

    search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase('es-PE');
        options.forEach((option) => {
            option.hidden = option.value !== '' && term !== '' && !option.text.toLocaleLowerCase('es-PE').includes(term);
        });
    });
});
</script>
@endpush
