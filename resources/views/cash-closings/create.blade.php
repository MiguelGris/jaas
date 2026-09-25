@extends('layouts.app')

@section('title', 'Nuevo cierre de caja')
@section('heading', 'Nuevo cierre de caja')

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('resources.index', ['resource' => $resource]) }}" class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">← Volver</a>
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Cerrar un periodo</h2>
            <p class="mt-1 text-sm text-slate-500">Los importes se calculan desde el último cierre y no se pueden editar manualmente.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" action="{{ route('resources.create', ['resource' => $resource]) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
            <div>
                <label for="closing-year" class="mb-1.5 block text-sm font-semibold text-slate-700">Año <span class="text-rose-600">*</span></label>
                <input id="closing-year" name="year" type="number" value="{{ $period['year'] }}" required min="2000" max="2100" step="1" inputmode="numeric" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                @foreach ($previewErrors->get('year') as $message)<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@endforeach
            </div>
            <div>
                <label for="closing-month" class="mb-1.5 block text-sm font-semibold text-slate-700">Mes <span class="text-rose-600">*</span></label>
                <input id="closing-month" name="month" type="number" value="{{ $period['month'] }}" required min="1" max="12" step="1" inputmode="numeric" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                @foreach ($previewErrors->get('month') as $message)<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@endforeach
            </div>
            <button type="submit" class="rounded-lg bg-slate-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">Calcular</button>
        </div>
        @foreach ($previewErrors->get('period') as $message)
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">{{ $message }}</p>
        @endforeach
    </form>

    @if ($summary !== null)
        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h3 class="font-semibold text-slate-800">Resumen automático del cierre</h3>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($summary['starts_on'])
                        Movimientos del {{ $summary['starts_on']->format('d/m/Y') }} al {{ $summary['ends_on']->format('d/m/Y') }}.
                    @else
                        Todos los movimientos registrados hasta el {{ $summary['ends_on']->format('d/m/Y') }}.
                    @endif
                </p>
            </div>

            <dl class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bg-white p-5"><dt class="text-sm font-medium text-slate-500">Saldo anterior</dt><dd class="mt-2 text-2xl font-bold text-slate-800">S/ {{ number_format($summary['previous_balance'], 2) }}</dd></div>
                <div class="bg-emerald-50 p-5"><dt class="text-sm font-medium text-emerald-700">Ingresos desde el último cierre</dt><dd class="mt-2 text-2xl font-bold text-emerald-800">S/ {{ number_format($summary['total_income'], 2) }}</dd></div>
                <div class="bg-rose-50 p-5"><dt class="text-sm font-medium text-rose-700">Egresos desde el último cierre</dt><dd class="mt-2 text-2xl font-bold text-rose-800">S/ {{ number_format($summary['total_expense'], 2) }}</dd></div>
                <div class="bg-sky-50 p-5"><dt class="text-sm font-medium text-sky-700">Saldo que se deja</dt><dd class="mt-2 text-2xl font-bold text-sky-800">S/ {{ number_format($summary['balance'], 2) }}</dd></div>
            </dl>

            <form method="POST" action="{{ route('resources.store', ['resource' => $resource]) }}" class="flex flex-col-reverse justify-end gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:px-6">
                @csrf
                <input type="hidden" name="year" value="{{ $summary['year'] }}">
                <input type="hidden" name="month" value="{{ $summary['month'] }}">
                <a href="{{ route('resources.index', ['resource' => $resource]) }}" class="rounded-lg px-4 py-2.5 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancelar</a>
                <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Confirmar cierre</button>
            </form>
        </section>
    @endif
@endsection
