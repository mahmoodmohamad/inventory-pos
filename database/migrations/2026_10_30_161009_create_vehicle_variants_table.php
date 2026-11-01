<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_model_id')
                ->constrained('vehicle_models')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug');
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('engine_code')->nullable();
            $table->unsignedSmallInteger('engine_capacity_cc')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('transmission')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vehicle_model_id', 'slug']);
        });

        DB::statement(
            'ALTER TABLE vehicle_variants ADD CONSTRAINT vehicle_variants_year_range_check '
            .'CHECK (year_to IS NULL OR year_to >= year_from)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_variants');
    }
};