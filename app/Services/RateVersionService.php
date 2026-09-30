<?php

namespace App\Services;

use App\Models\Rate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RateVersionService
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, ?User $actor = null): Rate
    {
        return DB::transaction(fn (): Rate => $this->createLocked($this->normalise($attributes), $actor));
    }

    /** @param array<string, mixed> $attributes */
    public function revise(Rate $current, array $attributes, ?User $actor = null): Rate
    {
        return DB::transaction(function () use ($current, $attributes, $actor): Rate {
            $current = Rate::query()->lockForUpdate()->findOrFail($current->getKey());
            $latest = Rate::query()
                ->where('usage_type_id', $current->usage_type_id)
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $latest?->is($current)) {
                throw ValidationException::withMessages([
                    'starts_on' => 'Solo la versión más reciente puede reemplazarse. Las versiones históricas se conservan sin cambios.',
                ]);
            }

            $attributes = $this->normalise(array_merge(
                $current->only([
                    'usage_type_id', 'year', 'amount', 'metered_unit_price',
                    'starts_on', 'ends_on', 'approved_by_assembly', 'notes',
                ]),
                $attributes,
            ));

            if ((int) $attributes['usage_type_id'] !== (int) $current->usage_type_id) {
                throw ValidationException::withMessages([
                    'usage_type_id' => 'El tipo de uso no puede cambiarse al crear una nueva versión.',
                ]);
            }

            if (Carbon::parse($attributes['starts_on'])->startOfDay()->lte($current->starts_on->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'starts_on' => 'La nueva vigencia debe comenzar después del '.($current->starts_on?->format('d/m/Y') ?? 'inicio anterior').'.',
                ]);
            }

            return $this->createLocked($attributes, $actor);
        });
    }

    /** @param array<string, mixed> $attributes */
    private function createLocked(array $attributes, ?User $actor): Rate
    {
        $start = Carbon::parse($attributes['starts_on'])->startOfDay();
        $end = filled($attributes['ends_on'] ?? null)
            ? Carbon::parse($attributes['ends_on'])->startOfDay()
            : null;
        $year = (int) $attributes['year'];

        if ($start->year !== $year) {
            throw ValidationException::withMessages([
                'year' => 'El año debe coincidir con la fecha de inicio de vigencia.',
            ]);
        }

        if ($end !== null && $end->year !== $year) {
            throw ValidationException::withMessages([
                'ends_on' => 'La fecha final debe pertenecer al mismo año de la tarifa.',
            ]);
        }

        $versions = Rate::query()
            ->where('usage_type_id', $attributes['usage_type_id'])
            ->orderBy('starts_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($versions->contains(fn (Rate $rate): bool => $rate->starts_on->isSameDay($start))) {
            throw ValidationException::withMessages([
                'starts_on' => 'Ya existe una tarifa para el tipo de uso en esa fecha de inicio.',
            ]);
        }

        $previous = $versions
            ->filter(fn (Rate $rate): bool => $rate->starts_on->lt($start))
            ->last();
        $next = $versions
            ->first(fn (Rate $rate): bool => $rate->starts_on->gt($start));

        if ($next !== null) {
            $maximumEnd = $next->starts_on->copy()->subDay();

            if ($end !== null && $end->gt($maximumEnd)) {
                throw ValidationException::withMessages([
                    'ends_on' => 'La vigencia se superpone con una tarifa posterior que empieza el '.$next->starts_on->format('d/m/Y').'.',
                ]);
            }

            $end ??= $maximumEnd;
            $attributes['ends_on'] = $end->toDateString();
        }

        if ($previous !== null && ($previous->ends_on === null || $previous->ends_on->gte($start))) {
            $audit = app(AuditService::class);
            $before = $audit->snapshot($previous);
            $previous->ends_on = $start->copy()->subDay()->toDateString();
            $previous->save();
            $audit->updated($actor, $previous, $before);
        }

        $overlaps = Rate::query()
            ->where('usage_type_id', $attributes['usage_type_id'])
            ->whereDate('starts_on', '<=', ($end ?? Carbon::create($year, 12, 31))->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start->toDateString()))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_on' => 'La vigencia indicada se superpone con otra tarifa existente.',
            ]);
        }

        $rate = Rate::query()->create($attributes);
        app(AuditService::class)->created($actor, $rate);

        return $rate;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        $attributes['starts_on'] = Carbon::parse($attributes['starts_on'])->toDateString();
        $attributes['ends_on'] = filled($attributes['ends_on'] ?? null)
            ? Carbon::parse($attributes['ends_on'])->toDateString()
            : null;

        return $attributes;
    }
}
