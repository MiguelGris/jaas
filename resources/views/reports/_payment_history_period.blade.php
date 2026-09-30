@php($periodId = $periodId ?? 'payment-history-period')

<div class="mt-3" data-payment-history-period>
    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $periodId }}-type">Periodo</label>
    <select id="{{ $periodId }}-type" name="period_type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" data-period-type>
        <option value="all">Todo el tiempo</option>
        <option value="year">Año completo</option>
        <option value="range">Rango de fechas</option>
    </select>

    <div class="mt-3 hidden" data-period-year>
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $periodId }}-year">Año</label>
        <input id="{{ $periodId }}-year" type="number" name="year" value="{{ now()->year }}" min="2000" max="2100" step="1" disabled class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
    </div>

    <div class="mt-3 hidden grid-cols-2 gap-3" data-period-range>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $periodId }}-from">Desde</label>
            <input id="{{ $periodId }}-from" type="date" name="from" disabled class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="{{ $periodId }}-to">Hasta</label>
            <input id="{{ $periodId }}-to" type="date" name="to" disabled class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-payment-history-period]').forEach((wrapper) => {
                const type = wrapper.querySelector('[data-period-type]');
                const yearContainer = wrapper.querySelector('[data-period-year]');
                const year = yearContainer.querySelector('input');
                const rangeContainer = wrapper.querySelector('[data-period-range]');
                const rangeInputs = [...rangeContainer.querySelectorAll('input')];

                const updatePeriod = () => {
                    const usesYear = type.value === 'year';
                    const usesRange = type.value === 'range';
                    yearContainer.classList.toggle('hidden', !usesYear);
                    year.disabled = !usesYear;
                    year.required = usesYear;
                    rangeContainer.classList.toggle('hidden', !usesRange);
                    rangeContainer.classList.toggle('grid', usesRange);
                    rangeInputs.forEach((input) => {
                        input.disabled = !usesRange;
                        input.required = usesRange;
                    });
                };

                type.addEventListener('change', updatePeriod);
                updatePeriod();
            });
        </script>
    @endpush
@endonce
