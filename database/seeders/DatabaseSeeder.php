<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * ترتيب التنفيذ مهم:
     *  1) كتالوج المركبات (makes → models)
     *  2) الفئات (variants) — تعتمد على الكتالوج
     *  3) بيانات تجريبية — local/testing فقط:
     *     المحل + المستخدمين + الفئات، المنتجات، الرصيد الافتتاحي، العملاء، مسودات الفواتير
     */
    public function run(): void
    {
        // 1) كتالوج المركبات (بيانات مرجعية — تزرع دائمًا)
        $this->call(VehicleCatalogSeeder::class);

        // 2) variants — تحتاج الكتالوج موجود
        $this->call(VehicleVariantSeeder::class);

        // 3) بيانات تجريبية — لا تزرع في production
        if (app()->environment('local', 'testing')) {
            $this->call(DemoShopSeeder::class);
            $this->call(DemoProductSeeder::class);
            $this->call(DemoStockSeeder::class);
            $this->call(DemoCustomerSeeder::class);
            $this->call(DemoSaleSeeder::class);

            // idempotent: مايتكررش لو الـ seeder اتشغل قبل كده
            if (! User::withoutGlobalScopes()->where('email', 'test@example.com')->exists()) {
                User::factory()->create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);
            }
        }
    }
}