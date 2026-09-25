<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingIssueDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_issue_day_is_configurable_and_never_exceeds_februarys_safe_day(): void
    {
        $billing = app(BillingService::class);

        $this->assertSame(28, $billing->issueDay());

        Setting::query()->updateOrCreate(
            ['key' => 'billing_issue_day'],
            ['value' => '28', 'description' => 'Día fijo de emisión de las cuotas mensuales.'],
        );
        $this->assertSame(28, $billing->issueDay());

        Setting::query()->where('key', 'billing_issue_day')->update(['value' => '15']);
        $this->assertSame(15, $billing->issueDay());

        Setting::query()->where('key', 'billing_issue_day')->update(['value' => '31']);
        $this->assertSame(28, $billing->issueDay());
    }
}
