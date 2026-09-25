@extends('layouts.public')

@section('title', 'Acceso administrativo')

@section('content')
    <section class="mx-auto flex min-h-[calc(100vh-17rem)] max-w-md items-center px-5 py-12 sm:px-8">
        <div class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">Área administrativa</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Iniciar sesión</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Ingresa con tu cuenta de personal autorizado.</p>

            <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
                @csrf
                <div><label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Correo electrónico</label><input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="block w-full rounded-lg border-slate-300 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">@error('email')<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Contraseña</label><input id="password" name="password" type="password" autocomplete="current-password" required class="block w-full rounded-lg border-slate-300 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">@error('password')<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">Recordar mi sesión</label>
                <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Ingresar al panel</button>
            </form>
        </div>
    </section>
@endsection
