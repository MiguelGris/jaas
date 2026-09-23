<?php

namespace App\Models;

class PaymentAllocation extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function fine()
    {
        return $this->belongsTo(Fine::class);
    }
}
