<section class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
    <h2 class="text-xl font-bold">Ficha del titular: {{ $record->display_name }}</h2>
    <p class="mt-2">Deuda pendiente: <strong>S/ {{ number_format($customerCharges['total'], 2) }}</strong> · {{ $customerCharges['invoices']->count() }} cuotas y {{ $customerCharges['fines']->count() }} multas.</p>
    @if ($canAnnulPayment)<a class="mt-3 inline-block rounded-lg bg-emerald-700 px-4 py-3 font-semibold text-white" href="{{ route('collections.create', ['customer' => $record->id]) }}">Cobrar y entregar recibo</a>@endif
    <h3 class="mt-5 font-bold">Casas, locales y servicios</h3>
    @forelse ($record->properties as $property)
        <article class="mt-3 rounded-lg bg-slate-50 p-4"><p class="font-semibold">{{ $property->property_code }} · {{ $property->address }} · {{ $property->active ? 'Activo' : 'Inactivo' }}</p>
        @foreach ($property->connections as $connection)<p class="mt-2 text-sm">{{ $connection->supply_code }} · {{ $connection->payment_mode === 'METERED' ? 'Con medidor' : 'Pago fijo' }}
        @if (App\Support\ResourceAccess::allows(auth()->user(), 'connections'))<a class="ml-3 font-semibold text-sky-700" href="{{ route('resources.show', ['resource'=>'connections','record'=>$connection->id]) }}">Ver servicio →</a>@endif</p>@endforeach</article>
    @empty<p class="mt-2 text-sm">Todavía no tiene una casa o local registrado. Continúa el alta del servicio.</p>@endforelse
    @if (App\Support\ResourceAccess::allows(auth()->user(), 'payments'))
    <h3 class="mt-5 font-bold">Últimos cinco recibos</h3>
    @forelse ($customerPayments as $payment)<p class="mt-2 text-sm"><a class="font-semibold text-sky-700" href="{{ route('resources.show', ['resource'=>'payments','record'=>$payment->id]) }}">{{ $payment->receipt_code }}</a> · {{ $payment->paid_at->format('d/m/Y') }} · S/ {{ number_format((float)$payment->amount, 2) }} · {{ $payment->status === 'ACTIVE' ? 'Válido' : 'Anulado' }}</p>
    @empty<p class="mt-2 text-sm">No hay recibos registrados.</p>@endforelse
@endif
</section>
