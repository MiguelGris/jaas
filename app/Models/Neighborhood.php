<?php

namespace App\Models;

class Neighborhood extends JassModel
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }
}
