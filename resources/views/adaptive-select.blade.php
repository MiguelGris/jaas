@php
    $useSearch = $searchable ?? count($options) > 20;
    $selectedValue = old($name, $value ?? null);
    $selectId = $selectId ?? $name;
    $isRequired = $required ?? false;
@endphp

@if ($useSearch)
    @include('searchable-select', [
        'name' => $name,
        'searchId' => $searchId ?? $name.'-search',
        'options' => $options,
        'value' => $selectedValue,
        'required' => $isRequired,
        'placeholder' => $placeholder ?? 'Escribe para buscar…',
    ])
@else
    <select id="{{ $selectId }}" name="{{ $name }}" @required($isRequired) class="block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
        <option value="">{{ $placeholder ?? 'Selecciona una opción' }}</option>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selectedValue === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
@endif
