<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VehicleVariant>
 */
class VehicleVariantFactory extends Factory
{
    protected $model = VehicleVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $yearFrom = fake()->numberBetween(2005, 2020);

        return [
            'vehicle_model_id' => VehicleModel::factory(),
            'name' => $name,
            'name_ar' => null,
            'slug' => Str::slug($name.' '.$yearFrom),
            'year_from' => $yearFrom,
            'year_to' => $yearFrom + fake()->numberBetween(1, 6),
            'engine_code' => null,
            'engine_capacity_cc' => fake()->randomElement([1000, 1300, 1600, 2000]),
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'transmission' => fake()->randomElement(Transmission::cases()),
            'is_active' => true,
        ];
    }
}