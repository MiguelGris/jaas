<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Customer extends JassModel
{
    use HasGeneratedCode;
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'registered_on' => 'date',
        ];
    }

    public function customerStatus()
    {
        return $this->belongsTo(CustomerStatus::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function assemblyAttendances()
    {
        return $this->hasMany(AssemblyAttendance::class);
    }

    public function fines()
    {
        return $this->hasMany(Fine::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'customer_code', 'series' => 'customers', 'prefix' => 'CLI', 'padding' => 4];
    }
}
