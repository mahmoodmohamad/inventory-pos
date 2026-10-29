<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'parent_id']);

            // الأب لازم يكون من نفس المحل (على مستوى الـ DB)
            $table->foreign(['shop_id', 'parent_id'])
                ->references(['shop_id', 'id'])->on('categories')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
