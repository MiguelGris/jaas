<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\Connection;
use App\Models\ConnectionUsageType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperationalRecordService
{
    public function attendance(array $attributes, ?AssemblyAttendance $record = null, bool $scheduledOnly = false): AssemblyAttendance
    {
        return DB::transaction(function () use ($attributes, $record, $scheduledOnly) {
            $assembly = Assembly::query()->whereKey($attributes['assembly_id'])->lockForUpdate()->firstOrFail();
            if ($scheduledOnly && $assembly->status !== 'SCHEDULED') {
                throw ValidationException::withMessages(['assembly_id' => 'La asamblea debe estar programada para registrar asistencias.']);
            }
            if ($record !== null && ($record->assembly_id != $attributes['assembly_id'] || $record->customer_id != $attributes['customer_id'])) {
                throw ValidationException::withMessages(['customer_id' => 'No puedes cambiar el cliente ni la asamblea de una asistencia. Corrige su asistencia en el padrón correspondiente.']);
            }
            $record ??= AssemblyAttendance::query()->firstOrNew([
                'assembly_id' => $attributes['assembly_id'], 'customer_id' => $attributes['customer_id'],
            ]);
            // La sincronización debe usar el estado leído bajo el bloqueo,
            // aunque el formulario hubiera cargado otra versión de la asamblea.
            $record->setRelation('assembly', $assembly);
            $audit = app(AuditService::class);
            $before = $record->exists ? $audit->snapshot($record) : null;
            $record->fill($attributes)->save();
            if ($before === null) {
                $audit->created(auth()->user(), $record);
            } else {
                $audit->updated(auth()->user(), $record, $before);
            }

            return $record;
        }, 3);
    }

    public function assembly(Assembly $record, array $attributes): Assembly
    {
        return DB::transaction(function () use ($record, $attributes) {
            $record = Assembly::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $record->fill($attributes)->save();

            return $record;
        }, 3);
    }

    public function usage(array $attributes, ?ConnectionUsageType $record = null): ConnectionUsageType
    {
        return DB::transaction(function () use ($attributes, $record) {
            $attributes = array_merge($record?->only(['connection_id', 'usage_type_id', 'starts_on', 'ends_on']) ?? [], $attributes);
            $attributes['starts_on'] = Carbon::parse($attributes['starts_on'])->toDateString();
            $attributes['ends_on'] = empty($attributes['ends_on']) ? null : Carbon::parse($attributes['ends_on'])->toDateString();
            Connection::query()->whereKey($attributes['connection_id'])->lockForUpdate()->firstOrFail();
            if ($record !== null && $record->connection_id != $attributes['connection_id']) {
                throw ValidationException::withMessages(['connection_id' => 'El uso pertenece a esta conexión. Registra otro uso para una conexión diferente.']);
            }
            if (! empty($attributes['ends_on']) && $attributes['ends_on'] < $attributes['starts_on']) {
                throw ValidationException::withMessages(['ends_on' => 'La fecha final debe ser igual o posterior a la fecha inicial.']);
            }
            $overlap = ConnectionUsageType::query()->where('connection_id', $attributes['connection_id'])
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $attributes['starts_on']))
                ->when(! empty($attributes['ends_on']), fn ($query) => $query->whereDate('starts_on', '<=', $attributes['ends_on']))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'Esta conexión ya tiene un uso en esas fechas. Revisa el uso existente o finaliza su vigencia antes de registrar el siguiente.']);
            }
            $record ??= new ConnectionUsageType;
            $record->fill($attributes)->save();

            return $record;
        }, 3);
    }
}
