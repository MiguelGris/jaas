<?php

namespace App\Models;

class PaymentMethod extends JassModel
{
    public $timestamps = false;

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
