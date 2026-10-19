@extends('layouts.app')
@section('title','Recibos de condonación')
@section('heading','Recibos de condonación')
@section('content')
<h2 class="text-2xl font-bold">Recibos mensuales · campaña #{{ $campaign->id }}</h2><p class="my-3">{{ $campaign->reason }}</p>
@include('partials.page-size', ['paginator' => $waivers])
<div class="space-y-3">@foreach($waivers as $waiver)<article class="rounded-xl border bg-white p-4"><p class="font-semibold">{{ $waiver->invoice->connection->property->customer->display_name }} · {{ $waiver->invoice->period_starts_on->format('m/Y') }}</p><p class="mt-1">{{ $waiver->receipt_code }} · Servicio condonado: S/ {{ number_format($waiver->amount,2) }} · Saldo restante: S/ {{ number_format($waiver->balance_after,2) }}</p><a class="mt-2 inline-block font-semibold text-sky-700" href="{{ route('service-waiver-receipts.show',$waiver) }}" target="_blank" rel="noopener">Imprimir recibo mensual</a></article>@endforeach</div><div class="mt-4">{{ $waivers->links() }}</div><a class="mt-4 inline-block text-sky-700" href="{{ route('waivers.index') }}">Volver a campañas</a>
@endsection
