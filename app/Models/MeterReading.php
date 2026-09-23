<?php

namespace App\Models;

class MeterReading extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'read_on' => 'date',
            'previous_reading' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'consumption' => 'decimal:2',
        ];
    }

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
