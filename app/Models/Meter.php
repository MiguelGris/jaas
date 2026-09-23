<?php

namespace App\Models;

class Meter extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['installed_on' => 'date', 'initial_reading' => 'decimal:2', 'active' => 'boolean'];
    }

    public function connection()
    {
        return $this->belongsTo(Connection::class);
    }

    public function readings()
    {
        return $this->hasMany(MeterReading::class);
    }
}
