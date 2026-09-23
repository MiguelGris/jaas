<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Connection extends JassModel
{
    use HasGeneratedCode;

    public const PAYMENT_FIXED = 'FIXED';
    public const PAYMENT_METERED = 'METERED';

    protected static function booted(): void
    {
        static::created(function (self $connection): void {
            $usageTypeId = UsageType::query()
                ->whereIn('name', ['RESIDENTIAL', 'RESIDENCIAL'])
                ->value('id');

            if ($usageTypeId !== null) {
                ConnectionUsageType::query()->firstOrCreate([
                    'connection_id' => $connection->getKey(),
                    'usage_type_id' => $usageTypeId,
                    'starts_on' => $connection->installed_on?->toDateString() ?? now()->toDateString(),
                ]);
            }
        });
    }
    protected function casts(): array
    {
        return ['installed_on' => 'date'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function connectionType()
    {
        return $this->belongsTo(ConnectionType::class);
    }

    public function connectionStatus()
    {
        return $this->belongsTo(ConnectionStatus::class);
    }

    public function usageAssignments()
    {
        return $this->hasMany(ConnectionUsageType::class);
    }

    public function usageTypes()
    {
        return $this->belongsToMany(UsageType::class, 'connection_usage_types')
            ->withPivot(['starts_on', 'ends_on']);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function meters()
    {
        return $this->hasMany(Meter::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'supply_code', 'series' => 'connections', 'prefix' => 'SUM', 'padding' => 4];
    }
}
