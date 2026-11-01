<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array{name_ar: string, models: array<string, string>}>
     */
    private const CATALOG = [
        'Toyota' => [
            'name_ar' => 'تويوتا',
            'models' => [
                'Corolla' => 'كورولا',
                'Yaris' => 'يارس',
                'Camry' => 'كامري',
                'Hilux' => 'هايلكس',
                'Land Cruiser' => 'لاند كروزر',
                'Fortuner' => 'فورتشنر',
            ],
        ],
        'Nissan' => [
            'name_ar' => 'نيسان',
            'models' => [
                'Sunny' => 'صني',
                'Sentra' => 'سنترا',
                'Qashqai' => 'قشقاي',
                'Juke' => 'جوك',
                'X-Trail' => 'إكس تريل',
            ],
        ],
        'Hyundai' => [
            'name_ar' => 'هيونداي',
            'models' => [
                'Elantra' => 'إلنترا',
                'Accent' => 'أكسنت',
                'Verna' => 'فيرنا',
                'Tucson' => 'توسان',
                'Creta' => 'كريتا',
                'i10' => 'i10',
            ],
        ],
        'Kia' => [
            'name_ar' => 'كيا',
            'models' => [
                'Cerato' => 'سيراتو',
                'Sportage' => 'سبورتاج',
                'Picanto' => 'بيكانتو',
                'Rio' => 'ريو',
                'Sorento' => 'سورينتو',
            ],
        ],
        'Chevrolet' => [
            'name_ar' => 'شيفروليه',
            'models' => [
                'Optra' => 'أوبترا',
                'Aveo' => 'أفيو',
                'Lanos' => 'لانوس',
                'Cruze' => 'كروز',
                'Captiva' => 'كابتيفا',
            ],
        ],
        'Renault' => [
            'name_ar' => 'رينو',
            'models' => [
                'Logan' => 'لوجان',
                'Duster' => 'داستر',
                'Megane' => 'ميجان',
                'Fluence' => 'فلوانس',
                'Clio' => 'كليو',
            ],
        ],
        'Skoda' => [
            'name_ar' => 'سكودا',
            'models' => [
                'Octavia' => 'أوكتافيا',
                'Fabia' => 'فابيا',
                'Rapid' => 'رابيد',
                'Superb' => 'سوبيرب',
            ],
        ],
        'Mitsubishi' => [
            'name_ar' => 'ميتسوبيشي',
            'models' => [
                'Lancer' => 'لانسر',
                'Attrage' => 'أتراج',
                'Pajero' => 'باجيرو',
            ],
        ],
        'Peugeot' => [
            'name_ar' => 'بيجو',
            'models' => [
                '206' => '206',
                '301' => '301',
                '508' => '508',
                '2008' => '2008',
            ],
        ],
        'Fiat' => [
            'name_ar' => 'فيات',
            'models' => [
                'Tipo' => 'تيبو',
                'Punto' => 'بونتو',
                'Uno' => 'أونو',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $makeName => $makeData) {
            $make = VehicleMake::updateOrCreate(
                ['slug' => Str::slug($makeName)],
                ['name' => $makeName, 'is_active' => true],
            );

            foreach ($makeData['models'] as $modelName => $modelNameAr) {
                VehicleModel::updateOrCreate(
                    ['vehicle_make_id' => $make->id, 'slug' => Str::slug($modelName)],
                    ['name' => $modelName, 'name_ar' => $modelNameAr, 'is_active' => true],
                );
            }
        }
    }
}