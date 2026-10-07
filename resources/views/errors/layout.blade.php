<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.brand-icons')
    <title>@yield('code') · @yield('title') · {{ config('jass.name') }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:1.25rem;background:#f8fafc;color:#0f172a;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:100%;max-width:38rem;padding:clamp(1.5rem,5vw,3rem);border:1px solid #e2e8f0;border-radius:1rem;background:#fff;box-shadow:0 12px 40px rgba(15,23,42,.08);text-align:center}.brand{display:inline-grid;width:3rem;height:3rem;place-items:center;border-radius:.75rem;background:#0ea5e9;color:#fff;font-weight:900}.code{margin:1.5rem 0 .25rem;color:#0284c7;font-size:.875rem;font-weight:800;letter-spacing:.15em}.title{margin:.25rem 0;font-size:clamp(1.5rem,6vw,2.25rem)}.message{margin:1rem auto 1.75rem;max-width:30rem;color:#64748b;line-height:1.6}.button{display:inline-block;padding:.75rem 1.1rem;border-radius:.6rem;background:#0f172a;color:#fff;text-decoration:none;font-weight:700}.button:focus-visible{outline:3px solid #7dd3fc;outline-offset:3px}
    </style>
</head>
<body>
    <main class="card">
        <img src="{{ asset('brand/symbol.svg') }}" alt="{{ config('jass.name') }}" width="64" height="64">
        <p class="code">ERROR @yield('code')</p>
        <h1 class="title">@yield('title')</h1>
        <p class="message">@yield('message')</p>
        <a class="button" href="{{ url('/') }}">Volver al inicio</a>
    </main>
</body>
</html>
