<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Seeds a few generic customers per shop. Idempotent: keyed by phone.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = app(TenantContext::class);

        Shop::query()->each(function (Shop $shop) use ($tenant): void {
            $tenant->run($shop, function (): void {
                foreach ($this->customers() as $row) {
                    Customer::query()->updateOrCreate(
                        ['phone' => $row['phone']],
                        $row,
                    );
                }
            });
        });
    }

    /** @return list<array<string, mixed>> */
    private function customers(): array
    {
        return [
            ['name' => 'Walk-in Customer', 'phone' => '01000000001',
                'is_active' => true, 'notes' => 'Default walk-in'],
            ['name' => 'Ahmed Workshop', 'phone' => '01000000002',
                'address' => 'Cairo', 'is_active' => true, 'notes' => 'Wholesale'],
            ['name' => 'Mohamed Ali', 'phone' => '01000000003',
                'email' => 'mohamed@example.test', 'is_active' => true],
            ['name' => 'Inactive Customer', 'phone' => '01000000004',
                'is_active' => false],
        ];
    }
}
