<?php

// database/factories/ProductFactory.php

namespace Database\Factories;

use App\Enums\ProductCondition;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->words(3, true),
            'part_number' => strtoupper(fake()->bothify('??-#####')),
            'oem_number' => null,
            'barcode' => fake()->unique()->numerify('##########'),
            'condition' => ProductCondition::Original,
            'unit' => ProductUnit::Piece,
            'retail_price' => 100,
            'wholesale_price' => 85,
            'min_stock_alert' => 0,
            'is_active' => true,
        ];
    }

    public function condition(ProductCondition $condition): static
    {
        return $this->state(fn () => ['condition' => $condition]);
    }

    public function withStock(float $qty, float $avgCost = 50): static
    {
        return $this->state(fn () => ['stock_qty' => $qty, 'avg_cost' => $avgCost, 'last_cost' => $avgCost]);
    }
}
