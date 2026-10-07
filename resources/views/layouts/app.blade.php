<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.brand-icons')
    <title>@yield('title', 'Panel') · {{ config('jass.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/ux.css') }}">
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <a href="#main-content" class="skip-link">Saltar al contenido</a>
    <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
        <aside class="app-sidebar border-b border-slate-200 bg-slate-950 text-slate-300 lg:min-h-screen lg:border-b-0 lg:border-r lg:border-slate-800">
            <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-6 sm:py-5">
                <div class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset('brand/symbol.svg') }}" alt="" width="40" height="40" class="size-10 shrink-0 rounded-xl bg-white p-0.5">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold tracking-wide text-white">{{ config('jass.name') }}</p>
                        <p class="truncate text-xs text-slate-400">Agua y saneamiento</p>
                    </div>
                </div>
                <button id="mobile-menu-toggle" type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 lg:hidden" aria-controls="sidebar-navigation" aria-expanded="false">
                    <span aria-hidden="true">☰</span><span>Menú</span>
                </button>
            </div>

            <nav data-user-id="{{ auth()->id() }}" id="sidebar-navigation" aria-label="Navegación principal" class="hidden max-h-[calc(100vh-4.5rem)] overflow-y-auto px-3 pb-6 lg:block lg:max-h-[calc(100vh-5rem)]">
                <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif @class([
                    'mb-3 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                    'bg-sky-500 text-white shadow-sm' => request()->routeIs('dashboard'),
                    'text-slate-300 hover:bg-slate-800 hover:text-white' => !request()->routeIs('dashboard'),
                ])>
                    <span aria-hidden="true">⌂</span> Panel principal
                </a>

<section id="favorite-navigation" class="mb-4" hidden><h2 class="px-3 py-2 text-sm font-bold text-white">Mis favoritos</h2><div id="favorite-links"></div></section>
                @foreach ($navigation as $group)
                    @php
                        $group['items'] = array_filter($group['items'], fn ($item) => App\Support\ResourceAccess::allowsNavigation(auth()->user(), $item));
                    @endphp
                    @continue(empty($group['items']))
                    <details class="group mb-1" open>
                        <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-slate-900 hover:text-slate-300">
                            {{ $group['title'] }}
                            <span class="text-base transition group-open:rotate-90">›</span>
                        </summary>
                        <div class="mt-1 space-y-0.5">
                            @foreach ($group['items'] as $item)
                                @php
                                    $canAccess = App\Support\ResourceAccess::allowsNavigation(auth()->user(), $item);
                                @endphp
                                @continue(! $canAccess)
                                @php
                                    $resource = $item['resource'] ?? null;
                                    $href = $resource
                                        ? route('resources.index', ['resource' => $resource])
                                        : route($item['route_name']).(isset($item['fragment']) ? '#'.$item['fragment'] : '');
                                    $isActive = $resource
                                        ? request()->route('resource') === $resource || ($resource === 'settings' && request()->routeIs('settings.mora.*'))
                                        : isset($item['active']) && request()->routeIs($item['active']);
                                @endphp
                                <a data-favorite-link href="{{ $href }}" @if($isActive) aria-current="page" @endif @class([
                                    'block rounded-lg px-3 py-2 text-sm transition',
                                    'bg-slate-800 font-medium text-white' => $isActive,
                                    'text-slate-400 hover:bg-slate-900 hover:text-white' => ! $isActive,
                                ])>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0">
            <header class="flex min-h-20 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-8">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-widest text-slate-400">Sistema de gestión</p>
                    <h1 class="mt-1 truncate text-base font-semibold text-slate-800 sm:text-lg">@yield('heading', 'Panel principal')</h1>
                </div>
                <div class="flex shrink-0 items-center gap-1 sm:gap-3">
                    <details class="group relative">
                        <summary class="flex max-w-40 cursor-pointer list-none items-center gap-1 rounded-lg px-2 py-2 text-right text-xs text-slate-500 transition hover:bg-slate-100 sm:max-w-56 sm:gap-2 sm:px-3 [&::-webkit-details-marker]:hidden">
                            <span class="min-w-0"><span class="block truncate font-semibold text-slate-700">{{ auth()->user()->name }}</span><span class="hidden truncate sm:block">{{ App\Http\Controllers\Web\JassPageController::roleLabel(auth()->user()->role?->name) }}</span></span>
                            <span aria-hidden="true" class="text-sm transition group-open:rotate-180">⌄</span>
                        </summary>
                        <div class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 text-sm shadow-xl">
                            <a href="{{ route('password.edit') }}" class="block px-4 py-2.5 font-medium text-slate-700 transition hover:bg-slate-50 hover:text-sky-700">Cambiar contraseña</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full px-4 py-2.5 text-left font-medium text-rose-700 transition hover:bg-rose-50">Cerrar sesión</button></form>
                        </div>
                    </details>
                </div>
            </header>

            <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-7xl p-4 sm:p-8">
                @if (session('success'))
                    <div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                        <span class="font-bold">✓</span><span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        <span class="font-bold">!</span><span>{{ session('error') }}</span>
                    </div>
                @endif

                @if(config('jass.training_mode') && app()->environment('local', 'testing'))<aside role="status" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 font-semibold">Modo de capacitación · Entorno local. Los cambios se guardan en la base configurada para esta instalación. Usa una base separada de pruebas.</aside>@endif
                @yield('content')
            </main>
        </div>
    </div>
    <script>
        (() => {
            const navigation = document.getElementById('sidebar-navigation');
            const menuToggle = document.getElementById('mobile-menu-toggle');
            const storageKey = 'jass.sidebar-navigation.scroll-top';

            if (!navigation) return;

            menuToggle?.addEventListener('click', () => {
                const opening = navigation.classList.contains('hidden');
                navigation.classList.toggle('hidden');
                menuToggle.setAttribute('aria-expanded', String(opening));
            });
            navigation.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && window.matchMedia('(max-width: 1023px)').matches) {
                    navigation.classList.add('hidden');
                    menuToggle?.setAttribute('aria-expanded', 'false');
                    menuToggle?.focus();
                }
            });

            try {
                const savedPosition = sessionStorage.getItem(storageKey);
                if (savedPosition !== null) {
                    requestAnimationFrame(() => {
                        navigation.scrollTop = Number(savedPosition);
                    });
                }

                navigation.addEventListener('scroll', () => {
                    sessionStorage.setItem(storageKey, String(navigation.scrollTop));
                }, { passive: true });

                navigation.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', () => {
                        sessionStorage.setItem(storageKey, String(navigation.scrollTop));
                        if (window.matchMedia('(max-width: 1023px)').matches) {
                            navigation.classList.add('hidden');
                            menuToggle?.setAttribute('aria-expanded', 'false');
                        }
                    });
                });
            } catch (_) {
                // The navigation remains fully usable when browser storage is unavailable.
            }
        })();
    </script>
    <script src="{{ asset('js/usability.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
