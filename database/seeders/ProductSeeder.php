<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductCondition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Generic catalog products for default-shop only. Barcode is the idempotency key.
 * Stock stays 0; opening balances come via StockMovementSeeder / StockService.
 * Needs BrandSeeder and CategorySeeder first.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'default-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {
            $brands = Brand::query()->pluck('id', 'name');
            $categories = Category::query()->whereNull('parent_id')->pluck('id', 'name');

            foreach ($this->products() as $row) {
                Product::query()->updateOrCreate(
                    ['barcode' => $row['barcode']],
                    [
                        'name' => $row['name'],
                        'part_number' => $row['part_number'],
                        'oem_number' => $row['oem_number'] ?? null,
                        'condition' => $row['condition'],
                        'unit' => 'piece',
                        'category_id' => $categories[$row['category']] ?? null,
                        'brand_id' => $brands[$row['brand']] ?? null,
                        'retail_price' => $row['retail'],
                        'wholesale_price' => $row['wholesale'],
                        'min_stock_alert' => $row['min'],
                        'is_active' => true,
                    ],
                );
            }
        });
    }

    /** @return list<array<string, mixed>> */
    private function products(): array
    {
        return [
            ['barcode' => '1000000000011', 'name' => 'Brake Pad Set Front', 'part_number' => 'BP-0011',
                'condition' => ProductCondition::Original, 'category' => 'Brake System', 'brand' => 'Denso',
                'retail' => 850, 'wholesale' => 720, 'min' => 4],
            ['barcode' => '1000000000021', 'name' => 'Oil Filter', 'part_number' => 'OF-0021',
                'condition' => ProductCondition::Original, 'category' => 'Filters', 'brand' => 'Mann Filter',
                'retail' => 180, 'wholesale' => 150, 'min' => 10],
            ['barcode' => '1000000000031', 'name' => 'Spark Plug', 'part_number' => 'SP-0031',
                'condition' => ProductCondition::Original, 'category' => 'Electrical', 'brand' => 'NGK',
                'retail' => 95, 'wholesale' => 78, 'min' => 16],
            ['barcode' => '1000000000041', 'name' => 'Shock Absorber Front', 'part_number' => 'SA-0041',
                'condition' => ProductCondition::Imported, 'category' => 'Suspension', 'brand' => 'Sachs',
                'retail' => 1450, 'wholesale' => 1250, 'min' => 2],
            ['barcode' => '1000000000051', 'name' => 'Air Filter', 'part_number' => 'AF-0051',
                'condition' => ProductCondition::Commercial, 'category' => 'Filters', 'brand' => 'Bosch',
                'retail' => 220, 'wholesale' => 185, 'min' => 8],
        ];
    }
}