<?php

namespace App\Models;

class AssemblyType extends JassModel
{
    public $timestamps = false;

    public function assemblies()
    {
        return $this->hasMany(Assembly::class);
    }
}
