<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            $table->tenantForeign('sale_id', 'sales');
            $table->tenantForeign('product_id', 'products');

            // Product snapshots at sale time.
            $table->string('product_name');
            $table->string('barcode', 64)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->string('oem_number', 100)->nullable();
            $table->string('condition', 20);
            $table->string('unit', 20);

            $table->decimal('quantity', 12, 3);

            // original = catalog price for the sale's price tier; unit_price = applied price.
            $table->decimal('original_unit_price', 12, 2);
            $table->decimal('unit_price', 12, 2);

            // Who authorised a manual price override (required when the prices differ).
            $table->tenantForeign('price_overridden_by', 'users', nullable: true);

            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            // WAC snapshot at the time of sale.
            $table->decimal('unit_cost', 12, 4);

            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'sale_id']);
            $table->index(['shop_id', 'product_id']);
        });

        $this->check('sale_items_quantity_positive', 'quantity > 0');
        $this->check(
            'sale_items_amounts_valid',
            'original_unit_price >= 0 AND unit_price >= 0 AND discount_amount >= 0 '
            .'AND line_total >= 0 AND unit_cost >= 0'
        );
        $this->check(
            'sale_items_override_requires_user',
            'unit_price = original_unit_price OR price_overridden_by IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }

    private function check(string $name, string $expression): void
    {
        DB::statement("ALTER TABLE sale_items ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
