<?php

namespace App\Models;

class Rate extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metered_unit_price' => 'decimal:4',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'approved_by_assembly' => 'boolean',
        ];
    }

    public function usageType()
    {
        return $this->belongsTo(UsageType::class);
    }
}
