<?php

namespace App\Models;

class CashClosing extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'total_income' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'balance' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
