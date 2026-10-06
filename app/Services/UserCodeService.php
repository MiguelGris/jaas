<?php

namespace App\Services;

use App\Models\User;
use App\Support\Codes\CodeGenerator;
use Illuminate\Support\Facades\DB;

final class UserCodeService
{
    public function repairMissing(): void
    {
        DB::transaction(function (): void {
            foreach (User::query()->whereNull('user_code')->orderBy('id')->lockForUpdate()->get() as $user) {
                do {
                    $code = CodeGenerator::next(['series' => 'users', 'prefix' => 'USR', 'padding' => 4]);
                } while (User::query()->where('user_code', $code)->exists());
                $user->forceFill(['user_code' => $code])->saveQuietly();
            }
        }, 3);
    }
}
