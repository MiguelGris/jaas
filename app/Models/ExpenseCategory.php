<?php

namespace App\Models;

class ExpenseCategory extends JassModel
{
    public $timestamps = false;

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
