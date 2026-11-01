<?php

namespace Database\Seeders;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * كتالوج السيارات المشترك (مفيش shop_id). آمن للتشغيل أكتر من مرة.
 */
class VehicleCatalogSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const CATALOG = [
        'Toyota' => ['Corolla', 'Yaris', 'Camry', 'Hilux', 'Land Cruiser'],
        'Nissan' => ['Sunny', 'Sentra', 'Qashqai', 'Juke'],
        'Hyundai' => ['Elantra', 'Accent', 'Verna', 'Tucson', 'Creta'],
        'Kia' => ['Cerato', 'Sportage', 'Picanto', 'Rio', 'Sorento'],
        'Chevrolet' => ['Optra', 'Aveo', 'Lanos', 'Cruze'],
        'Renault' => ['Logan', 'Duster', 'Megane', 'Fluence', 'Clio'],
        'Skoda' => ['Octavia', 'Fabia', 'Rapid', 'Superb'],
        'Mitsubishi' => ['Lancer', 'Attrage', 'Pajero'],
        'Peugeot' => ['206', '301', '508'],
        'Fiat' => ['Tipo', 'Punto', 'Uno'],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $makeName => $modelNames) {
            $make = VehicleMake::updateOrCreate(
                ['slug' => Str::slug($makeName)],
                ['name' => $makeName, 'is_active' => true],
            );

            foreach ($modelNames as $modelName) {
                VehicleModel::updateOrCreate(
                    ['vehicle_make_id' => $make->id, 'slug' => Str::slug($modelName)],
                    ['name' => $modelName, 'is_active' => true],
                );
            }
        }
    }
}