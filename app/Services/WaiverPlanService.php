<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Shared amount planning; callers authorize and persist the approved rows. */
final class WaiverPlanService
{
    public function plan(Collection $rows, string $mode, float $value, bool $perCustomer = false): array
    {
        if ($rows->isEmpty() || ! in_array($mode, ['all', 'percentage', 'amount'], true) || ! is_finite($value)
            || ($mode !== 'all' && ($value <= 0 || ($mode === 'percentage' && $value > 100)))) {
            throw ValidationException::withMessages(['campaign' => 'Selecciona deudas y un importe o porcentaje válido.']);
        }
        $remaining = (int) round($value * 100);
        if ($mode === 'amount' && ! $perCustomer && $remaining > (int) round($rows->sum('balance') * 100)) {
            throw ValidationException::withMessages(['campaign' => 'El importe supera el saldo seleccionado.']);
        }
        $plan = [];
        $customerRemaining = [];
        foreach ($rows as $row) {
            if ($mode === 'amount' && $perCustomer) {
                $customerRemaining[$row['customer_id']] ??= (int) round($value * 100);
                $remaining = $customerRemaining[$row['customer_id']];
            }
            $cents = (int) round($row['balance'] * 100);
            $waived = match ($mode) {
                'all' => $cents, 'percentage' => (int) round($cents * $value / 100), default => min($cents, $remaining)
            };
            if ($waived <= 0) {
                continue;
            }
            $remaining -= $waived;
            if ($mode === 'amount' && $perCustomer) {
                $customerRemaining[$row['customer_id']] = $remaining;
            }
            $plan[] = $row + ['waive' => $waived / 100];
        }
        if ($plan === []) {
            throw ValidationException::withMessages(['campaign' => 'El importe a condonar debe ser al menos S/ 0.01.']);
        }

        return $plan;
    }
}
