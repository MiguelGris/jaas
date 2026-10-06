<?php

namespace App\Services;

use App\Models\BillingCycleSchedule;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BillingCycleService
{
    /** The anchor is stable across calendar years, including eight-month cycles. */
    public function bounds(CarbonInterface|string $month, int $months, CarbonInterface|string $anchor = '2026-01-01'): array
    {
        $date = Carbon::parse($month)->startOfMonth();
        $origin = Carbon::parse($anchor)->startOfMonth();
        $distance = ($date->year - $origin->year) * 12 + $date->month - $origin->month;
        $start = $origin->copy()->addMonthsNoOverflow((int) floor($distance / $months) * $months);

        return ['start' => $start, 'end' => $start->copy()->addMonthsNoOverflow($months)->subDay(), 'months' => $months];
    }

    public function forMonth(CarbonInterface|string $month): array
    {
        $date = Carbon::parse($month)->startOfMonth();
        $schedule = DB::table('billing_cycle_schedules')->where('starts_on', '<=', $date->toDateString())->orderByDesc('starts_on')->first();
        if ($schedule) {
            return $this->bounds($date, (int) $schedule->months, $schedule->anchor_on ?? $schedule->starts_on);
        }
        $first = DB::table('billing_cycle_schedules')->orderBy('starts_on')->first();
        if ($first && $date->lt(Carbon::parse($first->starts_on))) {
            throw ValidationException::withMessages(['month' => 'El mes es anterior al inicio de la facturación configurada.']);
        }
        $months = $first ? (int) $first->months : max(1, (int) (Setting::query()->where('key', 'billing_period_months')->value('value') ?: 3));

        return $this->bounds($date, $months);
    }

    public function forInvoice(Invoice $invoice): array
    {
        if ($invoice->cycle_starts_on !== null) {
            return ['start' => $invoice->cycle_starts_on->copy(), 'end' => $invoice->cycle_ends_on->copy(), 'months' => (int) $invoice->billingPeriod->months];
        }

        return $this->bounds($invoice->period_starts_on, max(1, (int) ($invoice->billingPeriod?->months ?? 3)));
    }

    public function saveSetting(array $attributes, ?Setting $setting, ?string $startMonth, ?User $actor): Setting
    {
        return DB::transaction(function () use ($attributes, $setting, $startMonth, $actor): Setting {
            $setting = $setting ? Setting::query()->lockForUpdate()->findOrFail($setting->id) : null;
            $key = $attributes['key'] ?? $setting?->key;
            if ($setting && $key !== $setting->key) {
                throw ValidationException::withMessages(['key' => 'La clave identifica la configuración y no se puede cambiar.']);
            }
            $rules = match ($key) {
                'billing_period_months' => ['required', 'integer', 'min:1', 'max:2147483647'],
                'billing_issue_day' => ['required', 'integer', 'between:1,28'],
                default => ['nullable'],
            };
            Validator::make(['value' => $attributes['value'] ?? $setting?->value, 'cycle_start_month' => $startMonth], [
                'value' => $rules, 'cycle_start_month' => ['nullable', 'date_format:Y-m'],
            ])->validate();
            if ($key === 'billing_period_months' && ((array_key_exists('value', $attributes) && (int) $attributes['value'] !== (int) $setting?->value) || filled($startMonth))) {
                $months = (int) ($attributes['value'] ?? $setting?->value);
                $active = $this->forMonth(today());
                $next = $active['end']->copy()->addDay()->startOfMonth();
                $start = filled($startMonth) ? Carbon::createFromFormat('!Y-m', $startMonth) : $next;
                // Before the first emission the JASS can choose its initial month freely.
                $initial = filled($startMonth) && ! Invoice::query()->exists() && ! DB::table('billing_cycle_schedules')->exists();
                if (! $initial && ($start->lt($next) || ! $this->forMonth($start)['start']->isSameDay($start))) {
                    throw ValidationException::withMessages(['cycle_start_month' => 'Elige el inicio de un ciclo posterior al activo (desde '.$next->format('m/Y').'). Los ciclos existentes deben terminar completos.']);
                }
                // Do not reinterpret already emitted future charges or silently replace a plan.
                if (Invoice::query()->where(fn ($q) => $q->whereDate('period_starts_on', '>=', $start)->orWhereDate('cycle_ends_on', '>=', $start))->exists()) {
                    throw ValidationException::withMessages(['cycle_start_month' => 'Ya existen cuotas emitidas desde ese mes. Elige un inicio posterior a sus ciclos.']);
                }
                if (DB::table('billing_cycle_schedules')->where('starts_on', '>=', $start->toDateString())->exists()) {
                    throw ValidationException::withMessages(['cycle_start_month' => 'Ya hay un cambio de ciclo programado desde ese mes. Consulta la programación antes de registrar otro.']);
                }
                // Check the database date range before saving a very long cycle.
                if ($months > (9999 - $start->year) * 12 + 12 - $start->month) {
                    throw ValidationException::withMessages(['value' => 'La duración excede el calendario disponible.']);
                }
                if (! DB::table('billing_cycle_schedules')->exists() && ! $initial) {
                    DB::table('billing_cycle_schedules')->insert(['starts_on' => '1000-01-01', 'anchor_on' => '2026-01-01', 'months' => $active['months']]);
                }
                $plan = BillingCycleSchedule::query()->create(['starts_on' => $start->toDateString(), 'months' => $months]);
                app(AuditService::class)->created($actor, $plan);
            }
            $audit = app(AuditService::class);
            $before = $setting ? $audit->snapshot($setting) : null;
            $setting ??= new Setting;
            $setting->fill($attributes)->save();
            $setting->wasRecentlyCreated ? $audit->created($actor, $setting) : $audit->updated($actor, $setting, $before);

            return $setting;
        });
    }
}
