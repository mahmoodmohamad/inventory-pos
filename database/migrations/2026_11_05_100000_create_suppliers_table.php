<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('alternate_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // لازم لأي composite FK بيشاور على suppliers (purchases, supplier_payments)
            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'name']);
            $table->index(['shop_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
