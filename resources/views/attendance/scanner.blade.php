@extends('layouts.app')

@section('title', 'Lector de asistencia')
@section('heading', 'Lector de asistencia')

@section('content')
    <div class="mb-6">
        <p class="text-sm font-medium uppercase tracking-widest text-sky-700">Asambleas</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Registro rápido de asistencia</h2>
        <p class="mt-1 text-sm text-slate-500">Conecta el lector de código de barras, selecciona la asamblea y escanea el DNI. Al confirmar un nombre, el campo queda listo para la siguiente lectura.</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-300 bg-red-50 px-5 py-7 text-center shadow-sm" role="alert" aria-live="assertive">
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-red-700">Error al registrar asistencia</p>
            <p class="mt-2 text-4xl font-extrabold tracking-tight text-red-950 sm:text-5xl">{{ $errors->first() }}</p>
            <p class="mt-3 text-base font-medium text-red-800">Revisa la asamblea y el DNI, y vuelve a intentar la lectura.</p>
        </div>
    @endif
    @if ($assemblies->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900"><p class="font-bold">No hay asambleas programadas.</p><p class="mt-1 text-sm">Crea una asamblea antes de usar el lector.</p></div>
    @else
        @if (session('attendance_name'))
            <div class="mb-6 rounded-2xl border {{ session('attendance_already_present') ? 'border-sky-300 bg-sky-50' : 'border-emerald-300 bg-emerald-50' }} px-5 py-7 text-center shadow-sm" role="status" aria-live="polite">
                <p class="text-sm font-bold uppercase tracking-[0.2em] {{ session('attendance_already_present') ? 'text-sky-700' : 'text-emerald-700' }}">{{ session('attendance_already_present') ? 'Asistencia ya registrada' : 'Asistencia confirmada' }}</p>
                <p class="mt-2 text-4xl font-extrabold tracking-tight {{ session('attendance_already_present') ? 'text-sky-950' : 'text-emerald-950' }} sm:text-5xl">{{ session('attendance_name') }}</p>
                <p class="mt-3 text-base font-medium {{ session('attendance_already_present') ? 'text-sky-800' : 'text-emerald-800' }}">{{ session('attendance_message') }}</p>
            </div>
        @endif

        <form id="scanner-form" method="POST" action="{{ route('attendance.scan') }}" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            <div>
                @php
                    $assemblyOptions = $assemblies->mapWithKeys(fn ($assembly) => [
                        $assembly->id => $assembly->assembly_code.' · '.$assembly->held_on->format('d/m/Y').($assembly->place ? ' · '.$assembly->place : ''),
                    ])->all();
                    $searchAssemblies = count($assemblyOptions) > 20;
                @endphp
                <label for="{{ $searchAssemblies ? 'attendance-assembly-search' : 'assembly_id' }}" class="mb-1.5 block text-sm font-semibold text-slate-700">Asamblea</label>
                @include('adaptive-select', [
                    'name' => 'assembly_id',
                    'searchId' => 'attendance-assembly-search',
                    'options' => $assemblyOptions,
                    'value' => $selectedAssemblyId,
                    'required' => true,
                    'placeholder' => $searchAssemblies ? 'Código, fecha o lugar de la asamblea' : 'Selecciona una asamblea',
                ])
            </div>

            <div class="mt-5">
                <label for="barcode" class="mb-1.5 block text-sm font-semibold text-slate-700">Lectura del DNI</label>
                <input id="barcode" name="barcode" inputmode="numeric" autocomplete="off" autofocus placeholder="Escanea el código o escribe el DNI" class="block w-full rounded-lg border-slate-300 px-4 py-3 text-lg font-semibold tracking-wider shadow-sm focus:border-sky-500 focus:ring-sky-500" required>
                <p class="mt-2 text-sm text-slate-500">Los lectores USB/Bluetooth suelen actuar como teclado: escanea el código y presiona Enter.</p>
            </div>

            <div class="mt-5">
                <button class="rounded-lg bg-sky-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-sky-700">Registrar asistencia</button>
            </div>
        </form>

        @if ($selectedAssembly)
            <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="attendance-progress-title">
                <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-sky-700">Avance de la asamblea</p>
                        <h3 id="attendance-progress-title" class="mt-1 text-xl font-bold text-slate-900">Asistentes registrados</h3>
                    </div>
                    <p class="rounded-full bg-sky-100 px-4 py-2 text-base font-extrabold text-sky-800">
                        {{ $attendanceCount }} de {{ $attendanceTotal }} registran asistencia
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                        <thead class="bg-white text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="w-20 px-5 py-3 font-semibold">N.°</th>
                                <th class="px-5 py-3 font-semibold">Asistente</th>
                                <th class="w-32 px-5 py-3 text-right font-semibold">Hora</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($attendees as $attendance)
                                <tr>
                                    <td class="px-5 py-3.5 font-semibold text-slate-500">{{ $loop->iteration }}</td>
                                    <td class="px-5 py-3.5 text-base font-semibold text-slate-800">{{ $attendance->customer?->display_name ?? '—' }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono text-sm font-bold text-slate-700">{{ $attendance->attended_at?->format('H:i:s') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500">Aún no se registraron asistentes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('scanner-form');
    const input = document.getElementById('barcode');
    const assembly = document.getElementById('assembly_id');

    if (!form || !input) return;

    assembly?.addEventListener('change', () => {
        if (!assembly.value) return;
        const url = new URL(@json(route('attendance.scanner')), window.location.origin);
        url.searchParams.set('assembly', assembly.value);
        window.location.assign(url.toString());
    });

    input.focus();
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && input.value.trim() !== '') {
            event.preventDefault();
            form.requestSubmit();
        }
    });
})();
</script>
@endpush
