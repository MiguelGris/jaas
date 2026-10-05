<?php

namespace App\Services;

use App\Models\LateFeeSetting;
use App\Models\Setting;
use App\Models\User;
use App\Support\ResourceAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SettingsListService
{
    public function forUser(User $user): Collection
    {
        $settings = ResourceAccess::allows($user, 'settings') ? Setting::query()->get() : collect();
        $rows = $settings->map(fn (Setting $setting) => [
            'name' => $setting->displayName(),
            'value' => $setting->value,
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
                'description' => $fee ? 'Importe por atraso en el pago. Vigencia desde el '.$fee->starts_on->format('d/m/Y').'.' : 'Define el plazo de gracia y el importe mensual por atraso.',
                'show' => route('settings.mora.show'),
                'edit' => route('settings.mora.edit'),
                'automatic' => false,
                'edit_label' => $fee ? 'Editar' : 'Registrar',
            ]);
        }

        return $rows->sortBy(fn ($row) => mb_strtolower(Str::ascii($row['name'])))->values();
    }

    public function currentMora(): ?LateFeeSetting
    {
        return LateFeeSetting::query()->whereDate('starts_on', '<=', today())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->orderByDesc('starts_on')->orderByDesc('id')->first()
                ?? LateFeeSetting::query()->orderByDesc('starts_on')->orderByDesc('id')->first();
    }
}
