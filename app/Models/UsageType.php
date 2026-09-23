<?php

namespace App\Models;

class UsageType extends JassModel
{
    public $timestamps = false;

    public function connectionUsageTypes()
    {
        return $this->hasMany(ConnectionUsageType::class);
    }

    public function connections()
    {
        return $this->belongsToMany(Connection::class, 'connection_usage_types')
            ->withPivot(['starts_on', 'ends_on']);
    }

    public function rates()
    {
        return $this->hasMany(Rate::class);
    }
}
