<?php

namespace App\Models;

use Illuminate\Support\Str;

class Setting extends JassModel
{
    public function isAutomatic(): bool
    {
        return $this->key === 'billing_last_manual_run';
    }

    public function displayName(): string
    {
        return match ($this->key) {
            'billing_period_months' => 'Ciclo de pago',
            'billing_issue_day' => 'Día de emisión de cuotas',
            'billing_last_manual_run' => 'Última emisión manual',
            'work_exemption_age' => 'Edad de exoneración de faenas',
            'payment_due_days' => 'Plazo de pago anterior',
            default => Str::headline($this->key),
        };
    }

    public function displayDescription(): string
    {
        if (! $this->isAutomatic() && $this->key !== 'payment_due_days'
            && filled($this->description) && ! preg_match('/\b[a-z]+(?:_[a-z]+)+\b/', $this->description)) {
            return $this->description;
        }

        return match ($this->key) {
            'billing_period_months' => 'Cantidad de meses por ciclo de pago. Puede ser de 3 o 6 meses.',
            'billing_issue_day' => 'Día del mes en que se emiten las cuotas. Puede ser del 1 al 28.',
            'billing_last_manual_run' => 'Fecha, periodo y cantidad de cuotas de la última emisión manual. Se actualiza automáticamente y no se puede editar.',
            'work_exemption_age' => 'Edad a partir de la cual se exonera al titular de las faenas.',
            'payment_due_days' => 'Dato de referencia del esquema anterior. El plazo vigente se administra en Configuración de mora.',
            default => preg_replace_callback('/\b[a-z]+(?:_[a-z]+)+\b/', fn ($match) => match ($match[0]) {
                'late_fee_settings' => 'Configuración de mora',
                'billing_periods' => 'ciclos de pago',
                'billing_period_months' => 'ciclo de pago',
                'billing_issue_day' => 'día de emisión',
                'billing_last_manual_run' => 'última emisión manual',
                default => str_replace('_', ' ', $match[0]),
            }, (string) $this->description),
        };
    }
}
