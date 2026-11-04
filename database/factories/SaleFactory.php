<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            // الترتيب مهم: shop_id الأول عشان الـ closures اللي بعده تستخدمه.
            'shop_id' => Shop::factory(),
            'customer_id' => null,
            'created_by' => fn (array $a) => User::factory()->create(['shop_id' => $a['shop_id']])->id,
            'invoice_number' => fn (array $a) => ((int) Sale::withoutGlobalScopes()
                ->where('shop_id', $a['shop_id'])->max('invoice_number')) + 1,
            'status' => SaleStatus::Draft,
            'price_type' => PriceType::Retail,
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'total_amount' => '100.00',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SaleStatus::Completed,
            'completed_at' => now(),
            'completed_by' => fn (array $a) => User::factory()->create(['shop_id' => $a['shop_id']])->id,
        ]);
    }
}
