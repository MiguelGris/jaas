@extends('layouts.app')
@section('title', 'Generar cuotas')
@section('heading', 'Generar cuotas')
@section('content')
    <h2 class="text-2xl font-bold">Revisar y generar cuotas</h2>
    <p class="mt-2 text-sm text-slate-600">Primero revisa el mes y los servicios. Se crearán únicamente las cuotas que faltan; los pagos y las cuotas existentes se conservan.</p>
    <form id="billing-preview-form" method="GET" action="{{ route('billing.index') }}" class="my-5 grid gap-4 rounded-xl border bg-white p-5 sm:grid-cols-3">
        <div><label for="month" class="block text-sm font-semibold">Mes a emitir</label><input id="month" type="month" name="month" value="{{ $summary['month'] }}" required class="mt-2 w-full rounded-lg border-slate-300"></div>
        <div><label for="billing-customer-search" class="block text-sm font-semibold">Titular (opcional)</label>@include('searchable-select', ['name'=>'customer_id', 'searchId'=>'billing-customer-search', 'options'=>$customers->mapWithKeys(fn($customer)=>[$customer->id=>$customer->customer_code.' · '.$customer->display_name])->all(), 'value'=>$customerId, 'required'=>false, 'placeholder'=>'Todos los titulares'])</div>
        <div><label for="scope" class="block text-sm font-semibold">Qué deseas emitir</label><select id="scope" name="scope" class="mt-2 w-full rounded-lg border-slate-300"><option value="month" @selected($summary['scope'] === 'month')>Solo el mes elegido</option><option value="cycle" @selected($summary['scope'] === 'cycle')>Todo el ciclo que contiene ese mes</option></select></div>
        <button class="self-end rounded-lg bg-sky-700 px-4 py-3 font-semibold text-white">Revisar emisión</button>
    </form>
    @if ($errors->any())<div role="alert" tabindex="-1" class="mb-5 rounded-xl bg-rose-50 p-4 text-rose-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p class="mb-2 font-semibold">Resultado de la revisión: {{ implode(' · ', $summary['months']) }}</p>
    <p id="billing-review-changed" role="status" hidden class="mb-4 rounded-lg bg-amber-50 p-4 text-amber-900">Cambiaste el mes, el alcance o el titular. Pulsa Revisar emisión para actualizar el resultado antes de generar.</p>
    <p class="mb-4 font-semibold">{{ $summary['ready'] }} listas para generar · {{ $summary['existing'] }} ya existentes · {{ $summary['omitted'] }} omitidas</p>
    <div class="space-y-3">@forelse($summary['rows'] as $row)
        <article class="rounded-xl border bg-white p-4"><h3 class="font-semibold">{{ $row['month'] }} · {{ $row['customer'] }} · {{ $row['supply_code'] }}</h3><p class="mt-1 text-sm {{ $row['status'] === 'omitted' ? 'text-rose-700' : 'text-slate-600' }}">{{ $row['reason'] ?? 'Lista para emitir: S/ '.number_format($row['amount'], 2) }}</p></article>
    @empty<p>No hay conexiones para este titular.</p>@endforelse</div>
    @if($summary['ready'] > 0)
        <form id="billing-confirm-form" method="POST" action="{{ route('billing.store') }}" class="mt-5 rounded-xl border bg-white p-5">@csrf
            <input type="hidden" name="scope" value="{{ $summary['scope'] }}"><input type="hidden" name="month" value="{{ $summary['month'] }}"><input type="hidden" name="customer_id" value="{{ $customerId }}">
            <label for="reason" class="block font-semibold">Motivo de la emisión *</label><input id="reason" name="reason" value="{{ old('reason') }}" required minlength="3" maxlength="250" placeholder="Por ejemplo: recuperar el mes no emitido" class="mt-2 w-full rounded-lg border-slate-300">
            <p class="my-3 text-sm text-slate-600">Las cuotas generadas aparecerán como deuda. Revisa el mes y el titular antes de confirmar.</p>
            <button class="rounded-lg bg-sky-700 px-4 py-3 font-semibold text-white">Confirmar generación de {{ $summary['ready'] }} cuotas</button>
        </form>
    @endif
    <a class="mt-5 inline-block font-semibold text-sky-700" href="{{ route('resources.index', ['resource'=>'invoices']) }}">Volver a cuotas mensuales</a>
@endsection
@push('scripts')
<script>
(() => {
    const review = document.getElementById('billing-preview-form');
    const changed = () => {
        document.getElementById('billing-review-changed').hidden = false;
        const confirmation = document.getElementById('billing-confirm-form');
        if (confirmation) confirmation.hidden = true;
    };
    review.addEventListener('input', changed);
    review.addEventListener('change', changed);
})();
</script>
@endpush
