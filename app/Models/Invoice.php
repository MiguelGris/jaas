<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use App\Services\BillingCycleService;

class Invoice extends JassModel
{
    use HasGeneratedCode;

    protected function casts(): array
    {
        return [
            'cycle_starts_on' => 'date',
            'cycle_ends_on' => 'date',
            'cycle_late_fee_amount' => 'decimal:2',
            'issued_on' => 'date',
            'due_on' => 'date',
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
            'rate' => 'decimal:2',
            'late_fee' => 'decimal:2',
            'fines' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function cycleKey(): string
    {
        $cycle = app(BillingCycleService::class)->forInvoice($this);

        return $this->connection_id.'-'.$cycle['start']->toDateString().'-'.$cycle['end']->toDateString();
    }

    public function cycleLabel(): string
    {
        $cycle = app(BillingCycleService::class)->forInvoice($this);

        return $cycle['start']->translatedFormat('F Y').' – '.$cycle['end']->translatedFormat('F Y');
    }

    public function connection()
    {
        return $this->belongsTo(Connection::class);
    }

    public function billingPeriod()
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class)
            ->whereHas('payment', fn ($query) => $query->active());
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'invoice_code', 'series' => 'invoices', 'prefix' => 'FAC', 'padding' => 6, 'date_field' => 'issued_on'];
    }
}
