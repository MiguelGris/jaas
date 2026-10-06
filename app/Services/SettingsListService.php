<?php

namespace App\Services;

use App\Models\LateFeeSetting;
use App\Models\Setting;
use App\Models\User;
use App\Support\ResourceAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SettingsListService
{
    public function forUser(User $user): Collection
    {
        $settings = ResourceAccess::allows($user, 'settings') ? Setting::query()->get() : collect();
        $rows = $settings->map(fn (Setting $setting) => [
            'name' => $setting->displayName(),
            'value' => $setting->key === 'billing_period_months' ? $this->cycleSummary() : $setting->value,
            'description' => $setting->displayDescription(),
            'show' => route('resources.show', ['resource' => 'settings', 'record' => $setting->id]),
            'edit' => ! $setting->isAutomatic() && ResourceAccess::allows($user, 'settings', 'edit')
                ? route('resources.edit', ['resource' => 'settings', 'record' => $setting->id]) : null,
            'automatic' => $setting->isAutomatic(),
        ]);
        if (ResourceAccess::allows($user, 'late-fee-settings')) {
            $fee = $this->currentMora();
            $rows->push([
                'name' => 'Configuración de mora',
                'value' => $fee ? 'S/ '.number_format((float) $fee->monthly_amount, 2).' al mes · '.$fee->grace_months.' '.((int) $fee->grace_months === 1 ? 'mes' : 'meses').' de gracia' : 'Sin configurar',
                'description' => $fee ? 'Mora única por ciclo, por cada mes de atraso. Vigencia desde el '.$fee->starts_on->format('d/m/Y').'.' : 'Define el plazo de gracia y el importe mensual por atraso.',
                'show' => route('settings.mora.show'),
                'edit' => route('settings.mora.edit'),
                'automatic' => false,
                'edit_label' => $fee ? 'Nueva versión' : 'Registrar',
            ]);
        }

        return $rows->sortBy(fn ($row) => mb_strtolower(Str::ascii($row['name'])))->values();
    }

    private function cycleSummary(): string
    {
        $first = DB::table('billing_cycle_schedules')->orderBy('starts_on')->first();
        $started = ! $first || $first->starts_on <= today()->toDateString();
        $active = $started ? app(BillingCycleService::class)->forMonth(today()) : null;
        $next = DB::table('billing_cycle_schedules')->where('starts_on', '>', today()->toDateString())->orderBy('starts_on')->first();
        $value = $active ? $active['months'].' mes(es) actualmente' : 'Por iniciar';

        return $value.($next ? ' · '.$next->months.' mes(es) desde '.Carbon::parse($next->starts_on)->format('m/Y') : '');
    }

    public function currentMora(): ?LateFeeSetting
    {
        return LateFeeSetting::query()->whereDate('starts_on', '<=', today())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->orderByDesc('starts_on')->orderByDesc('id')->first()
                ?? LateFeeSetting::query()->orderByDesc('starts_on')->orderByDesc('id')->first();
    }
}
