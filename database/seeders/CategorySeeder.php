<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Engine Parts' => [
                'Pistons',
                'Piston Rings',
                'Engine Valves',
                'Gaskets',
            ],
            'Brake System' => [
                'Brake Pads',
                'Brake Discs',
                'Brake Calipers',
            ],
            'Suspension' => [
                'Shock Absorbers',
                'Control Arms',
                'Ball Joints',
            ],
            'Electrical' => [
                'Alternators',
                'Starters',
                'Batteries',
                'Spark Plugs',
            ],
            'Filters' => [
                'Oil Filters',
                'Air Filters',
                'Fuel Filters',
            ],
            'Cooling System' => [
                'Radiators',
                'Water Pumps',
                'Thermostats',
            ],
        ];

        $tenant = app(TenantContext::class);

        Shop::query()->each(function (Shop $shop) use ($tenant, $categories): void {
            $tenant->run($shop, function () use ($categories): void {
                foreach ($categories as $parentName => $children) {
                    $parent = Category::query()->firstOrCreate([
                        'name' => $parentName,
                        'parent_id' => null,
                    ]);

                    foreach ($children as $childName) {
                        Category::query()->firstOrCreate([
                            'name' => $childName,
                            'parent_id' => $parent->id,
                        ]);
                    }
                }
            });
        });
    }
}
