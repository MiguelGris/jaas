<?php

namespace App\Models;

class ConnectionType extends JassModel
{
    public $timestamps = false;

    public function connections()
    {
        return $this->hasMany(Connection::class);
    }
}
