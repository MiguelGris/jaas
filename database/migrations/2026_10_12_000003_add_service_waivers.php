<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_waivers', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_code', 50)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('waiver_campaigns')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reason', 250);
            $table->timestamps();
        });
        Schema::table('waiver_campaigns', fn (Blueprint $table) => $table->decimal('total_services', 12, 2)->default(0));
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->decimal('service_component', 12, 2)->nullable();
            $table->decimal('fine_component', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', fn (Blueprint $table) => $table->dropColumn(['service_component', 'fine_component']));
        Schema::table('waiver_campaigns', fn (Blueprint $table) => $table->dropColumn('total_services'));
        Schema::dropIfExists('service_waivers');
    }
};
