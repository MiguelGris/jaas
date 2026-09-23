@extends('layouts.app')

@section('title', 'Lector de asistencia')
@section('heading', 'Lector de asistencia')

@section('content')
    <div class="mb-6">
        <p class="text-sm font-medium uppercase tracking-widest text-sky-700">Asambleas</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Registro rápido de asistencia</h2>
        <p class="mt-1 text-sm text-slate-500">Conecta el lector de código de barras, selecciona la asamblea y escanea el DNI. Al confirmar un nombre, el campo queda listo para la siguiente lectura.</p>
    </div>

    @if ($assemblies->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900"><p class="font-bold">No hay asambleas programadas.</p><p class="mt-1 text-sm">Crea una asamblea antes de usar el lector.</p></div>
    @else
        <form id="scanner-form" method="POST" action="{{ route('attendance.scan') }}" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            <div>
                <label for="assembly_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Asamblea</label>
                <select id="assembly_id" name="assembly_id" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500" required>
                    <option value="">Selecciona una asamblea</option>
                    @foreach ($assemblies as $assembly)
                        <option value="{{ $assembly->id }}" @selected((int) $selectedAssemblyId === $assembly->id)>{{ $assembly->assembly_code }} · {{ $assembly->held_on->format('d/m/Y') }}{{ $assembly->place ? ' · '.$assembly->place : '' }}</option>
                    @endforeach
                </select>
                @error('assembly_id')<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5">
                <label for="barcode" class="mb-1.5 block text-sm font-semibold text-slate-700">Lectura del DNI</label>
                <input id="barcode" name="barcode" inputmode="numeric" autocomplete="off" autofocus placeholder="Escanea el código o escribe el DNI" class="block w-full rounded-lg border-slate-300 px-4 py-3 text-lg font-semibold tracking-wider shadow-sm focus:border-sky-500 focus:ring-sky-500" required>
                <p class="mt-2 text-sm text-slate-500">Los lectores USB/Bluetooth suelen actuar como teclado: escanea y presiona Enter. También puedes usar la cámara si tu navegador admite códigos PDF417.</p>
                @error('barcode')<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                <button class="rounded-lg bg-sky-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-sky-700">Registrar asistencia</button>
                <button id="start-camera" type="button" class="rounded-lg border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Usar cámara</button>
            </div>
            <p id="camera-message" class="mt-3 hidden text-sm font-medium"></p>
            <video id="camera-preview" class="mt-4 hidden w-full rounded-xl bg-slate-950" autoplay playsinline muted></video>
        </form>
    @endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('scanner-form');
    const input = document.getElementById('barcode');
    const cameraButton = document.getElementById('start-camera');
    const video = document.getElementById('camera-preview');
    const message = document.getElementById('camera-message');

    if (!form || !input) return;

    input.focus();
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && input.value.trim() !== '') {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    cameraButton?.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            message.textContent = 'La cámara no puede leer códigos en este navegador. Usa un lector de código de barras o escribe el DNI.';
            message.className = 'mt-3 text-sm font-medium text-amber-700';
            return;
        }

        try {
            const detector = new BarcodeDetector({ formats: ['pdf417'] });
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
            video.srcObject = stream;
            video.classList.remove('hidden');
            cameraButton.disabled = true;
            cameraButton.textContent = 'Leyendo…';

            const detect = async () => {
                const codes = await detector.detect(video);
                if (codes.length > 0) {
                    input.value = codes[0].rawValue;
                    stream.getTracks().forEach((track) => track.stop());
                    form.requestSubmit();
                    return;
                }
                requestAnimationFrame(detect);
            };
            requestAnimationFrame(detect);
        } catch (error) {
            message.textContent = 'No se pudo usar la cámara. Revisa el permiso del navegador o usa un lector externo.';
            message.className = 'mt-3 text-sm font-medium text-rose-700';
        }
    });
})();
</script>
@endpush
