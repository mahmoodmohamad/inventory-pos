<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * موردين تجريبيين للمحل demo-shop. آمن للتشغيل أكتر من مرة.
 * المفتاح الطبيعي: (shop_id, phone) — phone مش unique على مستوى الجدول،
 * لكن داخل نفس المحل بيتكررش.
 *
 * لازم DemoShopSeeder يتنفذ قبله.
 */
class DemoSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'demo-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function () use ($shop): void {
            foreach ($this->suppliers() as $row) {
    Supplier::query()->updateOrCreate(
        ['phone' => $row['phone']],
        $row,
    );
}
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suppliers(): array
    {
        return [
            [
                'name' => 'شركة النصر لقطع الغيار',
                'phone' => '01055555555',
                'is_active' => true,
            ],
            [
                'name' => 'مؤسسة الأمانة للتوريدات',
                'phone' => '01066666666',
                'is_active' => true,
            ],
            [
                'name' => 'التاجر للأصلي (Bosch / Denso)',
                'phone' => '01077777777',
                'is_active' => true,
            ],
            [
                'name' => 'مورد قديم (موقوف)',
                'phone' => '01088888888',
                'is_active' => false,
            ],
        ];
    }
}
