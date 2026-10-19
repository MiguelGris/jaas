<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBillingContext;
use Tests\TestCase;

class NamePresentationTest extends TestCase
{
    use RefreshDatabase, CreatesBillingContext;

    public function test_views_and_reports_show_surnames_first_without_rewriting_names(): void
    {
        [$user, $customers, , $method] = $this->context();
        $user->update(['name' => 'Juan Luis', 'last_name' => 'Quispe Gómez']);
        $customer = $customers->first();
        $customer->update(['first_name' => 'Rosa María', 'last_name' => 'Huanca Pérez']);
        $this->assertSame('Huanca Pérez Rosa María', $customer->display_name);
        $this->assertSame('Quispe Gómez Juan Luis', $user->display_name);
        $this->actingAs($user)->get(route('resources.index', ['resource' => 'customers']))
            ->assertOk()->assertSee('Huanca Pérez Rosa María')->assertSee('Quispe Gómez Juan Luis')
            ->assertDontSee('Rosa María Huanca Pérez');
        $this->get(route('collections.create', ['customer_id' => $customer->id]))
            ->assertOk()->assertSee('Huanca Pérez Rosa María');
        $reports = app(ReportService::class);
        $this->assertSame('Huanca Pérez Rosa María', $reports->build('customer-register')['rows'][0][3]);
        $this->assertSame('Huanca Pérez Rosa María', $reports->build('service-register')['rows'][0][4]);
        Payment::query()->create(['customer_id' => $customer->id, 'user_id' => $user->id, 'amount' => 10,
            'payment_method_id' => $method->id, 'paid_at' => now(), 'source' => 'COUNTER', 'status' => 'ACTIVE']);
        $history = $reports->build('customer-payment-history', ['customer_id' => $customer->id, 'period_type' => 'all']);
        $this->assertSame('Huanca Pérez Rosa María', $history['rows'][0][4]);
        $this->assertSame('Quispe Gómez Juan Luis', $history['rows'][0][9]);
        $this->assertSame('Rosa María', $customer->fresh()->first_name);
        $this->assertSame('Huanca Pérez', $customer->fresh()->last_name);
        $this->assertSame('Juan Luis', $user->fresh()->name);
    }

    public function test_institutions_and_missing_name_parts_remain_readable(): void
    {
        $this->assertSame('Centro de Salud Pucará', (new Customer(['customer_type' => 'BUSINESS', 'business_name' => 'Centro de Salud Pucará']))->display_name);
        $this->assertSame('Rosa', (new Customer(['first_name' => 'Rosa']))->display_name);
        $this->assertSame('Huanca', (new Customer(['last_name' => 'Huanca']))->display_name);
        $this->assertSame('Cliente sin nombre', (new Customer())->display_name);
        $this->assertSame('Juan', (new User(['name' => 'Juan']))->display_name);
        $this->assertSame('Gómez', (new User(['last_name' => 'Gómez']))->display_name);
    }
}
