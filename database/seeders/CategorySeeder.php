<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Generic (English) category tree for default-shop only.
 * demo-shop gets its Arabic categories from DemoShopSeeder.
 */
class CategorySeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const TREE = [
        'Engine Parts' => ['Pistons', 'Piston Rings', 'Engine Valves', 'Gaskets'],
        'Brake System' => ['Brake Pads', 'Brake Discs', 'Brake Calipers'],
        'Suspension' => ['Shock Absorbers', 'Control Arms', 'Ball Joints'],
        'Electrical' => ['Alternators', 'Starters', 'Batteries', 'Spark Plugs'],
        'Filters' => ['Oil Filters', 'Air Filters', 'Fuel Filters'],
        'Cooling System' => ['Radiators', 'Water Pumps', 'Thermostats'],
    ];

    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'default-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {
            foreach (self::TREE as $parentName => $children) {
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
    }
}