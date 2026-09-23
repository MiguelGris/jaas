<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Payment extends JassModel
{
    use HasGeneratedCode;
    public $timestamps = false;

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'receipt_code', 'series' => 'payments', 'prefix' => 'RC', 'padding' => 6, 'date_field' => 'paid_at'];
    }
}
