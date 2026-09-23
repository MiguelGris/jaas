<?php

namespace App\Models;

class IncomeType extends JassModel
{
    public $timestamps = false;

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }
}
