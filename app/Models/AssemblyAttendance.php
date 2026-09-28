<?php

namespace App\Models;

use App\Services\AssemblyFineService;

class AssemblyAttendance extends JassModel
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::saving(function (self $attendance): void {
            // La hora representa el momento en que cambió a asistente. Al
            // desmarcarlo se limpia para no mostrar una llegada que ya no vale.
            if ($attendance->isDirty('attended')) {
                $attendance->attended_at = $attendance->attended ? now() : null;
            }
        });

        static::created(function (self $attendance): void {
            app(AssemblyFineService::class)->synchroniseAttendanceFine($attendance);
        });

        static::updated(function (self $attendance): void {
            // Centralizarlo en el modelo mantiene la multa consistente tanto
            // desde formularios web como desde el lector o la API.
            if ($attendance->wasChanged('attended')) {
                app(AssemblyFineService::class)->synchroniseAttendanceFine($attendance);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'attended_at' => 'datetime',
        ];
    }

    public function assembly()
    {
        return $this->belongsTo(Assembly::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
