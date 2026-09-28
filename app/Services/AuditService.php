<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Registra cambios relevantes sin copiar contraseñas ni datos de sesión. */
final class AuditService
{
    /** @return array<string, mixed> */
    public function snapshot(Model $model): array
    {
        $excluded = ['password', 'remember_token', 'created_at', 'updated_at'];

        return collect($model->getAttributes())
            ->except($excluded)
            ->map(static function (mixed $value): mixed {
                if ($value instanceof \DateTimeInterface) {
                    return $value->format(DATE_ATOM);
                }

                return $value;
            })
            ->all();
    }

    public function created(?User $user, Model $model): void
    {
        $this->record($user, $model, 'INSERT', null, $this->snapshot($model));
    }

    /** @param array<string, mixed> $before */
    public function updated(?User $user, Model $model, array $before): void
    {
        $after = $this->snapshot($model);
        $oldValues = [];
        $newValues = [];

        // Solo se almacenan los campos realmente modificados. Esto mantiene la
        // bitácora legible incluso en modelos con muchos atributos.
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $attribute) {
            $oldValue = $before[$attribute] ?? null;
            $newValue = $after[$attribute] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $oldValues[$attribute] = $oldValue;
            $newValues[$attribute] = $newValue;
        }

        if ($newValues !== []) {
            $this->record($user, $model, 'UPDATE', $oldValues, $newValues);
        }
    }

    /** @param array<string, mixed> $before */
    public function deleted(?User $user, Model $model, array $before): void
    {
        $this->record($user, $model, 'DELETE', $before, null);
    }

    public function passwordChanged(?User $actor, User $user): void
    {
        $this->record(
            $actor,
            $user,
            'UPDATE',
            ['password' => 'PROTECTED'],
            ['password' => 'UPDATED'],
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(?User $user, Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        if ($user === null || $model instanceof AuditLog) {
            // Los procesos programados pueden no tener usuario y auditar una
            // entrada de auditoría provocaría una recursión infinita.
            return;
        }

        AuditLog::query()->create([
            'user_id' => $user->getKey(),
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'occurred_at' => now(),
        ]);
    }
}
