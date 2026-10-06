<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\Connection;
use App\Models\Customer;
use App\Models\Fine;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Codes\CodeGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentCollectionService
{
    public function __construct(private readonly DebtService $debts) {}

    /**
     * Registra un pago completo sobre las cuotas y multas seleccionadas.
     *
     * Todo se ejecuta en una sola transacción para impedir recibos parciales:
     * o se guardan el pago, sus asignaciones y los nuevos estados, o no se
     * guarda nada.
     *
     * @param  list<int|string>  $invoiceIds
     * @param  list<int|string>  $fineIds
     * @param  array{payment_method_id: int|string, notes?: string|null}  $details
     */
    public function collect(Customer $customer, array $invoiceIds, array $fineIds, array $details, ?User $user): Payment
    {
        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));
        $fineIds = array_values(array_unique(array_map('intval', $fineIds)));

        if ($invoiceIds === [] && $fineIds === []) {
            throw ValidationException::withMessages(['charges' => 'Selecciona al menos una cuota o multa.']);
        }

        return DB::transaction(function () use ($customer, $invoiceIds, $fineIds, $details, $user): Payment {
            // Compartimos el bloqueo con la reapertura de asambleas: el estado
            // no puede cambiar entre la validación y la confirmación del cobro.
            $assemblyIds = Fine::query()->whereIn('id', $fineIds)->pluck('assembly_id')->unique()->sort()->values();
            $assemblies = Assembly::query()->whereIn('id', $assemblyIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($assemblyIds as $assemblyId) {
                if ($assemblies->get($assemblyId)?->status !== 'HELD') {
                    throw ValidationException::withMessages(['fines' => 'La asamblea está programada o cancelada. Sus multas no se pueden cobrar.']);
                }
            }
            // Lock the entire supply: different quotas can share one cycle's mora.
            $connectionIds = Invoice::query()->whereIn('id', $invoiceIds)->pluck('connection_id')->unique()->sort();
            Connection::query()->whereIn('id', $connectionIds)->orderBy('id')->lockForUpdate()->get();
            sort($invoiceIds);
            $date = now();
            $allocations = [];

            // El bloqueo evita que dos cajeros cobren simultáneamente el mismo
            // saldo pendiente antes de que el primer pago sea confirmado.
            foreach ($invoiceIds as $invoiceId) {
                $invoice = Invoice::query()
                    ->lockForUpdate()
                    ->whereKey($invoiceId)
                    ->where('status', 'PENDING')
                    ->whereHas('connection.property', fn ($query) => $query->where('customer_id', $customer->getKey()))
                    ->first();

                if ($invoice === null) {
                    throw ValidationException::withMessages(['invoices' => 'Una de las cuotas ya no está disponible para este cliente.']);
                }

                $invoice = $this->debts->decorateInvoice($invoice, $date);

                if ($invoice->balance_due <= 0) {
                    throw ValidationException::withMessages(['invoices' => 'Una de las cuotas seleccionadas ya fue pagada.']);
                }

                $allocations[] = ['invoice' => $invoice, 'amount' => $invoice->balance_due];
            }

            foreach ($fineIds as $fineId) {
                $fine = Fine::query()
                    ->lockForUpdate()
                    ->whereKey($fineId)
                    ->where('customer_id', $customer->getKey())
                    ->where('status', 'PENDING')
                    ->first();

                if ($fine === null) {
                    throw ValidationException::withMessages(['fines' => 'Una de las multas ya no está disponible para este cliente.']);
                }

                $fine = $this->debts->decorateFine($fine);

                if ($fine->balance_due <= 0) {
                    throw ValidationException::withMessages(['fines' => 'Una de las multas seleccionadas ya fue pagada.']);
                }

                $allocations[] = ['fine' => $fine, 'amount' => $fine->balance_due];
            }

            $amount = round(array_sum(array_column($allocations, 'amount')), 2);

            // El pago es la entrada de caja; las asignaciones siguientes solo
            // explican a qué documentos corresponde ese importe.
            $payment = Payment::query()->create([
                'customer_id' => $customer->getKey(),
                'invoice_id' => null,
                'paid_at' => Carbon::now(),
                'amount' => $amount,
                'payment_method_id' => $details['payment_method_id'],
                'source' => 'COUNTER',
                'user_id' => $user?->getKey(),
                'operation_number' => CodeGenerator::next([
                    'series' => 'payment_operations',
                    'prefix' => 'OP',
                    'padding' => 6,
                    'date_field' => 'paid_at',
                ], $date),
                'notes' => $details['notes'] ?? null,
                'external_reference' => $details['external_reference'] ?? null,
                'status' => Payment::STATUS_ACTIVE,
            ]);

            foreach ($allocations as $allocation) {
                $row = ['payment_id' => $payment->getKey(), 'amount' => $allocation['amount']];

                if (isset($allocation['invoice'])) {
                    $invoice = $allocation['invoice'];
                    PaymentAllocation::query()->create($row + [
                        'charge_type' => 'INVOICE',
                        'invoice_id' => $invoice->getKey(),
                    ]);
                    $this->debts->synchroniseInvoice($invoice, $date);

                    continue;
                }

                $fine = $allocation['fine'];
                PaymentAllocation::query()->create($row + [
                    'charge_type' => 'FINE',
                    'fine_id' => $fine->getKey(),
                ]);
                $this->debts->synchroniseFine($fine);
            }

            return $payment->load(['customer', 'paymentMethod', 'user', 'allocations.invoice', 'allocations.fine']);
        }, 3);
    }
}
