<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PriceType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sales\SaleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Creates one draft sale per shop via SaleService (real POS path).
 * Skips shops that already have sales. Idempotent.
 *
 * No completed sales / payments / ledger entries here — those must come
 * through the completion service so stock + ledger stay consistent.
 */
class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = app(TenantContext::class);

        Shop::query()->each(function (Shop $shop) use ($tenant): void {
            $tenant->run($shop, function (): void {
                if (Sale::query()->exists()) {
                    return;
                }

                $cashier = User::query()->where('role', 'cashier')->first()
                    ?? User::query()->first();
                if ($cashier === null) {
                    return;
                }

                $customer = Customer::query()->where('is_active', true)->first();
                $product = Product::query()->where('is_active', true)->first();
                if ($product === null) {
                    return;
                }

                $sales = app(SaleService::class);
                $sale = $sales->createDraft(
                    $cashier,
                    $customer?->id,
                    PriceType::Retail,
                );
                $sales->addItem($cashier, $sale, $product->id, '1');
            });
        });
    }
}
