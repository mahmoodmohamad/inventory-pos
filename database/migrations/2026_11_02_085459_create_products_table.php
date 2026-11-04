<?php

// database/migrations/2026_11_02_100002_create_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->tenantForeign('category_id', nullable: true);
            $table->tenantForeign('brand_id', nullable: true);

            $table->string('name');
            $table->string('part_number', 100)->nullable();
            $table->string('oem_number', 100)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('condition', 20)->default('original');
            $table->string('unit', 20)->default('piece');

            $table->decimal('avg_cost', 12, 4)->default(0);
            $table->decimal('last_cost', 12, 4)->default(0);
            $table->decimal('retail_price', 12, 2)->default(0);
            $table->decimal('wholesale_price', 12, 2)->default(0);

            $table->decimal('stock_qty', 12, 3)->default(0);
            $table->decimal('min_stock_alert', 12, 3)->default(0);

            $table->string('location', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'id']);
            $table->unique(['shop_id', 'barcode']);   // NULL مسموح يتكرر في MySQL
            $table->index(['shop_id', 'part_number']);
            $table->index(['shop_id', 'oem_number']);
            $table->fullText('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
