<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Meter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ResourceValidationService
{
    public function ensureCashMovementIsOpen(?string $resource, array $attributes, ?Model $model = null): void
    {
        $dateField = match ($resource) {
            'incomes' => 'received_on',
            'expenses' => 'incurred_on',
            default => null,
        };

        if ($dateField === null) {
            return;
        }

        $cash = app(CashService::class);

        if ($model !== null) {
            $cash->ensureMovementDateIsOpen($model->getAttribute($dateField), $dateField);
        }

        if (array_key_exists($dateField, $attributes)) {
            $cash->ensureMovementDateIsOpen($attributes[$dateField], $dateField);
        }
    }

    public function ensureConnectionCanUsePaymentMode(Connection $connection, array $attributes): void
    {
        $mode = $attributes['payment_mode'] ?? $connection->payment_mode ?? Connection::PAYMENT_FIXED;

        if ($mode === Connection::PAYMENT_FIXED && $connection->meters()->where('active', true)->exists()) {
            throw ValidationException::withMessages([
                'payment_mode' => 'Desactiva o retira el medidor activo antes de cambiar la conexión a pago fijo.',
            ]);
        }
    }

    public function ensureMeterCanBeAssigned(array $attributes, ?Meter $meter = null): void
    {
        $connectionId = (int) ($attributes['connection_id'] ?? $meter?->connection_id);
        $connection = Connection::query()->findOrFail($connectionId);

        if ($connection->payment_mode !== Connection::PAYMENT_METERED) {
            throw ValidationException::withMessages([
                'connection_id' => 'Solo las conexiones configuradas con cobro por medidor pueden tener un medidor asignado.',
            ]);
        }

        $active = array_key_exists('active', $attributes) ? (bool) $attributes['active'] : ($meter?->active ?? true);
        if ($active) {
            $activeMeters = Meter::query()
                ->where('connection_id', $connection->getKey())
                ->where('active', true)
                ->when($meter, fn ($query) => $query->where('id', '!=', $meter->getKey()));

            if ($activeMeters->exists()) {
                throw ValidationException::withMessages(['connection_id' => 'La conexión ya tiene un medidor activo.']);
            }
        }

        $initialReading = (float) ($attributes['initial_reading'] ?? $meter?->initial_reading ?? 0);
        $firstReading = $meter?->readings()->orderBy('read_on')->orderBy('id')->first();
        if ($firstReading !== null && $initialReading > (float) $firstReading->current_reading) {
            throw ValidationException::withMessages([
                'initial_reading' => 'La lectura inicial no puede superar la primera lectura registrada.',
            ]);
        }
    }
}
