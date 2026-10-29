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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')
                ->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('cashier')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->unique(['shop_id', 'id']); // لازم لأي composite FK بيشاور على users
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'id']);
            $table->dropConstrainedForeignId('shop_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
