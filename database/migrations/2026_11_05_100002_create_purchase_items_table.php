<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            $table->tenantForeign('purchase_id', 'purchases');
            $table->tenantForeign('product_id', 'products');

            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'purchase_id']);
            $table->index(['shop_id', 'product_id']);
        });

        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT purchase_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT purchase_items_amounts_valid CHECK (unit_cost >= 0 AND line_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
