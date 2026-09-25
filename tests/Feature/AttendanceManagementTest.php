<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\AssemblyAttendance;
use App\Models\AssemblyType;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\Fine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\CashService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_shows_confirmation_progress_and_attendance_list_has_no_delete_button(): void
    {
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-attendance@example.test',
            'password' => 'Password123',
            'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
        $customer = Customer::query()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Asistencia',
            'national_id' => '12345678',
            'customer_status_id' => CustomerStatus::query()->create(['name' => 'ACTIVO'])->id,
            'registered_on' => '2026-09-01',
        ]);
        $assembly = Assembly::query()->create([
            'assembly_type_id' => AssemblyType::query()->create(['name' => 'Asamblea'])->id,
            'held_on' => '2026-09-30',
            'held_at' => '18:00',
            'place' => 'Local comunal',
            'absence_fine' => 10,
            'status' => 'SCHEDULED',
        ]);
        $attendance = AssemblyAttendance::query()
            ->where('assembly_id', $assembly->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->travelTo(Carbon::parse('2026-09-25 18:42:15'));

        $this->actingAs($administrator)
            ->followingRedirects()
            ->post(route('attendance.scan'), [
                'assembly_id' => $assembly->id,
                'barcode' => '12345678',
            ])
            ->assertOk()
            ->assertSee('Lectura del DNI')
            ->assertSee('Asistencia confirmada')
            ->assertSee('Cliente Asistencia')
            ->assertSee('1 de 1 registran asistencia')
            ->assertSee('18:42:15')
            ->assertSee('text-4xl', false)
            ->assertDontSee('Usar cámara')
            ->assertDontSee('start-camera')
            ->assertDontSee('BarcodeDetector');

        $this->assertDatabaseHas('assembly_attendances', [
            'id' => $attendance->id,
            'attended' => true,
            'attended_at' => '2026-09-25 18:42:15',
        ]);
        $this->travelBack();

        $this->actingAs($administrator)
            ->get(route('resources.index', ['resource' => 'assembly-attendances']))
            ->assertOk()
            ->assertDontSee('Eliminar');
    }

    public function test_attendance_corrections_sync_fines_without_changing_cash(): void
    {
        $administrator = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin-fines@example.test',
            'password' => 'Password123',
            'role_id' => Role::query()->create(['name' => 'ADMINISTRATOR'])->id,
            'active' => true,
        ]);
        $customer = Customer::query()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Multa',
            'customer_status_id' => CustomerStatus::query()->create(['name' => 'ACTIVO'])->id,
            'registered_on' => '2026-09-01',
        ]);
        $assembly = Assembly::query()->create([
            'assembly_type_id' => AssemblyType::query()->create(['name' => 'Asamblea'])->id,
            'held_on' => '2026-09-30',
            'held_at' => '18:00',
            'place' => 'Local comunal',
            'absence_fine' => 10,
            'status' => 'SCHEDULED',
        ]);
        $attendance = AssemblyAttendance::query()
            ->where('assembly_id', $assembly->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
        $attendance->update(['attended' => true]);
        $assembly->update(['status' => 'HELD']);

        $this->actingAs($administrator)
            ->put(route('resources.update', [
                'resource' => 'assembly-attendances',
                'record' => $attendance->id,
            ]), [
                'assembly_id' => $assembly->id,
                'customer_id' => $customer->id,
                'attended' => false,
                'notes' => null,
            ])
            ->assertSessionHasNoErrors();

        $fine = Fine::query()
            ->where('assembly_id', $assembly->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
        $this->assertNull($attendance->fresh()->attended_at);
        $this->assertSame('PENDING', $fine->status);
        $this->assertSame(10.0, (float) $fine->amount);

        $this->actingAs($administrator)
            ->put(route('resources.update', [
                'resource' => 'assembly-attendances',
                'record' => $attendance->id,
            ]), [
                'assembly_id' => $assembly->id,
                'customer_id' => $customer->id,
                'attended' => true,
                'notes' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('fines', ['id' => $fine->id]);

        $this->actingAs($administrator)
            ->put(route('resources.update', [
                'resource' => 'assembly-attendances',
                'record' => $attendance->id,
            ]), [
                'assembly_id' => $assembly->id,
                'customer_id' => $customer->id,
                'attended' => false,
                'notes' => null,
            ])
            ->assertSessionHasNoErrors();

        $fine = Fine::query()
            ->where('assembly_id', $assembly->id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
        $payment = Payment::query()->create([
            'customer_id' => $customer->id,
            'payment_method_id' => PaymentMethod::query()->create(['name' => 'Efectivo'])->id,
            'user_id' => $administrator->id,
            'paid_at' => now(),
            'amount' => 10,
            'source' => 'COUNTER',
            'operation_number' => 'OP-ATTENDANCE-001',
            'status' => Payment::STATUS_ACTIVE,
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'charge_type' => 'FINE',
            'fine_id' => $fine->id,
            'amount' => 10,
        ]);
        $fine->update(['status' => 'PAID']);
        $balanceBefore = app(CashService::class)->currentBalance()['balance'];

        $this->actingAs($administrator)
            ->put(route('resources.update', [
                'resource' => 'assembly-attendances',
                'record' => $attendance->id,
            ]), [
                'assembly_id' => $assembly->id,
                'customer_id' => $customer->id,
                'attended' => true,
                'notes' => 'Corrección posterior al cobro',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_ACTIVE]);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'fine_id' => $fine->id,
            'amount' => 10,
        ]);
        $this->assertSame($balanceBefore, app(CashService::class)->currentBalance()['balance']);
    }
}
