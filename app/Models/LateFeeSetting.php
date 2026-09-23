<?php

namespace App\Models;

class LateFeeSetting extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
