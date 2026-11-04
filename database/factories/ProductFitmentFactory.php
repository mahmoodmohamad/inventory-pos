<?php

// database/factories/ProductFitmentFactory.php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFitment>
 */
class ProductFitmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            // نفس محل المنتج دايمًا، وإلا الـ composite FK هيرفض
            'shop_id' => fn (array $attrs) => Product::withoutGlobalScopes()
                ->whereKey($attrs['product_id'])
                ->value('shop_id'),
            'vehicle_variant_id' => VehicleVariant::factory(),
            'notes' => null,
        ];
    }
}
