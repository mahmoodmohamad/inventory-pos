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
 * One draft sale for default-shop via SaleService (real POS path).
 * Skips if the shop already has sales. Idempotent.
 * demo-shop drafts come from DemoSaleSeeder.
 */
class SaleSeeder extends Seeder
{
    private const ACTOR_ORDER = ['cashier', 'manager', 'owner'];

    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'default-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function () use ($shop): void {
            if (Sale::query()->exists()) {
                return;
            }

            // DB-agnostic (no MySQL FIELD()): prefer cashier, then manager, then owner.
            $actor = User::query()
                ->where('shop_id', $shop->id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (User $u) => in_array($u->role->value, self::ACTOR_ORDER, true))
                ->sortBy(fn (User $u) => array_search($u->role->value, self::ACTOR_ORDER, true))
                ->first();

            if ($actor === null) {
                return;
            }

            $product = Product::query()->where('is_active', true)->first();

            if ($product === null) {
                return;
            }

            $customer = Customer::query()->where('is_active', true)->first();

            $sales = app(SaleService::class);
            $sale = $sales->createDraft($actor, $customer?->id, PriceType::Retail);
            $sales->addItem($actor, $sale, $product->id, '1');
        });
    }
}