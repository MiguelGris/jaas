<?php

namespace App\Models;

use App\Http\Controllers\Web\JassPageController;
use Carbon\Carbon;

class AuditLog extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function moduleLabel(): string
    {
        foreach (JassPageController::definitions() as $definition) {
            if ((new $definition['model'])->getTable() === $this->table_name) {
                return $definition['label'];
            }
        }

        return match ($this->table_name) {
            'billing_cycle_schedules' => 'Programación de ciclos', default => $this->table_name
        };
    }

    public function getSummaryTextAttribute(): string
    {
        $actor = $this->user?->name ?? 'Sistema';
        if ($this->table_name === 'cash_closings' && $this->action === 'INSERT' && isset($this->new_values['year'], $this->new_values['month'])) {
            $month = Carbon::create((int) $this->new_values['year'], (int) $this->new_values['month'], 1)->translatedFormat('F Y');

            return $actor.' registró el cierre de '.$month;
        }
        $verb = match ($this->action) {
            'INSERT' => 'creó', 'UPDATE' => 'actualizó', 'DELETE' => 'eliminó', default => 'registró un cambio en'
        };

        return $actor.' '.$verb.' '.$this->moduleLabel().' #'.$this->record_id;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
