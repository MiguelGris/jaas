<?php

namespace App\Models;

class ConnectionStatus extends JassModel
{
    public $timestamps = false;

    public function connections()
    {
        return $this->hasMany(Connection::class);
    }
}
