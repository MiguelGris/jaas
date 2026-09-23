<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE VIEW vw_customer_account AS
            SELECT
                customers.id AS customer_id,
                CONCAT(customers.first_name, ' ', customers.last_name) AS customer,
                SUM(invoices.total) AS total_debt
            FROM customers
            INNER JOIN properties ON properties.customer_id = customers.id
            INNER JOIN connections ON connections.property_id = properties.id
            INNER JOIN invoices ON invoices.connection_id = connections.id
            WHERE invoices.status = 'PENDING'
            GROUP BY customers.id, customers.first_name, customers.last_name
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE VIEW vw_monthly_incomes AS
            SELECT YEAR(received_on) AS year, MONTH(received_on) AS month, SUM(amount) AS total
            FROM incomes
            GROUP BY YEAR(received_on), MONTH(received_on)
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE VIEW vw_monthly_expenses AS
            SELECT YEAR(incurred_on) AS year, MONTH(incurred_on) AS month, SUM(amount) AS total
            FROM expenses
            GROUP BY YEAR(incurred_on), MONTH(incurred_on)
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE VIEW vw_delinq_customers AS
            SELECT
                customers.id AS customer_id,
                CONCAT(customers.first_name, ' ', customers.last_name) AS customer,
                COUNT(invoices.id) AS pending_invoices,
                SUM(invoices.total) AS debt
            FROM customers
            INNER JOIN properties ON properties.customer_id = customers.id
            INNER JOIN connections ON connections.property_id = properties.id
            INNER JOIN invoices ON invoices.connection_id = connections.id
            WHERE invoices.status = 'PENDING'
            GROUP BY customers.id, customers.first_name, customers.last_name
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE generate_assembly_fines(IN target_assembly_id BIGINT)
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM assemblies WHERE id = target_assembly_id) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The requested assembly does not exist';
                END IF;

                INSERT INTO fines (customer_id, assembly_id, reason, amount, generated_on)
                SELECT
                    attendances.customer_id,
                    attendances.assembly_id,
                    'Assembly absence',
                    assemblies.absence_fine,
                    CURDATE()
                FROM assembly_attendances AS attendances
                INNER JOIN assemblies ON assemblies.id = attendances.assembly_id
                INNER JOIN customers ON customers.id = attendances.customer_id
                INNER JOIN customer_statuses ON customer_statuses.id = customers.customer_status_id
                WHERE attendances.assembly_id = target_assembly_id
                    AND attendances.attended = FALSE
                    AND customer_statuses.name <> 'EXEMPT'
                    AND assemblies.absence_fine > 0
                    AND NOT EXISTS (
                        SELECT 1
                        FROM fines
                        WHERE fines.customer_id = attendances.customer_id
                            AND fines.assembly_id = attendances.assembly_id
                    );
            END
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS generate_assembly_fines');
        DB::unprepared('DROP VIEW IF EXISTS vw_delinq_customers');
        DB::unprepared('DROP VIEW IF EXISTS vw_monthly_expenses');
        DB::unprepared('DROP VIEW IF EXISTS vw_monthly_incomes');
        DB::unprepared('DROP VIEW IF EXISTS vw_customer_account');
    }
};
