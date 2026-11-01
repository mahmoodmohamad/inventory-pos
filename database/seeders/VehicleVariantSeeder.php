<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Seeder;

class VehicleVariantSeeder extends Seeder
{
    public function run(): void
    {
        // [make_slug, model_slug, slug, name, name_ar, year_from, year_to, engine_code, cc, fuel, transmission]
        $variants = [
            // Toyota Corolla
            ['toyota', 'corolla', '1-6-2014-2018', '1.6', '١٫٦', 2014, 2018, '1ZR-FE', 1600, FuelType::Petrol, Transmission::Automatic],
            ['toyota', 'corolla', '1-6-2019-2022', '1.6', '١٫٦', 2019, 2022, '1ZR-FAE', 1600, FuelType::Petrol, Transmission::Cvt],
            ['toyota', 'corolla', '1-8-2020-2023', '1.8', '١٫٨', 2020, 2023, '2ZR-FAE', 1800, FuelType::Petrol, Transmission::Cvt],
            // Toyota Yaris
            ['toyota', 'yaris', '1-3-2014-2019', '1.3', '١٫٣', 2014, 2019, '1NR-FE', 1300, FuelType::Petrol, Transmission::Automatic],
            ['toyota', 'yaris', '1-5-2020-2024', '1.5', '١٫٥', 2020, 2024, '2NR-FE', 1500, FuelType::Petrol, Transmission::Cvt],
            // Toyota Camry
            ['toyota', 'camry', '2-5-2018-2023', '2.5', '٢٫٥', 2018, 2023, 'A25A-FKS', 2500, FuelType::Petrol, Transmission::Automatic],
            // Toyota Hilux
            ['toyota', 'hilux', '2-7-2016-2023', '2.7', '٢٫٧', 2016, 2023, '2TR-FE', 2700, FuelType::Petrol, Transmission::Manual],
            ['toyota', 'hilux', '2-4-diesel-2016-2023', '2.4 D', '٢٫٤ ديزل', 2016, 2023, '2GD-FTV', 2400, FuelType::Diesel, Transmission::Manual],
            // Nissan Sunny
            ['nissan', 'sunny', '1-5-2015-2019', '1.5', '١٫٥', 2015, 2019, 'HR15DE', 1500, FuelType::Petrol, Transmission::Automatic],
            ['nissan', 'sunny', '1-6-2020-2024', '1.6', '١٫٦', 2020, 2024, 'HR16DE', 1600, FuelType::Petrol, Transmission::Cvt],
            // Nissan Sentra
            ['nissan', 'sentra', '1-6-2016-2019', '1.6', '١٫٦', 2016, 2019, 'MRA8DE', 1600, FuelType::Petrol, Transmission::Automatic],
            // Nissan Qashqai
            ['nissan', 'qashqai', '1-2-turbo-2018-2022', '1.2 Turbo', '١٫٢ تيربو', 2018, 2022, 'H5FT', 1200, FuelType::Petrol, Transmission::Cvt],
            // Hyundai Elantra
            ['hyundai', 'elantra', '1-6-2016-2020', '1.6', '١٫٦', 2016, 2020, 'G4FG', 1600, FuelType::Petrol, Transmission::Automatic],
            ['hyundai', 'elantra', '2-0-2021-2024', '2.0', '٢٫٠', 2021, 2024, 'G4NA', 2000, FuelType::Petrol, Transmission::Cvt],
            // Hyundai Accent
            ['hyundai', 'accent', '1-4-2012-2017', '1.4', '١٫٤', 2012, 2017, 'G4FA', 1400, FuelType::Petrol, Transmission::Manual],
            ['hyundai', 'accent', '1-6-2018-2024', '1.6', '١٫٦', 2018, 2024, 'G4FG', 1600, FuelType::Petrol, Transmission::Automatic],
            // Hyundai Verna
            ['hyundai', 'verna', '1-4-2016-2020', '1.4', '١٫٤', 2016, 2020, 'G4LC', 1400, FuelType::Petrol, Transmission::Automatic],
            // Hyundai Tucson
            ['hyundai', 'tucson', '1-6-turbo-2016-2021', '1.6 Turbo', '١٫٦ تيربو', 2016, 2021, 'G4FJ', 1600, FuelType::Petrol, Transmission::Dct],
            // Hyundai Creta
            ['hyundai', 'creta', '1-5-2020-2024', '1.5', '١٫٥', 2020, 2024, 'G4FL', 1500, FuelType::Petrol, Transmission::Cvt],
            // Hyundai i10
            ['hyundai', 'i10', '1-2-2019-2024', '1.2', '١٫٢', 2019, 2024, 'G4LA', 1200, FuelType::Petrol, Transmission::Manual],
            // Kia Cerato
            ['kia', 'cerato', '1-6-2013-2018', '1.6', '١٫٦', 2013, 2018, 'G4FG', 1600, FuelType::Petrol, Transmission::Automatic],
            ['kia', 'cerato', '1-6-2019-2024', '1.6', '١٫٦', 2019, 2024, 'G4FG', 1600, FuelType::Petrol, Transmission::Cvt],
            // Kia Sportage
            ['kia', 'sportage', '2-0-2016-2021', '2.0', '٢٫٠', 2016, 2021, 'G4NA', 2000, FuelType::Petrol, Transmission::Automatic],
            // Kia Picanto
            ['kia', 'picanto', '1-2-2017-2024', '1.2', '١٫٢', 2017, 2024, 'G4LA', 1200, FuelType::Petrol, Transmission::Automatic],
            // Kia Rio
            ['kia', 'rio', '1-4-2012-2017', '1.4', '١٫٤', 2012, 2017, 'G4FA', 1400, FuelType::Petrol, Transmission::Manual],
            ['kia', 'rio', '1-6-2018-2024', '1.6', '١٫٦', 2018, 2024, 'G4FG', 1600, FuelType::Petrol, Transmission::Automatic],
            // Kia Sorento
            ['kia', 'sorento', '2-4-2015-2020', '2.4', '٢٫٤', 2015, 2020, 'G4KE', 2400, FuelType::Petrol, Transmission::Automatic],
            // Chevrolet Optra
            ['chevrolet', 'optra', '1-6-2003-2007', '1.6', '١٫٦', 2003, 2007, 'F16D3', 1600, FuelType::Petrol, Transmission::Manual],
            ['chevrolet', 'optra', '1-8-2004-2008', '1.8', '١٫٨', 2004, 2008, 'T18SED', 1800, FuelType::Petrol, Transmission::Automatic],
            // Chevrolet Aveo
            ['chevrolet', 'aveo', '1-5-2008-2012', '1.5', '١٫٥', 2008, 2012, 'F15S3', 1500, FuelType::Petrol, Transmission::Automatic],
            ['chevrolet', 'aveo', '1-6-2012-2016', '1.6', '١٫٦', 2012, 2016, 'F16D4', 1600, FuelType::Petrol, Transmission::Automatic],
            // Chevrolet Lanos
            ['chevrolet', 'lanos', '1-5-1997-2008', '1.5', '١٫٥', 1997, 2008, 'A15SMS', 1500, FuelType::Petrol, Transmission::Manual],
            // Chevrolet Cruze
            ['chevrolet', 'cruze', '1-6-2009-2015', '1.6', '١٫٦', 2009, 2015, 'F16D4', 1600, FuelType::Petrol, Transmission::Automatic],
            ['chevrolet', 'cruze', '1-8-2009-2015', '1.8', '١٫٨', 2009, 2015, 'F18D4', 1800, FuelType::Petrol, Transmission::Automatic],
            // Renault Logan
            ['renault', 'logan', '1-4-2006-2012', '1.4', '١٫٤', 2006, 2012, 'K7J', 1400, FuelType::Petrol, Transmission::Manual],
            ['renault', 'logan', '1-6-2013-2019', '1.6', '١٫٦', 2013, 2019, 'K7M', 1600, FuelType::Petrol, Transmission::Manual],
            // Renault Duster
            ['renault', 'duster', '1-6-2015-2020', '1.6', '١٫٦', 2015, 2020, 'K4M', 1600, FuelType::Petrol, Transmission::Automatic],
            ['renault', 'duster', '2-0-2015-2020', '2.0', '٢٫٠', 2015, 2020, 'F4R', 2000, FuelType::Petrol, Transmission::Manual],
            // Renault Megane
            ['renault', 'megane', '1-6-2003-2009', '1.6', '١٫٦', 2003, 2009, 'K4M', 1600, FuelType::Petrol, Transmission::Automatic],
            // Renault Fluence
            ['renault', 'fluence', '1-6-2010-2016', '1.6', '١٫٦', 2010, 2016, 'K4M', 1600, FuelType::Petrol, Transmission::Automatic],
            // Renault Clio
            ['renault', 'clio', '1-2-2013-2019', '1.2', '١٫٢', 2013, 2019, 'D4F', 1200, FuelType::Petrol, Transmission::Manual],
            // Skoda Octavia
            ['skoda', 'octavia', '1-6-2013-2019', '1.6', '١٫٦', 2013, 2019, 'CWDA', 1600, FuelType::Petrol, Transmission::Automatic],
            ['skoda', 'octavia', '2-0-2014-2020', '2.0', '٢٫٠', 2014, 2020, 'CHHB', 2000, FuelType::Petrol, Transmission::Automatic],
            // Skoda Fabia
            ['skoda', 'fabia', '1-2-2015-2021', '1.2', '١٫٢', 2015, 2021, 'CJZC', 1200, FuelType::Petrol, Transmission::Automatic],
            // Skoda Rapid
            ['skoda', 'rapid', '1-6-2013-2019', '1.6', '١٫٦', 2013, 2019, 'CWDA', 1600, FuelType::Petrol, Transmission::Automatic],
            // Skoda Superb
            ['skoda', 'superb', '2-0-turbo-2016-2023', '2.0 Turbo', '٢٫٠ تيربو', 2016, 2023, 'CHHB', 2000, FuelType::Petrol, Transmission::Automatic],
            // Mitsubishi Lancer
            ['mitsubishi', 'lancer', '1-6-2008-2017', '1.6', '١٫٦', 2008, 2017, '4A92', 1600, FuelType::Petrol, Transmission::Automatic],
            ['mitsubishi', 'lancer', '2-0-2008-2017', '2.0', '٢٫٠', 2008, 2017, '4B11', 2000, FuelType::Petrol, Transmission::Cvt],
            // Mitsubishi Attrage
            ['mitsubishi', 'attrage', '1-2-2014-2022', '1.2', '١٫٢', 2014, 2022, '3A92', 1200, FuelType::Petrol, Transmission::Cvt],
            // Mitsubishi Pajero
            ['mitsubishi', 'pajero', '3-0-2007-2020', '3.0', '٣٫٠', 2007, 2020, '6G72', 3000, FuelType::Petrol, Transmission::Automatic],
            // Peugeot 206
            ['peugeot', '206', '1-4-1999-2009', '1.4', '١٫٤', 1999, 2009, 'TU3JP', 1400, FuelType::Petrol, Transmission::Manual],
            ['peugeot', '206', '1-6-1999-2009', '1.6', '١٫٦', 1999, 2009, 'TU5JP4', 1600, FuelType::Petrol, Transmission::Automatic],
            // Peugeot 301
            ['peugeot', '301', '1-6-2013-2020', '1.6', '١٫٦', 2013, 2020, 'EC5', 1600, FuelType::Petrol, Transmission::Automatic],
            // Peugeot 508
            ['peugeot', '508', '1-6-turbo-2011-2017', '1.6 Turbo', '١٫٦ تيربو', 2011, 2017, 'EP6CDT', 1600, FuelType::Petrol, Transmission::Automatic],
            // Peugeot 2008
            ['peugeot', '2008', '1-6-2014-2019', '1.6', '١٫٦', 2014, 2019, 'EC5', 1600, FuelType::Petrol, Transmission::Automatic],
            // Fiat Tipo
            ['fiat', 'tipo', '1-6-2016-2022', '1.6', '١٫٦', 2016, 2022, '55260384', 1600, FuelType::Petrol, Transmission::Automatic],
            // Fiat Punto
            ['fiat', 'punto', '1-2-2011-2018', '1.2', '١٫٢', 2011, 2018, '199A4', 1200, FuelType::Petrol, Transmission::Manual],
            ['fiat', 'punto', '1-4-2011-2018', '1.4', '١٫٤', 2011, 2018, '199A7', 1400, FuelType::Petrol, Transmission::Manual],
            // Fiat Uno
            ['fiat', 'uno', '1-0-2014-2020', '1.0', '١٫٠', 2014, 2020, 'Fire', 1000, FuelType::Petrol, Transmission::Manual],
        ];

        foreach ($variants as [$makeSlug, $modelSlug, $slug, $name, $nameAr, $from, $to, $engine, $cc, $fuel, $trans]) {
            $make = VehicleMake::where('slug', $makeSlug)->first();
            $model = $make
                ? VehicleModel::where('vehicle_make_id', $make->id)->where('slug', $modelSlug)->first()
                : null;

            if (! $model) {
                continue;
            }

            VehicleVariant::updateOrCreate(
                ['vehicle_model_id' => $model->id, 'slug' => $slug],
                [
                    'name' => $name,
                    'name_ar' => $nameAr,
                    'year_from' => $from,
                    'year_to' => $to,
                    'engine_code' => $engine,
                    'engine_capacity_cc' => $cc,
                    'fuel_type' => $fuel,
                    'transmission' => $trans,
                    'is_active' => true,
                ],
            );
        }
    }
}