@extends('layouts.app')

@php
    $editing = $record !== null;
    $versioningRate = $editing && in_array($resource, ['rates', 'late-fee-settings'], true);
@endphp
@section('title', ($versioningRate ? 'Nueva versión de ' : ($editing ? 'Editar ' : 'Registrar ')).Str::lower($definition['singular']))
@section('heading', $versioningRate ? 'Nueva versión de '.Str::lower($definition['singular']) : ($editing ? 'Editar '.$definition['singular'] : 'Registrar '.$definition['singular']))

@section('content')
    @if ($resource === 'assembly-attendances')
        <p class="mb-4 rounded-lg bg-sky-50 p-4 text-sm">El padrón se prepara automáticamente. Registrar asistencia actualiza al cliente seleccionado y no crea una fila duplicada.</p>
    @elseif ($resource === 'assemblies')
        <p class="mb-4 rounded-lg bg-sky-50 p-4 text-sm">Programada: registra asistencia. Realizada: cierra la lista y genera multas. Reabrir o cancelar anula las multas pendientes; si hay cobros, primero revisa y anula sus recibos.</p>
    @elseif ($resource === 'connection-usage-types')
        <p class="mb-4 rounded-lg bg-sky-50 p-4 text-sm">La conexión recibe uso residencial al registrarse. Para cambiar de uso, termina la vigencia anterior y comienza la siguiente al día siguiente. Las fechas no deben cruzarse.</p>
    @endif
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('resources.index', ['resource' => $resource === 'late-fee-settings' ? 'settings' : $resource]) }}" class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">← Volver</a>
        <div><h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ $versioningRate ? 'Crear nueva versión de '.Str::lower($definition['singular']) : ($editing ? 'Editar '.$definition['singular'] : 'Registrar '.$definition['singular']) }}</h2><p class="mt-1 text-sm text-slate-500">Los campos marcados con <span class="text-rose-600">*</span> son obligatorios.</p></div>
    </div>

    @if (in_array($resource, ['customers', 'properties', 'connections', 'connection-usage-types']))
        <p class="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
            Alta del servicio: 1. Cliente → 2. Predio (casa o local) → 3. Conexión → 4. Revisar tarifa y cuotas.
            @if ($resource === 'properties')Un predio inactivo no recibirá cuotas.@endif
            @if ($resource === 'customers')EXONERADO libera de las multas correspondientes; el agua se sigue facturando.@endif
            @if ($resource === 'connections')La fecha de instalación impide emitir cuotas de meses anteriores. Al corregirla, revisa también las fechas de la asignación de uso.@endif
            @if ($resource === 'connection-usage-types')El tipo de uso determina la tarifa; las fechas indican cuándo se aplica.@endif
        </p>
    @endif
    @if ($resource === 'settings')
        <p class="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm">El ciclo de pago admite cualquier cantidad de meses enteros positivos y el día de emisión del 1 al 28. La configuración de mora se encuentra en la lista de Configuraciones. La última emisión manual es un registro automático de consulta.</p>
    @endif
    @if ($versioningRate)
        <div class="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Indica una nueva fecha en <strong>{{ $resource === 'late-fee-settings' ? 'Inicio de vigencia' : 'Vigente desde' }}</strong>. La versión actual se cerrará el día anterior y conservará sus importes históricos.
        </div>
    @endif

    @if ($resource === 'late-fee-settings')
        @include('resources.mora-history')
    @endif
    @if ($resource === 'settings' && $record?->key === 'billing_period_months')
        @include('resources.cycle-schedule')
    @endif

    <form method="POST" action="{{ $editing ? route('resources.update', ['resource' => $resource, 'record' => $record->getKey()]) : route('resources.store', ['resource' => $resource]) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @if ($errors->any())<div role="alert" tabindex="-1" class="mb-5 rounded-lg bg-rose-50 p-4 text-rose-800">Revisa los campos indicados; tus datos se conservaron.</div>@endif
        @if ($editing) @method('PUT') @endif

        @if ($resource === 'settings' && $record?->key === 'billing_period_months')
            <div class="mb-5">
                <label for="cycle_start_month" class="block text-sm font-semibold">Mes de inicio del nuevo ciclo (opcional)</label>
                <input id="cycle_start_month" name="cycle_start_month" type="month" value="{{ old('cycle_start_month') }}" class="mt-2 rounded-lg border-slate-300">
                <p class="mt-2 text-sm text-slate-600">En blanco: comienza inmediatamente después del ciclo activo. Si eliges otro mes, debe ser el inicio de un ciclo posterior. Antes de emitir la primera cuota puedes elegir libremente el mes inicial.</p>
                @error('cycle_start_month')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
            </div>
        @endif
        <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
            @foreach ($definition['fields'] as $name => $field)
                @continue($field['readonly'] ?? false)
                @php
                    $value = $field['type'] === 'password'
                        ? null
                        : old($name, $editing ? $record->getAttribute($name) : ($field['default'] ?? null));
                    if ($resource === 'late-fee-settings' && $editing && in_array($name, ['starts_on', 'ends_on'], true)) $value = old($name);
                    if ($resource === 'settings' && $editing && $name === 'description') $value = old($name, $record->displayDescription());
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
                    $isSearchableSelect = $field['type'] === 'select'
                        && ! isset($field['choices'])
                        && ($field['searchable'] ?? count($options[$name] ?? []) > 20);
                    $customerFieldGroup = $resource === 'customers'
                        ? match ($name) {
                            'first_name', 'last_name', 'birth_date' => 'person',
                            'business_name' => 'business',
                            default => null,
                        }
                        : null;
                    $customerRequiredGroup = $resource === 'customers'
                        ? match ($name) {
                            'first_name', 'last_name' => 'person',
                            'national_id', 'business_name' => 'business',
                            default => null,
                        }
                        : null;
                @endphp
                <div @class(['md:col-span-2' => $wide]) @if ($customerFieldGroup) data-customer-field="{{ $customerFieldGroup }}" @endif>
                    @if ($field['type'] === 'checkbox')
                        <input type="hidden" name="{{ $name }}" value="0">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) old($name, $editing ? $record->getAttribute($name) : ($field['default'] ?? false))) class="size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            {{ $field['label'] }}
                        </label>
                    @else
                        <label for="{{ $isSearchableSelect ? $name.'-search' : $name }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $field['label'] }} @if ($isRequired)<span class="text-rose-600">*</span>@elseif ($customerRequiredGroup)<span class="text-rose-600" data-customer-required="{{ $customerRequiredGroup }}">*</span>@endif</label>
                        @if ($editing && $field['type'] === 'password')<p class="mb-2 text-xs text-slate-500">Déjala en blanco para conservar la contraseña actual.</p>@endif
                        @if ($resource === 'settings' && $editing && $name === 'key')
                            <input type="hidden" name="key" value="{{ $record->key }}">
                            <p class="rounded-lg bg-slate-50 px-4 py-3 text-sm">{{ $record->displayName() }}</p>
                        @elseif ($resource === 'settings' && $record?->key === 'billing_period_months' && $name === 'value')
                            <input type="number" id="value" name="value" min="1" step="1" required value="{{ $value }}" class="block w-full rounded-lg border-slate-300 text-sm">
                            <p class="mt-1 text-sm text-slate-500">Duración en meses: por ejemplo 1, 4 u 8.</p>
                        @elseif ($field['type'] === 'textarea')
                            <textarea id="{{ $name }}" name="{{ $name }}" rows="4" @required($isRequired) class="block w-full rounded-lg border-slate-300 text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">{{ $value }}</textarea>
                        @elseif ($field['type'] === 'select')
                            @if (! $isSearchableSelect)
                                <select id="{{ $name }}" name="{{ $name }}" @required($isRequired) class="block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                                    @if (! $field['required'])<option value="">Selecciona una opción</option>@endif
                                    @foreach ($options[$name] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @else
                                @include('searchable-select', [
                                    'name' => $name,
                                    'searchId' => $name.'-search',
                                    'options' => $options[$name],
                                    'value' => $value,
                                    'required' => $isRequired,
                                    'placeholder' => 'Buscar '.Str::lower($field['label']).'…',
                                ])
                            @endif
                        @else
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] }}" value="{{ $value }}" @required($isRequired) @if ($field['type'] === 'password') autocomplete="new-password" @endif @if ($field['type'] === 'number') step="{{ $field['step'] ?? '0.01' }}" @endif @if (isset($field['min'])) min="{{ $field['min'] }}" @endif @if (isset($field['max_value'])) max="{{ $field['max_value'] }}" @endif @if (isset($field['max']) && $field['type'] !== 'number') maxlength="{{ $field['max'] }}" @endif class="block w-full rounded-lg border-slate-300 text-sm shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        @endif
                    @endif
                    @error($name)<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex flex-col-reverse justify-end gap-3 border-t border-slate-100 pt-5 sm:flex-row">
            <a href="{{ route('resources.index', ['resource' => $resource === 'late-fee-settings' ? 'settings' : $resource]) }}" class="rounded-lg px-4 py-2.5 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancelar</a>
            <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">{{ $versioningRate ? 'Crear nueva versión' : ($editing ? 'Guardar cambios' : 'Registrar') }}</button>
        </div>
    </form>
@endsection

@if ($resource === 'customers')
    @push('scripts')
        <script>
            (() => {
                const type = document.getElementById('customer_type');
                const documentInput = document.getElementById('national_id');
                const status = document.getElementById('customer_status_id');
                if (!type || !documentInput) return;

                const updateCustomerFields = () => {
                    const isBusiness = type.value === 'BUSINESS';

                    document.querySelectorAll('[data-customer-field]').forEach((wrapper) => {
                        const visible = wrapper.dataset.customerField === (isBusiness ? 'business' : 'person');
                        wrapper.classList.toggle('hidden', !visible);
                        wrapper.querySelectorAll('input, select, textarea').forEach((input) => {
                            input.disabled = !visible;
                            input.required = visible && ['first_name', 'last_name', 'business_name'].includes(input.name);
                        });
                    });

                    document.querySelectorAll('[data-customer-required]').forEach((marker) => {
                        marker.classList.toggle('hidden', marker.dataset.customerRequired !== (isBusiness ? 'business' : 'person'));
                    });

                    documentInput.required = isBusiness;
                    documentInput.maxLength = isBusiness ? 11 : 8;
                    documentInput.pattern = isBusiness ? '[0-9]{11}' : '[0-9]{8}';
                    documentInput.placeholder = isBusiness ? 'RUC de 11 dígitos' : 'DNI de 8 dígitos';

                    if (status) {
                        [...status.options].forEach((option) => {
                            const normalized = option.textContent.trim().toLocaleLowerCase('es-PE');
                            option.disabled = isBusiness && normalized === 'exonerado';
                        });
                        if (status.selectedOptions[0]?.disabled) {
                            status.value = [...status.options].find((option) => !option.disabled)?.value ?? '';
                        }
                    }
                };

                type.addEventListener('change', updateCustomerFields);
                updateCustomerFields();
            })();
        </script>
    @endpush
@endif
