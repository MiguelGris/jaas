<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Income;
use App\Models\IncomeType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unused_catalog_item_can_be_deleted_from_the_list(): void
    {
        $administrator = $this->administrator();
        $duplicate = IncomeType::query()->create(['name' => 'DUPLICADO']);

        $this->actingAs($administrator)
            ->get(route('resources.index', ['resource' => 'income-types']))
            ->assertOk()
            ->assertSee('Eliminar');

        $this->actingAs($administrator)
            ->delete(route('resources.destroy', ['resource' => 'income-types', 'record' => $duplicate->id]))
            ->assertRedirect(route('resources.index', ['resource' => 'income-types']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('income_types', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'table_name' => 'income_types',
            'record_id' => $duplicate->id,
            'action' => 'DELETE',
        ]);
    }

    public function test_a_catalog_item_in_use_is_preserved_with_a_clear_error(): void
    {
        $administrator = $this->administrator();
        $incomeType = IncomeType::query()->create(['name' => 'EN USO']);
        Income::query()->create([
            'income_type_id' => $incomeType->id,
            'received_on' => '2026-09-01',
            'concept' => 'Ingreso relacionado',
            'amount' => 10,
            'user_id' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->from(route('resources.index', ['resource' => 'income-types']))
            ->delete(route('resources.destroy', ['resource' => 'income-types', 'record' => $incomeType->id]))
            ->assertRedirect(route('resources.index', ['resource' => 'income-types']))
            ->assertSessionHas('error', 'No se puede eliminar porque el registro tiene información relacionada.');

        $this->assertDatabaseHas('income_types', ['id' => $incomeType->id]);
        $this->assertSame(0, AuditLog::query()->where('table_name', 'income_types')->where('action', 'DELETE')->count());
    }

    private function administrator(): User
    {
        $role = Role::query()->create(['name' => 'ADMINISTRATOR']);

        return User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-catalogs@example.test',
            'password' => 'Password123',
            'role_id' => $role->id,
            'active' => true,
        ]);
    }
}
