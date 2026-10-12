<?php

namespace App\Models;

final class FineWaiver extends JassModel
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function fine()
    {
        return $this->belongsTo(Fine::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(WaiverCampaign::class);
    }
}
