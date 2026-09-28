<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentCancellationService
{
    public function __construct(
        private readonly DebtService $debts,
        private readonly CashService $cash,
    ) {}

    /**
     * Anula un pago sin borrarlo, preservando el recibo y la trazabilidad.
     *
     * Al dejar de ser un pago activo ya no suma en caja. Luego se recalculan
     * las cuotas y multas relacionadas para restaurar sus saldos pendientes.
     */
    public function cancel(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason): Payment {
            // Serializa anulaciones concurrentes del mismo comprobante.
            $payment = Payment::query()
                ->with(['allocations.invoice', 'allocations.fine'])
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($payment->status === Payment::STATUS_VOIDED) {
                throw ValidationException::withMessages([
                    'payment' => 'Este pago ya fue anulado.',
                ]);
            }

            $this->cash->ensureMovementDateIsOpen($payment->paid_at, 'payment');

            $payment->forceFill([
                'status' => Payment::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by' => $user->getKey(),
                'void_reason' => trim($reason),
            ])->save();

            // Las asignaciones se conservan como historial. Los servicios de
            // deuda ignoran su importe porque ahora pertenecen a un pago anulado.
            $payment->allocations
                ->pluck('invoice')
                ->filter()
                ->unique('id')
                ->each(fn ($invoice) => $this->debts->synchroniseInvoice($invoice, now()));
            $payment->allocations
                ->pluck('fine')
                ->filter()
                ->unique('id')
                ->each(fn ($fine) => $this->debts->synchroniseFine($fine));

            return $payment->fresh(['customer', 'paymentMethod', 'user', 'voidedBy', 'allocations.invoice', 'allocations.fine']);
        }, 3);
    }
}
