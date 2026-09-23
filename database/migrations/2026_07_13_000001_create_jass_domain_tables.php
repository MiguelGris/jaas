<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('permission_id')->constrained('permissions');
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 255)->nullable();
        });

        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('connection_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 200)->nullable();
        });

        Schema::create('connection_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 200)->nullable();
        });

        Schema::create('usage_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 200)->nullable();
        });

        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->integer('months');
            $table->string('description', 100)->nullable();
        });

        Schema::create('late_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_amount', 10, 2);
            $table->integer('grace_days')->default(30);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
        });

        Schema::create('assembly_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 200)->nullable();
        });

        Schema::create('income_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('national_id', 20)->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 250)->nullable();
            $table->foreignId('customer_status_id')->constrained('customer_statuses');
            $table->date('registered_on');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['last_name', 'first_name'], 'customers_name_index');
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('neighborhood_id')->constrained('neighborhoods');
            $table->string('address', 250);
            $table->string('reference', 250)->nullable();
            $table->string('property_code', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties');
            $table->string('supply_code', 50)->unique();
            $table->foreignId('connection_type_id')->constrained('connection_types');
            $table->foreignId('connection_status_id')->constrained('connection_statuses');
            $table->date('installed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('connection_usage_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections');
            $table->foreignId('usage_type_id')->constrained('usage_types');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->index(['connection_id', 'ends_on'], 'connection_usage_current_index');
        });

        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usage_type_id')->constrained('usage_types');
            $table->year('year');
            $table->decimal('amount', 10, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('approved_by_assembly')->default(false);
            $table->string('notes', 250)->nullable();
            $table->unique(['usage_type_id', 'year']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections');
            $table->foreignId('billing_period_id')->constrained('billing_periods');
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->decimal('rate', 10, 2);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->decimal('fines', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['PENDING', 'PAID', 'CANCELLED'])->default('PENDING');
            $table->timestamps();
            $table->index('status');
            $table->index('due_on');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->dateTime('paid_at');
            $table->decimal('amount', 10, 2);
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->enum('source', ['COUNTER', 'WEB']);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('operation_number', 100)->nullable();
            $table->string('notes', 250)->nullable();
            $table->index('paid_at');
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('connections');
            $table->string('meter_number', 50)->unique();
            $table->date('installed_on')->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained('meters');
            $table->date('read_on');
            $table->decimal('current_reading', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('notes', 250)->nullable();
        });

        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_type_id')->constrained('assembly_types');
            $table->date('held_on');
            $table->time('held_at')->nullable();
            $table->string('place', 250)->nullable();
            $table->text('description')->nullable();
            $table->decimal('absence_fine', 10, 2)->default(0);
            $table->enum('status', ['SCHEDULED', 'HELD', 'CANCELLED'])->default('SCHEDULED');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('assembly_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_id')->constrained('assemblies');
            $table->foreignId('customer_id')->constrained('customers');
            $table->boolean('attended')->default(false);
            $table->string('notes', 250)->nullable();
            $table->unique(['assembly_id', 'customer_id']);
        });

        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('assembly_id')->constrained('assemblies');
            $table->string('reason', 250)->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('generated_on');
            $table->enum('status', ['PENDING', 'PAID', 'CANCELLED'])->default('PENDING');
            $table->unique(['customer_id', 'assembly_id']);
            $table->index('status');
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_type_id')->constrained('income_types');
            $table->date('received_on');
            $table->string('concept', 250)->nullable();
            $table->decimal('amount', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('reference', 100)->nullable();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->date('incurred_on');
            $table->string('concept', 250)->nullable();
            $table->decimal('amount', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('receipt', 100)->nullable();
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->tinyInteger('month');
            $table->decimal('total_income', 10, 2)->default(0);
            $table->decimal('total_expense', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->dateTime('closed_at')->nullable();
            $table->unique(['year', 'month']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('table_name', 100);
            $table->bigInteger('record_id');
            $table->enum('action', ['INSERT', 'UPDATE', 'DELETE']);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->dateTime('occurred_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('fines');
        Schema::dropIfExists('assembly_attendances');
        Schema::dropIfExists('assemblies');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('rates');
        Schema::dropIfExists('connection_usage_types');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('income_types');
        Schema::dropIfExists('assembly_types');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('late_fee_settings');
        Schema::dropIfExists('billing_periods');
        Schema::dropIfExists('usage_types');
        Schema::dropIfExists('connection_statuses');
        Schema::dropIfExists('connection_types');
        Schema::dropIfExists('neighborhoods');
        Schema::dropIfExists('customer_statuses');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
};
