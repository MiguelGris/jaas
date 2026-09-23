<?php

namespace App\Models;

class CustomerStatus extends JassModel
{
    public $timestamps = false;

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
