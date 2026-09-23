<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('late_fee_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('grace_months')->default(1)->after('grace_days');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('invoice_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->change();
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('charge_type', 20);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('fine_id')->nullable()->constrained('fines')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->index('invoice_id');
            $table->index('fine_id');
            $table->unique(['payment_id', 'invoice_id']);
            $table->unique(['payment_id', 'fine_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['connection_id', 'period_starts_on'], 'invoices_connection_period_unique');
        });

        $this->preserveHistoricalPayments();
        $this->prepareExistingConnections();

        DB::table('late_fee_settings')->update(['grace_months' => 1]);
        DB::table('settings')->updateOrInsert(
            ['key' => 'billing_period_months'],
            ['value' => '3', 'description' => 'Meses por ciclo de pago obligatorio: 3 (trimestral) o 6 (semestral).', 'updated_at' => now()],
        );
        DB::table('settings')->updateOrInsert(
            ['key' => 'billing_issue_day'],
            ['value' => '28', 'description' => 'Día fijo de emisión de las cuotas mensuales.', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_connection_period_unique');
        });

        Schema::dropIfExists('payment_allocations');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('late_fee_settings', function (Blueprint $table): void {
            $table->dropColumn('grace_months');
        });

        DB::table('settings')->where('key', 'billing_issue_day')->delete();
    }

    private function preserveHistoricalPayments(): void
    {
        DB::table('payments')->orderBy('id')->each(function (object $payment): void {
            if ($payment->invoice_id === null) {
                return;
            }

            $customerId = DB::table('invoices')
                ->join('connections', 'connections.id', '=', 'invoices.connection_id')
                ->join('properties', 'properties.id', '=', 'connections.property_id')
                ->where('invoices.id', $payment->invoice_id)
                ->value('properties.customer_id');

            if ($customerId !== null) {
                DB::table('payments')->where('id', $payment->id)->update(['customer_id' => $customerId]);
            }

            DB::table('payment_allocations')->updateOrInsert(
                ['payment_id' => $payment->id, 'invoice_id' => $payment->invoice_id],
                ['charge_type' => 'INVOICE', 'fine_id' => null, 'amount' => $payment->amount],
            );
        });

        DB::table('invoices')->orderBy('id')->each(function (object $invoice): void {
            $paid = (float) DB::table('payment_allocations')->where('invoice_id', $invoice->id)->sum('amount');

            if ($paid >= (float) $invoice->total) {
                DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'PAID']);
            }
        });
    }

    private function prepareExistingConnections(): void
    {
        $residentialId = DB::table('usage_types')->where('name', 'RESIDENTIAL')->value('id');

        if ($residentialId === null) {
            return;
        }

        DB::table('connections')->orderBy('id')->each(function (object $connection) use ($residentialId): void {
            $hasUsage = DB::table('connection_usage_types')
                ->where('connection_id', $connection->id)
                ->exists();

            if (! $hasUsage) {
                DB::table('connection_usage_types')->insert([
                    'connection_id' => $connection->id,
                    'usage_type_id' => $residentialId,
                    'starts_on' => $connection->installed_on ?? now()->toDateString(),
                    'ends_on' => null,
                ]);
            }
        });
    }
};
