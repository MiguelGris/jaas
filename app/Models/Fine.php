<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Fine extends JassModel
{
    use HasGeneratedCode;
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'generated_on' => 'date'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assembly()
    {
        return $this->belongsTo(Assembly::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'fine_code', 'series' => 'fines', 'prefix' => 'MLT', 'padding' => 6, 'date_field' => 'generated_on'];
    }
}
