<?php

declare(strict_types=1);

namespace App\Services\Purchases;

use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Sales\InvoiceNumberGenerator;
use App\Services\StockService;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * استلام مشتريات من مورّد + دفعات المورّد. كل الحسابات بـ bcmath.
 * الرصيد = إجمالي المشتريات - المدفوعات (مفيش عمود مخزّن).
 */
final class PurchaseService
{
    private const MAX_TOTAL = '9999999999.99';

    public function __construct(
        private InvoiceNumberGenerator $numbers,
        private StockService $stock,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: string, unit_cost: string}>  $lines
     */
    public function receive(
        User $actor,
        int $supplierId,
        array $lines,
        ?PaymentMethod $method = null,
        string $paid = '0',
        ?string $supplierRef = null,
        ?string $notes = null,
    ): Purchase {
        Gate::forUser($actor)->authorize('create', Purchase::class);

        if ($lines === []) {
            throw new DomainException(__('ضيف بند واحد على الأقل.'));
        }

        $paid = $this->decimal($paid, 2, 10);
        $clean = [];
        $total = '0.00';

        foreach ($lines as $line) {
            $qty = $this->decimal((string) $line['quantity'], 3, 9);
            $cost = $this->decimal((string) $line['unit_cost'], 4, 8);

            if (Money::cmp($qty, '0', 3) !== 1) {
                throw new InvalidArgumentException(__('الكمية لازم تكون أكبر من صفر.'));
            }

            $lineTotal = Money::round(Money::mul($qty, $cost, 7), 2);
            $total = Money::add($total, $lineTotal, 2);
            $clean[] = ['product_id' => (int) $line['product_id'], 'quantity' => $qty, 'unit_cost' => $cost, 'line_total' => $lineTotal];
        }

        if (Money::cmp($total, self::MAX_TOTAL, 2) === 1) {
            throw new InvalidArgumentException(__('إجمالي الفاتورة كبير جدًا.'));
        }

        if (Money::cmp($paid, $total, 2) === 1) {
            throw new DomainException(__('المدفوع أكبر من إجمالي المشتريات.'));
        }

        if (Money::cmp($paid, '0', 2) === 1 && $method === null) {
            throw new DomainException(__('اختار طريقة الدفع.'));
        }

        // ترتيب ثابت بالـ product_id لتقليل خطر الـ deadlock
        usort($clean, fn (array $a, array $b) => $a['product_id'] <=> $b['product_id']);

        return DB::transaction(function () use ($actor, $supplierId, $clean, $total, $paid, $method, $supplierRef, $notes): Purchase {
            // ShopScope: مورّد محل تاني = ModelNotFoundException
            Supplier::query()->where('is_active', true)->findOrFail($supplierId);

            $now = now();

            $purchase = new Purchase([
                'supplier_id' => $supplierId,
                'supplier_invoice_ref' => filled($supplierRef) ? trim($supplierRef) : null,
                'notes' => filled($notes) ? trim($notes) : null,
            ]);
            $purchase->forceFill([
                'received_by' => $actor->id,
                'invoice_number' => $this->numbers->next('purchase_invoice'),
                'total_amount' => $total,
                'received_at' => $now,
            ])->save();

            foreach ($clean as $line) {
                $product = Product::query()->findOrFail($line['product_id']);

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                ]);

                // بيحسب WAC و last_cost ويسجّل حركة المخزون
                $this->stock->purchase(
                    $product,
                    $line['quantity'],
                    $line['unit_cost'],
                    $purchase,
                    'مشتريات #'.$purchase->invoice_number,
                );
            }

            if (Money::cmp($paid, '0', 2) === 1) {
                SupplierPayment::query()->create([
                    'supplier_id' => $supplierId,
                    'purchase_id' => $purchase->id,
                    'paid_by' => $actor->id,
                    'amount' => $paid,
                    'method' => $method->value,
                    'paid_at' => $now,
                ]);
            }

            return $purchase->refresh();
        });
    }

    /** دفعة لاحقة للمورّد. مفيش دفع أكتر من المتبقي. */
    public function pay(User $actor, int $supplierId, string $amount, PaymentMethod $method, ?string $notes = null): SupplierPayment
    {
        $amount = $this->decimal($amount, 2, 10);

        if (Money::cmp($amount, '0', 2) !== 1) {
            throw new InvalidArgumentException(__('المبلغ لازم يكون أكبر من صفر.'));
        }

        return DB::transaction(function () use ($actor, $supplierId, $amount, $method, $notes): SupplierPayment {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            Gate::forUser($actor)->authorize('update', $supplier);

            if (Money::cmp($amount, $this->balance($supplier->id), 2) === 1) {
                throw new DomainException(__('المبلغ أكبر من المتبقي للمورّد.'));
            }

            return SupplierPayment::query()->create([
                'supplier_id' => $supplier->id,
                'paid_by' => $actor->id,
                'amount' => $amount,
                'method' => $method->value,
                'notes' => filled($notes) ? trim($notes) : null,
                'paid_at' => now(),
            ]);
        });
    }

    /** المتبقي للمورّد: موجب = علينا للمورّد. */
    public function balance(int $supplierId): string
    {
        $bought = Purchase::query()->where('supplier_id', $supplierId)
            ->toBase()->selectRaw('coalesce(sum(total_amount), 0) as t')->value('t');
        $paid = SupplierPayment::query()->where('supplier_id', $supplierId)
            ->toBase()->selectRaw('coalesce(sum(amount), 0) as t')->value('t');

        return Money::sub((string) $bought, (string) $paid, 2);
    }

    /**
     * @return numeric-string
     */
    private function decimal(string $value, int $scale, int $maxIntDigits): string
    {
        $value = trim($value);

        if (preg_match('/^\d{1,'.$maxIntDigits.'}(\.\d{1,'.$scale.'})?$/', $value) !== 1) {
            throw new InvalidArgumentException(__('قيمة غير صالحة.'));
        }

        assert(is_numeric($value));

        return bcadd($value, '0', $scale);
    }
}
