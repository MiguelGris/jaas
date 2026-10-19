<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Support\ListPagination;
use App\Support\ResourcePresenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SharedPresentationTest extends TestCase
{
    public function test_shared_page_sizes_accept_the_public_contract_and_reject_other_values(): void
    {
        $this->assertSame(25, ListPagination::DEFAULT_SIZE);
        foreach ([25, 50, 100, 200] as $size) {
            $this->assertFalse(Validator::make(['per_page' => $size], ['per_page' => ListPagination::rules()])->fails());
        }
        foreach ([10, 20, 0, 201, 'bad', [25]] as $size) {
            $this->assertTrue(Validator::make(['per_page' => $size], ['per_page' => ListPagination::rules()])->fails());
        }
    }

    public function test_shared_presenter_preserves_hours_and_surname_first_user_relations(): void
    {
        $record = new Payment(['paid_at' => Carbon::parse('2026-10-19 14:35:00')]);
        $this->assertSame('19/10/2026 14:35', ResourcePresenter::value('payments', $record, 'paid_at', ['type' => 'datetime-local']));
        $this->assertSame('19/10/2026', ResourcePresenter::value('payments', $record, 'paid_at', ['type' => 'date']));
        $record->setRelation('user', new User(['name' => 'Ana María', 'last_name' => 'Pérez Huanca']));
        $field = ['type' => 'select', 'relation' => 'user', 'options' => ['label' => 'name']];
        $this->assertSame('Pérez Huanca Ana María', ResourcePresenter::value('payments', $record, 'user_id', $field));
        $record->setRelation('user', null);
        $this->assertNull(ResourcePresenter::value('payments', $record, 'user_id', $field));
    }

    public function test_shared_presenter_keeps_zero_amounts_choices_and_catalog_labels(): void
    {
        $record = new Payment(['amount' => 0, 'status' => 'ACTIVE']);
        $this->assertSame('0.00', ResourcePresenter::value('payments', $record, 'amount', ['type' => 'number']));
        $this->assertSame('Vigente', ResourcePresenter::value('payments', $record, 'status', ['type' => 'select', 'choices' => ['ACTIVE' => 'Vigente']]));
        $user = new User(['active' => false]);
        $this->assertSame('No', ResourcePresenter::value('users', $user, 'active', ['type' => 'checkbox']));
        $setting = new Setting(['key' => 'billing_issue_day']);
        $this->assertSame('Día de emisión de cuotas', ResourcePresenter::value('settings', $setting, 'key', ['type' => 'text']));
        $audit = new AuditLog(['table_name' => 'customers']);
        $this->assertSame('Clientes', ResourcePresenter::value('audit-logs', $audit, 'table_name', ['type' => 'text']));
    }
}
