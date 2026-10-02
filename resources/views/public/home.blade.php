@extends('layouts.public')

@section('title', 'Consulta de deudas')

@section('content')
    <section class="relative overflow-hidden bg-slate-950 py-16 text-white sm:py-24">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(14,165,233,.25),_transparent_40%)]"></div>
        <div class="relative mx-auto grid max-w-6xl gap-12 px-5 sm:px-8 lg:grid-cols-[1.15fr_.85fr] lg:items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[.2em] text-sky-300">Servicio de agua</p>
                <h1 class="mt-4 max-w-xl text-4xl font-bold tracking-tight sm:text-5xl">Consulta tus deudas pendientes.</h1>
                <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">Ingresa tu DNI o RUC para revisar rápidamente las cuotas y otros cargos que tienes por pagar.</p>
            </div>

            <div class="rounded-2xl bg-white p-6 text-slate-900 shadow-2xl shadow-sky-950/40 sm:p-8">
                <h2 class="text-xl font-bold">Consulta de deuda</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Solo necesitas el DNI o RUC del titular del servicio.</p>
                <form method="POST" action="{{ route('debt.lookup') }}" class="mt-6">
                    @csrf
                    <label for="national_id" class="mb-2 block text-sm font-semibold text-slate-700">DNI o RUC del titular</label>
                    <input id="national_id" name="national_id" inputmode="numeric" autocomplete="off" maxlength="11" pattern="([0-9]{8}|[0-9]{11})" required value="{{ old('national_id') }}" placeholder="Ej.: 12345678 o 20123456789" class="block w-full rounded-lg border-slate-300 px-3 py-2.5 text-base shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    @error('national_id')<p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                    <button type="submit" class="mt-5 inline-flex w-full items-center justify-center rounded-lg bg-sky-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Consultar deuda</button>
                </form>
                <p class="mt-5 text-center text-xs leading-5 text-slate-400">Por seguridad, esta consulta muestra únicamente las obligaciones pendientes.</p>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-6xl gap-4 px-5 py-12 sm:grid-cols-3 sm:px-8">
        <div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-800">Consulta simple</p><p class="mt-2 text-sm leading-6 text-slate-500">Usa el DNI o RUC registrado del titular.</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-800">Detalle claro</p><p class="mt-2 text-sm leading-6 text-slate-500">Revisa las fechas de vencimiento e importes de cada cuota o cargo.</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-800">Información actual</p><p class="mt-2 text-sm leading-6 text-slate-500">Los pagos registrados se descuentan del saldo mostrado.</p></div>
    </section>
@endsection
