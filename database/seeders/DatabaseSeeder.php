<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Execution order matters:
     *  1) Vehicle catalog (makes → models → variants) — shared reference data
     *  2) Shops + owner user + sequences — tenancy roots
     *  3) Per-shop reference data: brands, categories
     *  4) Per-shop catalog: products, fitments, opening stock, customers, draft sale
     *  5) Demo-only extras (local/testing): demo shop, demo products,
     *     demo suppliers/purchases, demo sale, completed sale
     *
     * ⚠️ Important ordering constraints:
     *  - DemoShopSeeder MUST run AFTER SaleSeeder. If it ran before, SaleSeeder
     *    would iterate over demo-shop and create a placeholder draft, which would
     *    make DemoSaleSeeder a no-op (Sale::query()->exists() returns true).
     *  - DemoCompletedSaleSeeder MUST run AFTER DemoSaleSeeder (it completes one
     *    of the drafts) and AFTER DemoStockSeeder (needs stock to decrement).
     */
    public function run(): void
    {
        // 1) Shared vehicle catalog
        $this->call(VehicleCatalogSeeder::class);
        $this->call(VehicleVariantSeeder::class);

        // 2) Tenancy roots
        $this->call(ShopSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(ShopSequenceSeeder::class);

        // 3) Per-shop reference data
        $this->call(BrandSeeder::class);
        $this->call(CategorySeeder::class);

        // 4) Per-shop catalog + opening balances + first draft sale
        $this->call(ProductSeeder::class);
        $this->call(ProductFitmentSeeder::class);
        $this->call(StockMovementSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(SaleSeeder::class);

        // 5) Demo-only data (local/testing)
        if (app()->environment('local', 'testing')) {
            $this->call(DemoShopSeeder::class);
            $this->call(DemoProductSeeder::class);
            $this->call(DemoStockSeeder::class);
            $this->call(DemoCustomerSeeder::class);
            $this->call(DemoSupplierSeeder::class);
            $this->call(DemoSaleSeeder::class);
            $this->call(DemoPurchaseSeeder::class);

            // ⚠️ Only call this once you've wired SaleService::complete().
            // It exercises the real completion path and produces a coherent
            // payments + customer_ledgers + stock_movements set.
            // $this->call(DemoCompletedSaleSeeder::class);
        }

        // Laravel Breeze test user (idempotent)
        if (! User::withoutGlobalScopes()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
