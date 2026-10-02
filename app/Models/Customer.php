<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;

class Customer extends JassModel
{
    use HasGeneratedCode;

    public const TYPE_PERSON = 'PERSON';

    public const TYPE_BUSINESS = 'BUSINESS';

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->customer_type ??= self::TYPE_PERSON;

            if ($customer->isBusiness()) {
                $customer->first_name = null;
                $customer->last_name = null;
                $customer->birth_date = null;
            } else {
                $customer->business_name = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'registered_on' => 'date',
        ];
    }

    public function customerStatus()
    {
        return $this->belongsTo(CustomerStatus::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function assemblyAttendances()
    {
        return $this->hasMany(AssemblyAttendance::class);
    }

    public function fines()
    {
        return $this->hasMany(Fine::class);
    }

    public function isBusiness(): bool
    {
        return $this->customer_type === self::TYPE_BUSINESS;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->isBusiness()) {
            return trim((string) $this->business_name) ?: 'Empresa sin razón social';
        }

        return trim(collect([$this->first_name, $this->last_name])->filter()->implode(' ')) ?: 'Cliente sin nombre';
    }

    public function getDocumentLabelAttribute(): string
    {
        return $this->isBusiness() ? 'RUC' : 'DNI';
    }

    public static function statusIsExempt(int|string|null $statusId): bool
    {
        if ($statusId === null || $statusId === '') {
            return false;
        }

        $name = CustomerStatus::query()->whereKey($statusId)->value('name');

        return in_array(strtoupper((string) $name), ['EXEMPT', 'EXONERADO'], true);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'customer_code', 'series' => 'customers', 'prefix' => 'CLI', 'padding' => 4];
    }
}
