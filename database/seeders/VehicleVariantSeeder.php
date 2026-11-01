<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Seeder;

/**
 * Idempotent seeder for the shared catalog; needs no tenant context.
 * Call it from VehicleCatalogSeeder AFTER makes and models are seeded.
 */
class VehicleVariantSeeder extends Seeder
{
    public function run(): void
    {
        $variants = [
            [
                'make' => 'toyota', 'model' => 'corolla',
                'slug' => '1-6-2014-2018', 'name' => '1.6', 'name_ar' => '١٫٦',
                'year_from' => 2014, 'year_to' => 2018,
                'engine_code' => '1ZR-FE', 'engine_capacity_cc' => 1600,
                'fuel_type' => FuelType::Petrol, 'transmission' => Transmission::Automatic,
            ],
            [
                'make' => 'toyota', 'model' => 'corolla',
                'slug' => '1-6-2019-2022', 'name' => '1.6', 'name_ar' => '١٫٦',
                'year_from' => 2019, 'year_to' => 2022,
                'engine_code' => '1ZR-FAE', 'engine_capacity_cc' => 1600,
                'fuel_type' => FuelType::Petrol, 'transmission' => Transmission::Cvt,
            ],
            [
                'make' => 'hyundai', 'model' => 'elantra',
                'slug' => '1-6-2016-2020', 'name' => '1.6', 'name_ar' => '١٫٦',
                'year_from' => 2016, 'year_to' => 2020,
                'engine_code' => 'G4FG', 'engine_capacity_cc' => 1600,
                'fuel_type' => FuelType::Petrol, 'transmission' => Transmission::Automatic,
            ],
        ];

        foreach ($variants as $row) {
            $make = VehicleMake::query()->where('slug', $row['make'])->first();
            $model = $make === null
                ? null
                : VehicleModel::query()
                    ->where('vehicle_make_id', $make->id)
                    ->where('slug', $row['model'])
                    ->first();

            if ($model === null) {
                continue;
            }

            VehicleVariant::query()->updateOrCreate(
                ['vehicle_model_id' => $model->id, 'slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'name_ar' => $row['name_ar'],
                    'year_from' => $row['year_from'],
                    'year_to' => $row['year_to'],
                    'engine_code' => $row['engine_code'],
                    'engine_capacity_cc' => $row['engine_capacity_cc'],
                    'fuel_type' => $row['fuel_type'],
                    'transmission' => $row['transmission'],
                    'is_active' => true,
                ],
            );
        }
    }
}