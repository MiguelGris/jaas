<?php

namespace App\Models;

class BillingPeriod extends JassModel
{
    public $timestamps = false;

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
