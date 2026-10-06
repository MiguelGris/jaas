@php
    $cycleService = app(App\Services\BillingCycleService::class);
    $firstPlan = Illuminate\Support\Facades\DB::table('billing_cycle_schedules')->orderBy('starts_on')->first();
    $hasStarted = ! $firstPlan || $firstPlan->starts_on <= today()->toDateString();
    $activeCycle = $hasStarted ? $cycleService->forMonth(today()) : null;
    $plans = Illuminate\Support\Facades\DB::table('billing_cycle_schedules')->where('starts_on', '>', today()->toDateString())->orderBy('starts_on')->get();
@endphp
<section class="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm">
    <h3 class="font-bold">Programación de ciclos</h3>
    @if ($activeCycle)
        <p class="mt-2">Ciclo activo: {{ $activeCycle['start']->format('d/m/Y') }} al {{ $activeCycle['end']->format('d/m/Y') }} · {{ $activeCycle['months'] }} mes(es).</p>
        <p class="mt-2">Próximo inicio disponible: {{ $activeCycle['end']->copy()->addDay()->format('m/Y') }}.</p>
    @else
        <p class="mt-2">La facturación comenzará en el mes programado.</p>
    @endif
    @foreach ($plans as $plan)
        <p class="mt-2 font-semibold">Desde {{ Carbon\Carbon::parse($plan->starts_on)->format('m/Y') }}: ciclos de {{ $plan->months }} mes(es).</p>
    @endforeach
    <p class="mt-2">Los ciclos continúan al año siguiente sin acortarse. Las cuotas ya emitidas mantienen sus fechas y su mora.</p>
</section>
