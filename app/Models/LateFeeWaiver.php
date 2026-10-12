<?php

namespace App\Models;

final class LateFeeWaiver extends JassModel
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
