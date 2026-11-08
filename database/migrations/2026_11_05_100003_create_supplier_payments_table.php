<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            $table->tenantForeign('supplier_id', 'suppliers');
            $table->tenantForeign('purchase_id', 'purchases', nullable: true);
            $table->tenantForeign('paid_by', 'users');

            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');

            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'supplier_id', 'paid_at'], 'supplier_payments_supplier_paid_index');
        });

        DB::statement('ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_amount_positive CHECK (amount > 0)');
        DB::statement("ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_method_valid CHECK (method IN ('cash','card','bank_transfer','wallet','other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
