<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

final class ServiceWaiver extends JassModel
{
    use HasGeneratedCode;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(WaiverCampaign::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'receipt_code', 'series' => 'service_waiver_receipts', 'prefix' => 'CD', 'padding' => 6, 'date_field' => 'created_at'];
    }
}
