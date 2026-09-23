<?php

namespace App\Models;

class Permission extends JassModel
{
    public $timestamps = false;

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
