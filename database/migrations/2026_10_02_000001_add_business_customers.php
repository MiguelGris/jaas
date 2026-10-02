<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('customer_type', 20)->default('PERSON')->after('id');
            $table->string('business_name', 200)->nullable()->after('last_name');
        });

        // Las empresas se identifican por razón social, por lo que no deben
        // almacenar nombres personales ni fecha de nacimiento.
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable()->change();
            $table->string('last_name', 100)->nullable()->change();
        });

        $this->refreshCustomerViews(true);
    }

    public function down(): void
    {
        $this->refreshCustomerViews(false);

        // Conserva una representación legible si se revierte con empresas ya
        // registradas antes de restaurar las columnas obligatorias antiguas.
        DB::table('customers')
            ->whereNull('first_name')
            ->update(['first_name' => 'Empresa']);
        DB::table('customers')
            ->whereNull('last_name')
            ->update(['last_name' => '']);

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable(false)->change();
            $table->string('last_name', 100)->nullable(false)->change();
            $table->dropColumn(['customer_type', 'business_name']);
        });
    }

    private function refreshCustomerViews(bool $supportsBusinesses): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $customerExpression = $supportsBusinesses
            ? "CASE WHEN customers.customer_type = 'BUSINESS' THEN customers.business_name ELSE CONCAT_WS(' ', customers.first_name, customers.last_name) END"
            : "CONCAT(customers.first_name, ' ', customers.last_name)";
        $groupColumns = $supportsBusinesses
            ? 'customers.id, customers.customer_type, customers.business_name, customers.first_name, customers.last_name'
            : 'customers.id, customers.first_name, customers.last_name';

        DB::unprepared("CREATE OR REPLACE VIEW vw_customer_account AS
            SELECT customers.id AS customer_id, {$customerExpression} AS customer, SUM(invoices.total) AS total_debt
            FROM customers
            INNER JOIN properties ON properties.customer_id = customers.id
            INNER JOIN connections ON connections.property_id = properties.id
            INNER JOIN invoices ON invoices.connection_id = connections.id
            WHERE invoices.status = 'PENDING'
            GROUP BY {$groupColumns}");

        DB::unprepared("CREATE OR REPLACE VIEW vw_delinq_customers AS
            SELECT customers.id AS customer_id, {$customerExpression} AS customer,
                COUNT(invoices.id) AS pending_invoices, SUM(invoices.total) AS debt
            FROM customers
            INNER JOIN properties ON properties.customer_id = customers.id
            INNER JOIN connections ON connections.property_id = properties.id
            INNER JOIN invoices ON invoices.connection_id = connections.id
            WHERE invoices.status = 'PENDING'
            GROUP BY {$groupColumns}");
    }
};
