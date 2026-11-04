<?php

namespace Database\Factories;

use App\Enums\ProductCondition;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleItem> */
class SaleItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'sale_id' => fn (array $a) => Sale::factory()->create(['shop_id' => $a['shop_id']])->id,
            'product_id' => fn (array $a) => Product::factory()->create(['shop_id' => $a['shop_id']])->id,
            'product_name' => fake()->words(3, true),
            'condition' => ProductCondition::Original,
            'unit' => ProductUnit::Piece,
            'quantity' => '1.000',
            'original_unit_price' => '100.00',
            'unit_price' => '100.00',
            'discount_amount' => '0.00',
            'line_total' => '100.00',
            'unit_cost' => '60.0000',
        ];
    }
}
