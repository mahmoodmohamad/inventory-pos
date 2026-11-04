<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            // دفعة على فاتورة (sale_id) أو تحصيل عام من العميل (customer_id) أو الاتنين.
            $table->tenantForeign('sale_id', 'sales', nullable: true);
            $table->tenantForeign('customer_id', 'customers', nullable: true);
            $table->tenantForeign('received_by', 'users');

            $table->decimal('amount', 12, 2);

            // cash, card, bank_transfer, wallet, other
            $table->string('method', 30);

            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');

            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'sale_id', 'paid_at'], 'payments_shop_sale_paid_index');
            $table->index(['shop_id', 'customer_id', 'paid_at'], 'payments_shop_customer_paid_index');
        });

        $this->check('payments_amount_positive', 'amount > 0');
        $this->check('payments_method_valid', "method IN ('cash','card','bank_transfer','wallet','other')");
        $this->check('payments_has_target', 'sale_id IS NOT NULL OR customer_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }

    private function check(string $name, string $expression): void
    {
        DB::statement("ALTER TABLE payments ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
