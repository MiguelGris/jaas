<?php

namespace App\Models;

use App\Services\AssemblyFineService;

class AssemblyAttendance extends JassModel
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::saving(function (self $attendance): void {
            if ($attendance->isDirty('attended')) {
                $attendance->attended_at = $attendance->attended ? now() : null;
            }
        });

        static::created(function (self $attendance): void {
            app(AssemblyFineService::class)->synchroniseAttendanceFine($attendance);
        });

        static::updated(function (self $attendance): void {
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
