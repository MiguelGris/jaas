@extends('layouts.app')
@section('title', 'Configuraciones')
@section('heading', 'Configuraciones')
@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-bold text-slate-900">Configuraciones</h2><p class="mt-1 text-sm text-slate-500">{{ $configurationRows->count() }} opciones, ordenadas alfabéticamente.</p></div>
        @if (App\Support\ResourceAccess::allows(auth()->user(), 'settings', 'create'))
            <a href="{{ route('resources.create', ['resource' => 'settings']) }}" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white">+ Registrar configuración</a>
        @endif
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Configuración</th><th class="px-5 py-3">Valor</th><th class="px-5 py-3">Descripción</th><th class="px-5 py-3 text-right">Acciones</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($configurationRows as $row)
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-3.5 text-slate-600">{{ $row['name'] }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $row['value'] }}</td>
                            <td class="max-w-md px-5 py-3.5 text-slate-600">{{ $row['description'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                <a href="{{ $row['show'] }}" class="font-semibold text-slate-600 hover:text-sky-700">Ver</a>
                                @if ($row['edit'])<a href="{{ $row['edit'] }}" class="ml-3 font-semibold text-sky-700">{{ $row['edit_label'] ?? 'Editar' }}</a>@endif
                                @if ($row['automatic'])<span class="ml-3 text-slate-500">Automático</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No hay configuraciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
