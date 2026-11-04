<?php

// database/migrations/2026_11_02_100003_create_product_fitments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->tenantForeign('product_id');
            // جدول مشترك (من غير shop_id) فـ FK عادي
            $table->foreignId('vehicle_variant_id')
                ->constrained('vehicle_variants')
                ->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->unique(['shop_id', 'product_id', 'vehicle_variant_id'], 'fitments_product_variant_unique');
            $table->index(['shop_id', 'vehicle_variant_id'], 'fitments_shop_variant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_fitments');
    }
};
