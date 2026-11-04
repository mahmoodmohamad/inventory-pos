<?php

// database/seeders/DemoProductSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductCondition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\VehicleVariant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * منتجات تجريبية للمحل demo-shop. آمن للتشغيل أكتر من مرة.
 * لازم VehicleCatalogSeeder و VehicleVariantSeeder و DemoShopSeeder يتنفذوا قبله.
 * المخزون صفر عمدًا: الرصيد الافتتاحي هييجي من stock_movements في 3.2.
 */
class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'demo-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {
            $brands = [];
            foreach (['Bosch', 'Denso', 'Valeo', 'Mann Filter', 'Sachs', 'TRW'] as $name) {
                $brands[$name] = Brand::query()->firstOrCreate(['name' => $name]);
            }

            $categories = Category::query()->whereNull('parent_id')->pluck('id', 'name');

            foreach ($this->products() as $row) {
                $product = Product::query()->updateOrCreate(
                    ['barcode' => $row['barcode']],
                    [
                        'name' => $row['name'],
                        'part_number' => $row['part_number'],
                        'oem_number' => $row['oem_number'] ?? null,
                        'condition' => $row['condition'],
                        'category_id' => $categories[$row['category']] ?? null,
                        'brand_id' => $brands[$row['brand']]->id,
                        'retail_price' => $row['retail'],
                        'wholesale_price' => $row['wholesale'],
                        'min_stock_alert' => $row['min'],
                        'is_active' => true,
                    ],
                );

                foreach ($row['fits'] as [$make, $model, $variantSlug]) {
                    $variant = VehicleVariant::query()
                        ->where('slug', $variantSlug)
                        ->whereHas('vehicleModel', fn ($q) => $q
                            ->where('slug', $model)
                            ->whereHas('make', fn ($m) => $m->where('slug', $make)))
                        ->first();

                    if ($variant !== null) {
                        $product->fitments()->firstOrCreate(['vehicle_variant_id' => $variant->id]);
                    }
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        $corolla14 = ['toyota', 'corolla', '1-6-2014-2018'];
        $corolla19 = ['toyota', 'corolla', '1-6-2019-2022'];
        $elantra = ['hyundai', 'elantra', '1-6-2016-2020'];

        return [
            [
                'barcode' => '6220000000011', 'name' => 'طقم فحمات فرامل أمامي',
                'part_number' => '04465-02220', 'oem_number' => '04465-02220',
                'condition' => ProductCondition::Original, 'category' => 'فرامل', 'brand' => 'Denso',
                'retail' => 850, 'wholesale' => 720, 'min' => 4, 'fits' => [$corolla14, $corolla19],
            ],
            [
                'barcode' => '6220000000012', 'name' => 'طقم فحمات فرامل أمامي',
                'part_number' => '04465-02220', 'oem_number' => '04465-02220',
                'condition' => ProductCondition::Commercial, 'category' => 'فرامل', 'brand' => 'TRW',
                'retail' => 420, 'wholesale' => 350, 'min' => 6, 'fits' => [$corolla14, $corolla19],
            ],
            [
                'barcode' => '6220000000021', 'name' => 'فلتر زيت',
                'part_number' => 'W67/1', 'condition' => ProductCondition::Original,
                'category' => 'فلاتر', 'brand' => 'Mann Filter',
                'retail' => 180, 'wholesale' => 150, 'min' => 10, 'fits' => [$corolla14, $elantra],
            ],
            [
                'barcode' => '6220000000031', 'name' => 'شمعة احتراق',
                'part_number' => 'FR7DC+', 'condition' => ProductCondition::Original,
                'category' => 'كهرباء', 'brand' => 'Bosch',
                'retail' => 95, 'wholesale' => 78, 'min' => 16, 'fits' => [$corolla19, $elantra],
            ],
            [
                'barcode' => '6220000000041', 'name' => 'مساعد أمامي',
                'part_number' => '314-875', 'condition' => ProductCondition::Imported,
                'category' => 'تعليق', 'brand' => 'Sachs',
                'retail' => 1450, 'wholesale' => 1250, 'min' => 2, 'fits' => [$elantra],
            ],
        ];
    }
}
