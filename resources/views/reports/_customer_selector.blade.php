@php($selectorId = $selectorId ?? 'report-customer')

<div data-customer-report-selector>
    <label class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $selectorId }}-search">Buscar cliente</label>
    <div class="mt-1">
        @include('searchable-select', [
            'name' => 'customer_id',
            'searchId' => $selectorId.'-search',
            'options' => $reportCustomers->mapWithKeys(fn ($customer) => [
                $customer->id => $customer->customer_code.' · '.$customer->document_label.' '.($customer->national_id ?: 'sin registrar').' · '.$customer->display_name,
            ])->all(),
            'value' => old('customer_id'),
            'required' => true,
            'placeholder' => 'DNI, código, nombres o apellidos; RUC o razón social',
        ])
    </div>

    @if ($reportCustomers->isEmpty())
        <p class="mt-2 text-xs font-medium text-amber-700">No hay clientes registrados.</p>
    @else
        <p class="mt-1 text-xs text-slate-500">Escribe para buscar y selecciona una coincidencia por su código, DNI o RUC.</p>
    @endif
</div>
