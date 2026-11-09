<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * فواتير توريد ومدفوعات موردين تجريبية للمحل demo-shop.
 *
 * آمن للتشغيل أكثر من مرة.
 * المخزون لا يتأثر هنا.
 */
class DemoPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()
            ->where('slug', 'demo-shop')
            ->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {

            // If purchases already exist, synchronize the sequence before exiting.
            if (Purchase::query()->exists()) {
                $maxInvoice = (int) Purchase::query()->max('invoice_number');

                if ($maxInvoice > 0) {
                    $this->syncPurchaseInvoiceSequence($maxInvoice);
                }

                return;
            }

            $receiver = User::query()
                ->where('email', 'manager@demo.test')
                ->first();

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

            // 1) شركة النصر — فاتورة كاملة + سداد كامل
            $p1 = $this->createPurchase($receiver, $nasr, $products, [
                ['barcode' => '6220000000011', 'qty' => '6', 'unit_cost' => '720'],
                ['barcode' => '6220000000012', 'qty' => '4', 'unit_cost' => '350'],
                ['barcode' => '6220000000021', 'qty' => '20', 'unit_cost' => '150'],
            ], [
                'invoice_number' => 1001,
                'supplier_invoice_ref' => 'NASR-2024-001',
                'received_at' => now()->subDays(20),
                'notes' => 'توريد أول دفعة فرامل وفلاتر',
            ]);

            $this->createPayment($receiver, $nasr, $p1, [
                'amount' => Money::norm($p1->total_amount),
                'method' => PaymentMethod::BankTransfer,
                'paid_at' => now()->subDays(18),
                'notes' => 'سداد كامل فاتورة 1001',
            ]);

            // 2) مؤسسة الأمانة — فاتورة + دفعة جزئية
            $p2 = $this->createPurchase($receiver, $amana, $products, [
                ['barcode' => '6220000000031', 'qty' => '30', 'unit_cost' => '78'],
                ['barcode' => '6220000000041', 'qty' => '1', 'unit_cost' => '1250'],
            ], [
                'invoice_number' => 1002,
                'supplier_invoice_ref' => 'AMANA-77',
                'received_at' => now()->subDays(10),
                'notes' => 'شمعة احتراق ومساعدين',
            ]);

            $this->createPayment($receiver, $amana, $p2, [
                'amount' => '1500.00',
                'method' => PaymentMethod::Cash,
                'paid_at' => now()->subDays(8),
                'notes' => 'دفعة مقدمة',
            ]);

            // 3) التاجر الأصلي — فاتورة بدون دفعة
            $this->createPurchase($receiver, $original, $products, [
                ['barcode' => '6220000000011', 'qty' => '2', 'unit_cost' => '720'],
            ], [
                'invoice_number' => 1003,
                'supplier_invoice_ref' => 'ORIG-2024-A12',
                'received_at' => now()->subDays(2),
                'notes' => 'دفعة فحمات Denso',
            ]);

            // Synchronize the purchase invoice sequence.
            $this->syncPurchaseInvoiceSequence(1003);
        });
    }

    /**
     * Synchronize the purchase invoice sequence for the current tenant.
     */
    private function syncPurchaseInvoiceSequence(int $lastNumber): void
    {
        DB::table('shop_sequences')
            ->where('shop_id', app(TenantContext::class)->idOrFail())
            ->where('sequence_key', 'purchase_invoice')
            ->where('last_number', '<', $lastNumber)
            ->update([
                'last_number' => $lastNumber,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<string, Product> $products
     * @param list<array{barcode: string, qty: string, unit_cost: string}> $lines
     * @param array{invoice_number: int, supplier_invoice_ref?: string|null, received_at: \DateTimeInterface, notes?: string|null} $attrs
     */
    private function createPurchase(
        User $receiver,
        Supplier $supplier,
        array $products,
        array $lines,
        array $attrs,
    ): Purchase {
        $total = '0.00';
        $clean = [];

        foreach ($lines as $line) {
            $product = $products[$line['barcode']] ?? null;

            if ($product === null) {
                continue;
            }

            $qty = Money::norm($line['qty']);
            $unitCost = Money::norm($line['unit_cost']);

            $lineTotal = Money::round(
                Money::mul($qty, $unitCost, 7),
                2
            );

            $total = Money::add($total, $lineTotal, 2);

            $clean[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
            ];
        }

        $purchase = new Purchase([
            'supplier_id' => $supplier->id,
            'supplier_invoice_ref' => $attrs['supplier_invoice_ref'] ?? null,
            'notes' => $attrs['notes'] ?? null,
        ]);

        $purchase->forceFill([
            'received_by' => $receiver->id,
            'invoice_number' => $attrs['invoice_number'],
            'total_amount' => $total,
            'received_at' => $attrs['received_at'],
        ])->save();

        foreach ($clean as $line) {
            $purchase->items()->create([
                'product_id' => $line['product']->id,
                'product_name' => $line['product']->name,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => $line['line_total'],
            ]);
        }

        return $purchase->refresh();
    }

    /**
     * @param array{amount: string, method: PaymentMethod, paid_at: \DateTimeInterface, notes?: string|null} $attrs
     */
    private function createPayment(
        User $paidBy,
        Supplier $supplier,
        Purchase $purchase,
        array $attrs,
    ): SupplierPayment {
        return SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'paid_by' => $paidBy->id,
            'amount' => $attrs['amount'],
            'method' => $attrs['method']->value,
            'paid_at' => $attrs['paid_at'],
            'notes' => $attrs['notes'] ?? null,
        ]);
    }
}
