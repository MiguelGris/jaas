<?php

namespace App\Models;

final class WaiverCampaign extends JassModel
{
    protected function casts(): array
    {
        return ['total_services' => 'decimal:2', 'total_late_fees' => 'decimal:2', 'total_fines' => 'decimal:2', 'value' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
