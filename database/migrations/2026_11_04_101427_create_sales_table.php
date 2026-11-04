<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            $table->tenantForeign('customer_id', 'customers', nullable: true);
            $table->tenantForeign('created_by', 'users');
            $table->tenantForeign('completed_by', 'users', nullable: true);

            $table->unsignedBigInteger('invoice_number');

            // draft, completed, cancelled
            $table->string('status', 20)->default('draft');

            // retail, wholesale
            $table->string('price_type', 20)->default('retail');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->unique(['shop_id', 'invoice_number']);

            $table->index(['shop_id', 'status', 'created_at'], 'sales_shop_status_created_index');
            $table->index(['shop_id', 'customer_id', 'created_at'], 'sales_shop_customer_created_index');
        });

        $this->check('sales_status_valid', "status IN ('draft','completed','cancelled')");
        $this->check('sales_price_type_valid', "price_type IN ('retail','wholesale')");
        $this->check(
            'sales_amounts_valid',
            'subtotal >= 0 AND discount_amount >= 0 AND discount_amount <= subtotal '
            .'AND total_amount = subtotal - discount_amount'
        );
        $this->check('sales_completed_has_timestamp', "status <> 'completed' OR completed_at IS NOT NULL");
        $this->check('sales_cancelled_has_timestamp', "status <> 'cancelled' OR cancelled_at IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }

    private function check(string $name, string $expression): void
    {
        DB::statement("ALTER TABLE sales ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
