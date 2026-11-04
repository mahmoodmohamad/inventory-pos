<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sequences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('sequence_key', 50);
            $table->unsignedBigInteger('last_number')->default(0);

            $table->timestamps();

            $table->unique(['shop_id', 'sequence_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_sequences');
    }
};
