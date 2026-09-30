@php
    $inputName = $name;
    $searchId = $searchId ?? $inputName.'-search';
    $resultsId = $searchId.'-results';
    $selectedValue = old($inputName, $value ?? null);
    $selectedLabel = $selectedValue !== null && isset($options[$selectedValue]) ? $options[$selectedValue] : '';
    $isRequired = $required ?? false;
@endphp

<div class="relative" data-searchable-select>
    <div class="flex">
        <input id="{{ $searchId }}" type="search" value="{{ $selectedLabel }}" @required($isRequired) placeholder="{{ $placeholder ?? 'Escribe para buscar…' }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $resultsId }}" class="block min-w-0 flex-1 rounded-l-lg border-slate-300 text-sm shadow-sm outline-none transition focus:z-10 focus:border-sky-500 focus:ring-2 focus:ring-sky-100" data-searchable-input>
        <button type="button" title="Mostrar todas las opciones" aria-label="Mostrar todas las opciones" aria-controls="{{ $resultsId }}" aria-expanded="false" class="rounded-r-lg border border-l-0 border-slate-300 bg-slate-50 px-3 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:z-10 focus:outline-none focus:ring-2 focus:ring-sky-100" data-searchable-toggle>⌄</button>
    </div>
    <input id="{{ $inputName }}" type="hidden" name="{{ $inputName }}" value="{{ $selectedValue }}" data-searchable-value>

    <div id="{{ $resultsId }}" class="absolute z-30 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl" role="listbox" data-searchable-results>
        @foreach ($options as $optionValue => $optionLabel)
            <button type="button" role="option" class="hidden w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-sky-50 hover:text-sky-800" data-searchable-option data-value="{{ $optionValue }}" data-label="{{ $optionLabel }}">{{ $optionLabel }}</button>
        @endforeach
        <p class="hidden px-3 py-2 text-sm text-slate-500" data-searchable-empty>No se encontraron coincidencias.</p>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-searchable-select]').forEach((wrapper) => {
                const search = wrapper.querySelector('[data-searchable-input]');
                const toggle = wrapper.querySelector('[data-searchable-toggle]');
                const selectedValue = wrapper.querySelector('[data-searchable-value]');
                const results = wrapper.querySelector('[data-searchable-results]');
                const empty = wrapper.querySelector('[data-searchable-empty]');
                const options = [...wrapper.querySelectorAll('[data-searchable-option]')];
                const normalize = (text) => text.toLocaleLowerCase('es-PE').normalize('NFD').replace(/[\u0300-\u036f]/g, '');

                const closeResults = () => {
                    results.classList.add('hidden');
                    search.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-expanded', 'false');
                };

                const showAllOptions = () => {
                    options.forEach((option) => option.classList.remove('hidden'));
                    empty.classList.toggle('hidden', options.length !== 0);
                    results.classList.remove('hidden');
                    search.setAttribute('aria-expanded', 'true');
                    toggle.setAttribute('aria-expanded', 'true');
                };

                const selectOption = (option) => {
                    selectedValue.value = option.dataset.value;
                    search.value = option.dataset.label;
                    search.setCustomValidity('');
                    selectedValue.dispatchEvent(new Event('change', { bubbles: true }));
                    closeResults();
                };

                const filterResults = () => {
                    selectedValue.value = '';
                    selectedValue.dispatchEvent(new Event('change', { bubbles: true }));
                    const term = normalize(search.value.trim());
                    search.setCustomValidity(term === '' && !search.required ? '' : 'Selecciona una opción de los resultados.');

                    if (term === '') {
                        showAllOptions();
                        return;
                    }

                    let matches = 0;
                    options.forEach((option) => {
                        const visible = normalize(option.dataset.label).includes(term);
                        option.classList.toggle('hidden', !visible);
                        if (visible) matches++;
                    });
                    empty.classList.toggle('hidden', matches !== 0);
                    results.classList.remove('hidden');
                    search.setAttribute('aria-expanded', 'true');
                    toggle.setAttribute('aria-expanded', 'true');
                };

                search.addEventListener('input', filterResults);
                search.addEventListener('focus', () => {
                    if (search.value.trim() !== '' && selectedValue.value === '') {
                        filterResults();
                    } else {
                        showAllOptions();
                    }
                });
                search.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') closeResults();
                    if (event.key === 'Enter' && selectedValue.value === '') {
                        const firstMatch = options.find((option) => !option.classList.contains('hidden'));
                        if (firstMatch) {
                            event.preventDefault();
                            selectOption(firstMatch);
                        }
                    }
                });
                search.addEventListener('blur', () => setTimeout(closeResults, 150));
                toggle.addEventListener('mousedown', (event) => event.preventDefault());
                toggle.addEventListener('click', () => {
                    if (results.classList.contains('hidden')) {
                        showAllOptions();
                        search.focus();
                    } else {
                        closeResults();
                    }
                });
                options.forEach((option) => option.addEventListener('click', () => selectOption(option)));
            });
        </script>
    @endpush
@endonce
