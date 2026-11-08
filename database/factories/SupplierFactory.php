<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->company(),
            'phone' => fake()->numerify('01#########'),
            'is_active' => true,
        ];
    }
}
