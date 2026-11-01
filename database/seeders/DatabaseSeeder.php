<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * ترتيب التنفيذ مهم:
     *  1) كتالوج المركبات (makes → models)
     *  2) الفئات (variants) — تعتمد على الكتالوج
     *  3) المحل التجريبي + المستخدمين + الفئات — local/testing فقط
     */
    public function run(): void
    {
        // 1) كتالوج المركبات (بيانات مرجعية — تزرع دائمًا)
        $this->call(VehicleCatalogSeeder::class);

        // 2) فئات السيارات (variants) — تحتاج الكتالوج موجود
        $this->call(VehicleVariantSeeder::class);

        // 3) بيانات تجريبية — لا تزرع في production
        if (app()->environment('local', 'testing')) {
            $this->call(DemoShopSeeder::class);
        }

        // 4) مستخدم اختبار عام — مفيد للتطوير فقط
        if (app()->environment('local', 'testing')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}