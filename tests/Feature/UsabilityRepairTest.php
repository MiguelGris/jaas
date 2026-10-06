<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\UserCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UsabilityRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_codes_are_repaired_without_rewriting_existing_accounts(): void
    {
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);
        $existing = User::query()->create(['name' => 'Existente', 'email' => 'existing@example.test', 'password' => 'test-password', 'active' => true, 'role_id' => $role->id]);
        $code = $existing->user_code;
        $missing = User::query()->create(['name' => 'Sin código', 'email' => 'missing@example.test', 'password' => 'test-password', 'active' => true, 'role_id' => $role->id]);
        DB::table('users')->where('id', $missing->id)->update(['user_code' => null]);
        app(UserCodeService::class)->repairMissing();
        $this->assertSame($code, $existing->fresh()->user_code);
        $this->assertNotNull($missing->fresh()->user_code);
        $this->assertNotSame($code, $missing->fresh()->user_code);
        $repaired = $missing->fresh()->user_code;
        app(UserCodeService::class)->repairMissing();
        $this->assertSame($repaired, $missing->fresh()->user_code);
    }
}
