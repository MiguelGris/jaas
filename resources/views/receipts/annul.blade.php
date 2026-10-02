@extends('layouts.app')

@section('title', 'Anular pago')
@section('heading', 'Anular pago')

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('resources.show', ['resource' => 'payments', 'record' => $payment->getKey()]) }}" class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">← Volver</a>
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Anular {{ $payment->receipt_code }}</h2>
            <p class="mt-1 text-sm text-slate-500">El recibo se conservará como anulado y los cargos pagados volverán a quedar pendientes.</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.75fr)]">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="font-semibold text-slate-800">Datos del pago</h3>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $payment->customer?->display_name ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fecha</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $payment->paid_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</dt><dd class="mt-1 text-lg font-bold text-slate-900">S/ {{ number_format((float) $payment->amount, 2) }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Operación</dt><dd class="mt-1 text-sm font-medium text-slate-800">{{ $payment->operation_number ?: '—' }}</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('receipts.annul', ['payment' => $payment->getKey()]) }}" class="rounded-xl border border-rose-200 bg-rose-50 p-5 shadow-sm sm:p-6">
            @csrf
            @method('PATCH')
            <h3 class="font-semibold text-rose-900">Confirmar anulación</h3>
            <p class="mt-1 text-sm text-rose-700">Esta acción afecta caja, reportes y el estado de las cuotas o multas relacionadas.</p>
            <label for="void-reason" class="mt-5 block text-sm font-semibold text-slate-700">Motivo <span class="text-rose-600">*</span></label>
            <textarea id="void-reason" name="void_reason" rows="4" required minlength="3" maxlength="250" class="mt-1.5 block w-full rounded-lg border-rose-300 bg-white text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500" placeholder="Describe brevemente el error cometido">{{ old('void_reason') }}</textarea>
            @error('void_reason')<p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            @error('payment')<p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('resources.show', ['resource' => 'payments', 'record' => $payment->getKey()]) }}" class="rounded-lg px-4 py-2.5 text-center text-sm font-semibold text-slate-600 transition hover:bg-white">Cancelar</a>
                <button type="submit" class="rounded-lg bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-800">Anular pago</button>
            </div>
        </form>
    </div>
@endsection
