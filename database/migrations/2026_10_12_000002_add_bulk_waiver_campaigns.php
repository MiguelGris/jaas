<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiver_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_token')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reason', 250);
            $table->string('mode', 20);
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('total_late_fees', 12, 2);
            $table->decimal('total_fines', 12, 2);
            $table->unsignedInteger('customer_count');
            $table->unsignedInteger('debt_count');
            $table->timestamps();
        });
        Schema::table('late_fee_waivers', fn (Blueprint $table) => $table->foreignId('campaign_id')->nullable()->constrained('waiver_campaigns')->restrictOnDelete());
        Schema::create('fine_waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fine_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('waiver_campaigns')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 250);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_waivers');
        Schema::table('late_fee_waivers', fn (Blueprint $table) => $table->dropConstrainedForeignId('campaign_id'));
        Schema::dropIfExists('waiver_campaigns');
    }
};
