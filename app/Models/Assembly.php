<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use App\Services\AssemblyFineService;

class Assembly extends JassModel
{
    use HasGeneratedCode;
    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::created(function (self $assembly): void {
            app(AssemblyFineService::class)->prepareAttendance($assembly);

            if ($assembly->status === 'HELD') {
                app(AssemblyFineService::class)->applyAbsenceFines($assembly);
            }
        });

        static::updated(function (self $assembly): void {
            if ($assembly->wasChanged('status') && $assembly->status === 'HELD') {
                app(AssemblyFineService::class)->applyAbsenceFines($assembly);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'absence_fine' => 'decimal:2',
        ];
    }

    public function assemblyType()
    {
        return $this->belongsTo(AssemblyType::class);
    }

    public function attendances()
    {
        return $this->hasMany(AssemblyAttendance::class);
    }

    public function fines()
    {
        return $this->hasMany(Fine::class);
    }

    protected function generatedCodeDefinition(): array
    {
        return ['field' => 'assembly_code', 'series' => 'assemblies', 'prefix' => 'ASM', 'padding' => 4, 'date_field' => 'held_on'];
    }
}
