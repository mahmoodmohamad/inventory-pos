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
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            // كتالوج مشترك: مفيش shop_id ولا tenantForeign
            $table->foreignId('vehicle_make_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // الـ slug unique جوه كل شركة، مش على مستوى الجدول كله
            $table->unique(['vehicle_make_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
