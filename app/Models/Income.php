<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Income extends JassModel
{
    use HasGeneratedCode;
    public $timestamps = false;

    protected function casts(): array
    {
        return ['received_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function incomeType()
    {
        return $this->belongsTo(IncomeType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'income_code', 'series' => 'incomes', 'prefix' => 'ING', 'padding' => 6, 'date_field' => 'received_on'];
    }
}
