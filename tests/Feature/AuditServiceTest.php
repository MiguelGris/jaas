<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_authenticated_user_and_changed_values(): void
    {
        $role = Role::query()->create(['name' => 'AUDITOR']);
        $user = User::query()->create([
            'name' => 'Usuario',
            'last_name' => 'Prueba',
            'email' => 'auditoria@example.test',
            'password' => 'secret-password',
            'role_id' => $role->id,
            'active' => true,
        ]);
        $setting = Setting::query()->create([
            'key' => 'audit_test_setting',
            'value' => 'antes',
        ]);
        $audit = app(AuditService::class);

        $audit->created($user, $setting);

        $before = $audit->snapshot($setting);
        $setting->update(['value' => 'después']);
        $audit->updated($user, $setting, $before);

        $created = AuditLog::query()->where('action', 'INSERT')->firstOrFail();
        $updated = AuditLog::query()->where('action', 'UPDATE')->firstOrFail();

        $this->assertSame($user->id, $created->user_id);
        $this->assertSame('settings', $created->table_name);
        $this->assertSame('antes', $created->new_values['value']);
        $this->assertSame('antes', $updated->old_values['value']);
        $this->assertSame('después', $updated->new_values['value']);
    }
}
