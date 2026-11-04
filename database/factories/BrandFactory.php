<?php

// database/factories/BrandFactory.php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->unique()->company(),
        ];
    }
}
