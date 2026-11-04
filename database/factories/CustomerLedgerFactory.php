<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerSource;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerLedger> */
class CustomerLedgerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'customer_id' => fn (array $a) => Customer::factory()->create(['shop_id' => $a['shop_id']])->id,
            'sale_id' => null,
            'payment_id' => null,
            'created_by' => fn (array $a) => User::factory()->create(['shop_id' => $a['shop_id']])->id,
            'source' => LedgerSource::Adjustment,
            'direction' => LedgerDirection::Debit,
            'amount' => '10.00',
            'occurred_at' => now(),
        ];
    }
}
