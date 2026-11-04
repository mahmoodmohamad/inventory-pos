<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'sale_id' => fn (array $a) => Sale::factory()->create(['shop_id' => $a['shop_id']])->id,
            'customer_id' => null,
            'received_by' => fn (array $a) => User::factory()->create(['shop_id' => $a['shop_id']])->id,
            'amount' => '40.00',
            'method' => PaymentMethod::Cash,
            'paid_at' => now(),
        ];
    }
}
