<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            $table->tenantForeign('supplier_id', 'suppliers');
            $table->tenantForeign('received_by', 'users');

            $table->unsignedBigInteger('invoice_number');
            $table->string('supplier_invoice_ref', 100)->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamp('received_at');

            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->unique(['shop_id', 'invoice_number']);
            $table->index(['shop_id', 'supplier_id', 'received_at'], 'purchases_supplier_received_index');
        });

        DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_total_valid CHECK (total_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
