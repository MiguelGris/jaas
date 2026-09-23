<?php

namespace App\Models;

class ConnectionUsageType extends JassModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function connection()
    {
        return $this->belongsTo(Connection::class);
    }

    public function usageType()
    {
        return $this->belongsTo(UsageType::class);
    }
}
