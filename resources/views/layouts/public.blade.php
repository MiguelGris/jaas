<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Consulta de deudas') · JASS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-18 max-w-6xl items-center justify-between px-5 sm:px-8">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2 sm:gap-3">
                <span class="grid size-9 place-items-center rounded-xl bg-sky-600 font-black text-white">J</span>
                <span class="min-w-0"><span class="block text-sm font-bold tracking-wide text-slate-900">JASS</span><span class="hidden truncate text-xs text-slate-500 sm:block">Administración de agua</span></span>
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="shrink-0 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 sm:px-3.5 sm:text-sm">Ir al panel</a>
            @else
                <a href="{{ route('login') }}" class="shrink-0 rounded-lg px-2.5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 sm:px-3.5 sm:text-sm">Acceso administrativo</a>
            @endauth
        </div>
    </header>

    <main>
        @if (session('success'))
            <div class="mx-auto mt-6 max-w-3xl px-5 sm:px-8"><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div></div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-slate-200 bg-white py-7">
        <p class="mx-auto max-w-6xl px-5 text-center text-xs text-slate-500 sm:px-8">JASS · Consulta de pagos y deudas del servicio.</p>
    </footer>
</body>
</html>
