<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Expense extends JassModel
{
    use HasGeneratedCode;
    public $timestamps = false;

    protected function casts(): array
    {
        return ['incurred_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'expense_code', 'series' => 'expenses', 'prefix' => 'EGR', 'padding' => 6, 'date_field' => 'incurred_on'];
    }
}
