<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            $table->tenantForeign('customer_id', 'customers');
            $table->tenantForeign('sale_id', 'sales', nullable: true);
            $table->tenantForeign('payment_id', 'payments', nullable: true);
            $table->tenantForeign('created_by', 'users');

            // invoice, payment, return, cancellation, adjustment
            $table->string('source', 20);

            // debit = customer owes the shop, credit = customer paid / has credit
            $table->string('direction', 10);

            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');

            // Append-only ledger: no updated_at.
            $table->timestamp('created_at')->useCurrent();

            // قيد واحد بس للفاتورة وقيد واحد بس للإلغاء. المدفوعات والتسويات متعددة.
            $table->string('sale_event_key', 40)->nullable()->storedAs(
                "CASE WHEN source IN ('invoice','cancellation') THEN CONCAT(source, ':', sale_id) END"
            );

            $table->unique(['shop_id', 'id']);
            $table->unique(['shop_id', 'payment_id']);
            $table->unique(['shop_id', 'sale_event_key']);

            $table->index(['shop_id', 'customer_id', 'created_at'], 'customer_ledgers_customer_date_index');
            $table->index(['shop_id', 'sale_id']);
        });

        $this->check('customer_ledgers_source_valid', "source IN ('invoice','payment','return','cancellation','adjustment')");
        $this->check('customer_ledgers_direction_valid', "direction IN ('debit','credit')");
        $this->check('customer_ledgers_amount_positive', 'amount > 0');
        $this->check(
            'customer_ledgers_sale_required',
            "source NOT IN ('invoice','cancellation') OR sale_id IS NOT NULL"
        );
        $this->check(
            'customer_ledgers_payment_required',
            "source <> 'payment' OR payment_id IS NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledgers');
    }

    private function check(string $name, string $expression): void
    {
        DB::statement("ALTER TABLE customer_ledgers ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
