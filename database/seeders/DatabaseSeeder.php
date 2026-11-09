<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Shared catalog (no shop_id): makes/models first, then variants
        $this->call([
            VehicleCatalogSeeder::class,
            VehicleVariantSeeder::class,
        ]);

        // 2) default-shop: shop -> users/sequences -> catalog data -> products
        //    -> fitments/stock -> customers -> sales
        $this->call([
            ShopSeeder::class,
            UserSeeder::class,
            ShopSequenceSeeder::class,
            BrandSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            ProductFitmentSeeder::class,   // needs products + variants
            StockMovementSeeder::class,    // needs products
            CustomerSeeder::class,
            SaleSeeder::class,             // needs users, customers, products, sequences
        ]);

        // 3) demo-shop (local / testing only)
        if (app()->environment('local', 'testing')) {
            $this->call([
                DemoShopSeeder::class,       // shop, users, categories, sequences
                DemoProductSeeder::class,    // needs categories + variants
                DemoStockSeeder::class,      // needs products
                DemoCustomerSeeder::class,
                DemoSupplierSeeder::class,
                DemoPurchaseSeeder::class,   // needs suppliers, products, manager user
                DemoSaleSeeder::class,       // needs customers, products, cashier user
                // DemoCompletedSaleSeeder::class,
            ]);
        }

        // 4) Test user (factory creates its own shop)
        if (! User::withoutGlobalScopes()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}