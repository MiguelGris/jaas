@extends('layouts.public')

@section('title', 'Resultado de consulta')

@section('content')
    <section class="mx-auto max-w-4xl px-5 py-10 sm:px-8 sm:py-14">
        <a href="{{ route('home') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-800">← Nueva consulta</a>

        @if ($notFound)
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-7 text-center sm:p-10">
                <p class="text-lg font-bold text-amber-900">No encontramos un servicio asociado a ese DNI o RUC.</p>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-amber-800">Verifica el documento ingresado. Si acabas de registrarte, comunícate con la administración para actualizar tus datos.</p>
            </div>
        @else
            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">Resultado de la consulta</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ $customer->isBusiness() ? 'Empresa: '.$customer->display_name : 'Hola, '.$customer->display_name }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $customer->document_label }} consultado: {{ $nationalId }}</p>

                @if ($invoices->isEmpty() && $fines->isEmpty())
                    <div class="mt-7 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center">
                        <p class="text-lg font-bold text-emerald-900">No tienes deudas pendientes.</p>
                        <p class="mt-1 text-sm text-emerald-800">Tus cuotas están pagadas o no hay obligaciones vigentes.</p>
                    </div>
                @else
                    <div class="mt-7 flex flex-col justify-between gap-3 rounded-xl bg-slate-950 p-5 text-white sm:flex-row sm:items-center">
                        <div><p class="text-sm text-slate-300">Total por pagar</p><p class="mt-1 text-3xl font-bold">S/ {{ number_format($totalDue, 2) }}</p></div>
                        <p class="text-sm text-slate-300">{{ $invoices->count() }} {{ Str::plural('cuota', $invoices->count()) }} y {{ $fines->count() }} {{ Str::plural('multa', $fines->count()) }} pendiente(s)</p>
                    </div>
                    @if ($invoices->isNotEmpty())
                    <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Cuota</th><th class="px-4 py-3 font-semibold">Suministro</th><th class="px-4 py-3 font-semibold">Mora</th><th class="px-4 py-3 text-right font-semibold">Saldo</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($invoices as $invoice)
                                        <tr><td class="px-4 py-3.5 font-medium text-slate-700">{{ $invoice->period_starts_on?->translatedFormat('F Y') ?? '—' }}</td><td class="px-4 py-3.5 text-slate-600">{{ $invoice->connection?->supply_code ?? '—' }}</td><td class="px-4 py-3.5 text-slate-600">S/ {{ number_format($invoice->calculated_late_fee, 2) }}</td><td class="px-4 py-3.5 text-right font-semibold text-slate-800">S/ {{ number_format($invoice->balance_due, 2) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    @if ($fines->isNotEmpty())
                    <div class="mt-5 overflow-hidden rounded-xl border border-rose-200">
                        <div class="border-b border-rose-100 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-900">Multas pendientes</div>
                        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-rose-100 text-left text-sm"><tbody class="divide-y divide-rose-100">@foreach ($fines as $fine)<tr><td class="px-4 py-3.5 text-slate-700">{{ $fine->reason ?? 'Multa' }}</td><td class="px-4 py-3.5 text-slate-500">{{ $fine->generated_on?->format('d/m/Y') }}</td><td class="px-4 py-3.5 text-right font-semibold text-slate-800">S/ {{ number_format($fine->balance_due, 2) }}</td></tr>@endforeach</tbody></table></div>
                    </div>
                    @endif
                @endif
            </div>
        @endif
    </section>
@endsection
