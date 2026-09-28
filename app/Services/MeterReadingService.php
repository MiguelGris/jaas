<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MeterReadingService
{
    /** @param array{meter_id: int|string, read_on: string, current_reading: int|float|string, user_id?: int|string|null, notes?: string|null} $attributes */
    public function create(array $attributes, ?int $recordedBy = null): MeterReading
    {
        return DB::transaction(function () use ($attributes, $recordedBy): MeterReading {
            $meter = $this->readableMeter((int) $attributes['meter_id']);
            $this->ensureUniqueReadingDate($meter, (string) $attributes['read_on']);
            $attributes['user_id'] = $attributes['user_id'] ?? $recordedBy;
            $reading = MeterReading::query()->create($attributes);

            $this->recalculateForMeter($meter);

            return $reading->fresh(['meter.connection', 'user']);
        });
    }

    /** @param array{meter_id: int|string, read_on: string, current_reading: int|float|string, user_id?: int|string|null, notes?: string|null} $attributes */
    public function update(MeterReading $reading, array $attributes): MeterReading
    {
        return DB::transaction(function () use ($reading, $attributes): MeterReading {
            $previousMeterId = $reading->meter_id;
            $meter = $this->readableMeter((int) $attributes['meter_id']);
            $this->ensureUniqueReadingDate($meter, (string) $attributes['read_on'], $reading->getKey());
            $reading->fill($attributes)->save();

            if ($previousMeterId !== $meter->getKey()) {
                $this->recalculateForMeter(Meter::query()->findOrFail($previousMeterId));
            }
            $this->recalculateForMeter($meter);

            return $reading->fresh(['meter.connection', 'user']);
        });
    }

    public function delete(MeterReading $reading): void
    {
        DB::transaction(function () use ($reading): void {
            $meter = Meter::query()->findOrFail($reading->meter_id);
            $reading->delete();
            $this->recalculateForMeter($meter);
        });
    }

    /**
     * Recorre cronológicamente todas las lecturas del medidor.
     *
     * Se recalcula la cadena completa porque insertar, editar o eliminar una
     * lectura antigua modifica la lectura anterior y el consumo de las
     * posteriores.
     */
    public function recalculateForMeter(Meter $meter): void
    {
        $previous = (float) $meter->initial_reading;
        $readings = MeterReading::query()
            ->where('meter_id', $meter->getKey())
            ->orderBy('read_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($readings as $reading) {
            $current = (float) $reading->current_reading;

            if ($current < $previous) {
                throw ValidationException::withMessages([
                    'current_reading' => 'La lectura no puede ser menor que la lectura anterior ('.number_format($previous, 2).').',
                ]);
            }

            $reading->forceFill([
                'previous_reading' => $previous,
                'consumption' => round($current - $previous, 2),
            ])->saveQuietly();
            $previous = $current;
        }
    }

    private function readableMeter(int $meterId): Meter
    {
        $meter = Meter::query()->with('connection')->findOrFail($meterId);

        if (! $meter->active) {
            throw ValidationException::withMessages(['meter_id' => 'El medidor seleccionado está inactivo.']);
        }

        if ($meter->connection?->payment_mode !== Connection::PAYMENT_METERED) {
            throw ValidationException::withMessages(['meter_id' => 'Solo se pueden registrar lecturas en conexiones configuradas con medidor.']);
        }

        return $meter;
    }

    private function ensureUniqueReadingDate(Meter $meter, string $readOn, ?int $exceptId = null): void
    {
        $query = MeterReading::query()
            ->where('meter_id', $meter->getKey())
            ->whereDate('read_on', $readOn);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['read_on' => 'Ya existe una lectura para este medidor en la fecha indicada.']);
        }
    }
}
