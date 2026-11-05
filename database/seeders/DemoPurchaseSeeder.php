<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * فواتير توريد ومدفوعات موردين تجريبية للمحل demo-shop.
 * آمن للتشغيل أكتر من مرة.
 *
 * المفاتيح الطبيعية (عشان الـ idempotency):
 *  - purchases:          shop_id + invoice_number
 *  - purchase_items:     shop_id + purchase_id + product_id
 *  - supplier_payments:  shop_id + purchase_id + supplier_id   (دفعة واحدة لكل فاتورة)
 *
 * لازم DemoShopSeeder و DemoSupplierSeeder و DemoProductSeeder يتنفذوا قبله.
 *
 * ملاحظات:
 *  - total_amount محسوب من بنود الفاتورة (مش literal) عشان يفضل متسق.
 *  - المخزون مش بيتأثر هنا: حركات المخزون مسؤولية StockService.
 *  - لو فيه purchases موجودة للمحل، السيدر بيخرج بدون ما يعدل حاجة
 *    (زي DemoSaleSeeder) عشان ما يعملش overwrite على بيانات حقيقية.
 */
class DemoPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'demo-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function () use ($shop): void {
            if (Purchase::query()->exists()) {
                return;
            }

            $receiver = User::query()->where('email', 'manager@demo.test')->first();

            if ($receiver === null) {
                return;
            }

            /** @var array<string, Supplier> $suppliers */
            $suppliers = Supplier::query()->get()->keyBy('phone')->all();

            /** @var array<string, Product> $products */
            $products = Product::query()->get()->keyBy('barcode')->all();

            $nasr = $suppliers['01055555555'] ?? null;
            $amana = $suppliers['01066666666'] ?? null;
            $original = $suppliers['01077777777'] ?? null;

            if ($nasr === null || $amana === null || $original === null) {
                return;
            }

            // 1) فاتورة من "شركة النصر" — مستلمة بالكامل
            $p1Items = [
                ['barcode' => '6220000000011', 'qty' => 6.000,  'unit_cost' => 720.0000],
                ['barcode' => '6220000000012', 'qty' => 4.000,  'unit_cost' => 350.0000],
                ['barcode' => '6220000000021', 'qty' => 20.000, 'unit_cost' => 150.0000],
            ];
            $p1 = $this->upsertPurchase($shop, $receiver, $nasr, $products, $p1Items, [
                'invoice_number' => 1001,
                'supplier_invoice_ref' => 'NASR-2024-001',
                'received_at' => now()->subDays(20),
                'notes' => 'توريد أول دفعة فرامل وفلاتر',
            ]);

            // 2) فاتورة من "مؤسسة الأمانة" — مستلمة، وفيها مدفوعة جزئية
            $p2Items = [
                ['barcode' => '6220000000031', 'qty' => 30.000, 'unit_cost' => 78.0000],
                ['barcode' => '6220000000041', 'qty' => 1.000,  'unit_cost' => 1250.0000],
            ];
            $p2 = $this->upsertPurchase($shop, $receiver, $amana, $products, $p2Items, [
                'invoice_number' => 1002,
                'supplier_invoice_ref' => 'AMANA-77',
                'received_at' => now()->subDays(10),
                'notes' => 'شمعة احتراق ومساعدين',
            ]);

            // 3) فاتورة من "التاجر للأصلي"
            $p3Items = [
                ['barcode' => '6220000000011', 'qty' => 2.000, 'unit_cost' => 720.0000],
            ];
            $this->upsertPurchase($shop, $receiver, $original, $products, $p3Items, [
                'invoice_number' => 1003,
                'supplier_invoice_ref' => 'ORIG-2024-A12',
                'received_at' => now()->subDays(2),
                'notes' => 'دفعة فحمات Denso',
            ]);

            // المدفوعات — مفتاح طبيعي: (purchase_id, supplier_id). دفعة واحدة لكل فاتورة.
            // paid_at/amount في الـ update payload مش في المفتاح عشان الـ idempotency.
            $this->upsertPayment($shop, $receiver, $nasr, $p1, [
                'amount' => (float) $p1->total_amount,
                'method' => 'bank_transfer',
                'paid_at' => now()->subDays(18),
                'notes' => 'سداد كامل فاتورة 1001',
            ]);

            $this->upsertPayment($shop, $receiver, $amana, $p2, [
                'amount' => 1500.00,
                'method' => 'cash',
                'paid_at' => now()->subDays(8),
                'notes' => 'دفعة مقدمة',
            ]);
        });
    }

    /**
     * @param  array<string, Product>  $products
     * @param  list<array{barcode: string, qty: float, unit_cost: float}>  $items
     * @param  array<string, mixed>  $attrs
     */
    private function upsertPurchase(
        Shop $shop,
        User $receiver,
        Supplier $supplier,
        array $products,
        array $items,
        array $attrs,
    ): Purchase {
        $total = 0.0;
        foreach ($items as $item) {
            $total += round($item['qty'] * $item['unit_cost'], 2);
        }

        $purchase = Purchase::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'invoice_number' => $attrs['invoice_number'],
            ],
            [
                'supplier_id' => $supplier->id,
                'received_by' => $receiver->id,
                'supplier_invoice_ref' => $attrs['supplier_invoice_ref'] ?? null,
                'total_amount' => $total,
                'received_at' => $attrs['received_at'],
                'notes' => $attrs['notes'] ?? null,
            ],
        );

        foreach ($items as $item) {
            $product = $products[$item['barcode']] ?? null;

            if ($product === null) {
                continue;
            }

            PurchaseItem::query()->updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                ],
                [
                    'product_name' => $product->name,
                    'quantity' => $item['qty'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => round($item['qty'] * $item['unit_cost'], 2),
                ],
            );
        }

        return $purchase;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function upsertPayment(
        Shop $shop,
        User $paidBy,
        Supplier $supplier,
        Purchase $purchase,
        array $attrs,
    ): SupplierPayment {
        return SupplierPayment::query()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'supplier_id' => $supplier->id,
                'purchase_id' => $purchase->id,
            ],
            [
                'paid_by' => $paidBy->id,
                'amount' => $attrs['amount'],
                'method' => $attrs['method'],
                'paid_at' => $attrs['paid_at'],
                'notes' => $attrs['notes'] ?? null,
            ],
        );
    }
}
