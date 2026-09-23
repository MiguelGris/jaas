<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Property extends JassModel
{
    use HasGeneratedCode;
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function connections()
    {
        return $this->hasMany(Connection::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'property_code', 'series' => 'properties', 'prefix' => 'PRD', 'padding' => 4];
    }
}
