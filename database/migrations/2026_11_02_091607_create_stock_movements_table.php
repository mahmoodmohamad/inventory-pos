<?php

// database/migrations/2026_11_02_100004_create_stock_movements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->tenantForeign('product_id');
            $table->tenantForeign('user_id', 'users', nullable: true);

            $table->string('type', 20);
            $table->decimal('quantity', 12, 3);          // موقّع: + دخول / - خروج
            $table->decimal('qty_after', 12, 3);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('avg_cost_after', 12, 4);

            // morph من غير FK (محمي بالكود والاختبارات)
            $table->nullableMorphs('reference');
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'product_id', 'id'], 'stock_movements_product_ledger_index');
            $table->index(['shop_id', 'type', 'created_at'], 'stock_movements_type_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
