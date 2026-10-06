<section class="mb-5 overflow-x-auto rounded-xl border border-slate-200 bg-white p-5">
    <h3 class="font-bold">Historial de versiones de mora</h3>
    <p class="my-3 text-sm text-slate-600">Cada ciclo conserva la versión vigente al comenzar. La mora se calcula una sola vez por ciclo y por mes de atraso, después de la gracia. Cero meses de gracia permite pagar hasta el último día del ciclo. Las nuevas versiones no cambian los ciclos ya emitidos.</p>
    <table class="w-full text-left text-sm">
        <thead><tr><th class="p-2">Desde</th><th class="p-2">Hasta</th><th class="p-2">Mora mensual por ciclo</th><th class="p-2">Gracia</th></tr></thead>
        <tbody>
        @forelse (App\Models\LateFeeSetting::query()->orderByDesc('starts_on')->orderByDesc('id')->get() as $version)
            <tr class="border-t"><td class="p-2">{{ $version->starts_on->format('d/m/Y') }}</td><td class="p-2">{{ $version->ends_on?->format('d/m/Y') ?? 'Sin fecha final' }}</td><td class="p-2">S/ {{ number_format((float) $version->monthly_amount, 2) }}</td><td class="p-2">{{ $version->grace_months }} mes(es)</td></tr>
        @empty
            <tr><td colspan="4" class="p-2">Todavía no hay versiones registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
