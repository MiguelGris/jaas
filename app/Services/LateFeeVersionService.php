<?php

namespace App\Services;

use App\Models\LateFeeSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class LateFeeVersionService
{
    public function create(array $attributes, ?User $actor = null): LateFeeSetting
    {
        return $this->save(null, $attributes, $actor);
    }

    public function revise(LateFeeSetting $current, array $attributes, ?User $actor = null): LateFeeSetting
    {
        return $this->save($current, $attributes, $actor);
    }

    private function save(?LateFeeSetting $current, array $attributes, ?User $actor): LateFeeSetting
    {
        return DB::transaction(function () use ($current, $attributes, $actor): LateFeeSetting {
            // The shared configuration row also serializes creation of the first version.
            DB::table('settings')->where('key', 'billing_period_months')->lockForUpdate()->first();
            $latest = LateFeeSetting::query()->orderByDesc('starts_on')->orderByDesc('id')->lockForUpdate()->first();
            if ($current && ! $latest?->is($current)) {
                throw ValidationException::withMessages(['starts_on' => 'Solo se puede crear una versión a partir de la más reciente. El historial no se modifica.']);
            }
            $attributes = array_merge($current?->only(['monthly_amount', 'grace_months', 'starts_on']) ?? [], $attributes);
            $attributes['ends_on'] = $attributes['ends_on'] ?? null;
            Validator::make($attributes, [
                'monthly_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'grace_months' => ['required', 'integer', 'between:0,12'],
                'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            ])->validate();
            $start = Carbon::parse($attributes['starts_on'])->startOfDay();
            if ($latest && $start->lte($latest->starts_on)) {
                throw ValidationException::withMessages(['starts_on' => 'La nueva vigencia debe comenzar después del '.$latest->starts_on->format('d/m/Y').'.']);
            }
            $audit = app(AuditService::class);
            if ($latest && ($latest->ends_on === null || $latest->ends_on->gte($start))) {
                $before = $audit->snapshot($latest);
                $latest->update(['ends_on' => $start->copy()->subDay()->toDateString()]);
                $audit->updated($actor, $latest, $before);
            }
            $version = LateFeeSetting::query()->create($attributes);
            $audit->created($actor, $version);

            return $version;
        });
    }
}
