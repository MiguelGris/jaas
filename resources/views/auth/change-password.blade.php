@extends('layouts.app')

@section('title', 'Cambiar contraseña')
@section('heading', 'Cambiar contraseña')

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="mb-6">
            <p class="text-sm font-medium uppercase tracking-widest text-sky-700">Mi cuenta</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Cambiar contraseña</h2>
            <p class="mt-1 text-sm text-slate-500">Confirma tu contraseña actual y establece una nueva de al menos 8 caracteres.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            <div><label for="current_password" class="mb-1.5 block text-sm font-semibold text-slate-700">Contraseña actual</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" class="block w-full rounded-lg border-slate-300 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100" required>@error('current_password')<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Nueva contraseña</label><input id="password" name="password" type="password" autocomplete="new-password" class="block w-full rounded-lg border-slate-300 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100" required>@error('password')<p class="mt-1.5 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirmar nueva contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="block w-full rounded-lg border-slate-300 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100" required></div>
            <div class="flex justify-end border-t border-slate-100 pt-5"><button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Actualizar contraseña</button></div>
        </form>
    </div>
@endsection
