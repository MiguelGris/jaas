<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedCode;
use App\Services\AssemblyFineService;
use App\Services\AuditService;
use Illuminate\Validation\ValidationException;

class Assembly extends JassModel
{
    use HasGeneratedCode;

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::created(function (self $assembly): void {
            // La lista nace junto con la asamblea para que el lector y la edición
            // manual trabajen sobre el mismo padrón desde el primer momento.
            app(AssemblyFineService::class)->prepareAttendance($assembly);

            if ($assembly->status === 'HELD') {
                app(AssemblyFineService::class)->applyAbsenceFines($assembly);
            }
        });

        static::updating(function (self $assembly): void {
            if (($assembly->isDirty('status') && $assembly->status !== 'HELD') || $assembly->isDirty('absence_fine')) {
                if ($assembly->fines()->whereHas('paymentAllocations')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Esta asamblea tiene multas cobradas. Revisa y anula los recibos correspondientes antes de reabrirla, cancelarla o cambiar la multa.',
                    ]);
                }
            }
        });

        static::updated(function (self $assembly): void {
            // Las multas se generan al cerrar la asistencia, no mientras la
            // asamblea continúa programada y todavía pueden llegar titulares.
            if (($assembly->wasChanged('status') || $assembly->wasChanged('absence_fine')) && $assembly->status === 'HELD') {
                app(AssemblyFineService::class)->applyAbsenceFines($assembly);
            } elseif ($assembly->wasChanged('status') || $assembly->wasChanged('absence_fine')) {
                foreach ($assembly->fines()->where('status', 'PENDING')->get() as $fine) {
                    $audit = app(AuditService::class);
                    $before = $audit->snapshot($fine);
                    $fine->update(['status' => 'CANCELLED']);
                    $audit->updated(auth()->user(), $fine, $before);
                }
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
