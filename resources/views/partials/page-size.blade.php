<form method="GET" class="my-4 flex flex-wrap items-center gap-3">
    @foreach (request()->except(['page', 'per_page', 'history_page']) as $key => $value)
        @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <label for="list-page-size" class="text-sm font-semibold">Por página</label>
    <select id="list-page-size" name="per_page" class="rounded-lg border-slate-300">
        @include('partials.page-size-options', ['selectedSize' => $paginator->perPage()])
    </select>
    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Aplicar</button>
</form>
