<?php

namespace App\Models;

class AssemblyAttendance extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['attended' => 'boolean'];
    }

    public function assembly()
    {
        return $this->belongsTo(Assembly::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
