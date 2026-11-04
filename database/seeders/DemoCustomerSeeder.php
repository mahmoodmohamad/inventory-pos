<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * عملاء تجريبيين للمحل demo-shop. آمن للتشغيل أكتر من مرة (المفتاح: رقم التليفون).
 * لازم DemoShopSeeder يتنفذ قبله.
 */
class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'demo-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {
            foreach ($this->customers() as $row) {
                Customer::query()->updateOrCreate(
                    ['phone' => $row['phone']],
                    $row,
                );
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customers(): array
    {
        return [
            [
                'name' => 'عم سيد - ورشة الصلاح',
                'phone' => '01011111111',
                'alternate_phone' => '01211111111',
                'address' => 'شبرا، القاهرة',
                'notes' => 'عميل جملة، بيتعامل بالأجل',
                'is_active' => true,
            ],
            [
                'name' => 'مصطفى حسن',
                'phone' => '01022222222',
                'email' => 'mostafa@example.test',
                'address' => 'مدينة نصر، القاهرة',
                'is_active' => true,
            ],
            [
                'name' => 'ورشة النور للسيارات',
                'phone' => '01033333333',
                'address' => 'العبور',
                'notes' => 'جملة',
                'is_active' => true,
            ],
            [
                'name' => 'عميل قديم (موقوف)',
                'phone' => '01044444444',
                'is_active' => false,
            ],
        ];
    }
}