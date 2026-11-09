<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\LedgerDirection;
use App\Enums\LedgerSource;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\StockService;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * مسودة الفاتورة وبنودها. مفيش هنا مخزون ولا دفع ولا إكمال (الخطوة 5.3).
 * كل الحسابات بـ bcmath (عن طريق Money)، ومفيش floats.
 */
final class SaleService
{
    private const MAX_QTY = '999999999.999';

    public function __construct(
        private InvoiceNumberGenerator $numbers,
        private StockService $stock,
    ) {}

    public function createDraft(User $actor, ?int $customerId, PriceType $priceType): Sale
    {
        Gate::forUser($actor)->authorize('create', Sale::class);

        return DB::transaction(function () use ($actor, $customerId, $priceType): Sale {
            if ($customerId !== null) {
                // الـ ShopScope: عميل محل تاني = ModelNotFoundException (404)
                Customer::query()->where('is_active', true)->findOrFail($customerId);
            }

            $sale = new Sale(['customer_id' => $customerId, 'price_type' => $priceType->value]);
            $sale->forceFill([
                'created_by' => $actor->id,
                'invoice_number' => $this->numbers->next(),
                'status' => SaleStatus::Draft,
            ])->save();

            return $sale->refresh();
        });
    }

    public function addItem(User $actor, Sale $sale, int $productId, string $quantity = '1', ?string $unitPrice = null): SaleItem
    {
        $qty = $this->quantity($quantity);
        $requestedPrice = $unitPrice === null ? null : $this->money($unitPrice);

        return DB::transaction(function () use ($actor, $sale, $productId, $qty, $requestedPrice): SaleItem {
            $locked = $this->lockDraft($actor, $sale);

            // قراءة فقط: addItem مابتغيّرش المخزون، فمفيش داعي لـ lockForUpdate
            // (ومنع تعارض الأقفال مع complete() اللي بتقفل المنتجات بالترتيب).
            $product = Product::query()
                ->where('is_active', true)
                ->findOrFail($productId);

            $original = $locked->price_type === PriceType::Wholesale
                ? Money::norm($product->wholesale_price)
                : Money::norm($product->retail_price);

            $price = $requestedPrice ?? $original;
            $overrider = $this->resolveOverrider($actor, $price, $original);

            $existing = $locked->items()
                ->where('product_id', $product->id)
                ->where('unit_price', $price)
                ->first();

            if ($existing !== null) {
                $newQty = Money::add($existing->quantity, $qty, 3);

                if (Money::cmp($newQty, self::MAX_QTY, 3) === 1) {
                    throw new InvalidArgumentException(__('الكمية كبيرة جدًا.'));
                }

                $this->reprice(
                    $existing,
                    $newQty,
                    $price,
                    Money::norm($existing->discount_amount),
                );
                $item = $existing;
            } else {
                $item = $locked->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'barcode' => $product->barcode,
                    'part_number' => $product->part_number,
                    'oem_number' => $product->oem_number,
                    'condition' => $product->condition->value,
                    'unit' => $product->unit->value,
                    'quantity' => $qty,
                    'original_unit_price' => $original,
                    'unit_price' => $price,
                    'price_overridden_by' => $overrider,
                    'discount_amount' => '0.00',
                    'line_total' => Money::round(Money::mul($qty, $price, 5), 2),
                    // لقطة من المتوسط المرجّح وقت البيع
                    'unit_cost' => Money::norm($product->avg_cost),
                ]);
            }

            $this->recalculate($locked);

            return $item;
        });
    }

    /** كمية صفر = حذف البند. */
    public function setQuantity(User $actor, Sale $sale, int $itemId, string $quantity): void
    {
        $qty = $this->decimal($quantity, 3);

        if (Money::cmp($qty, '0', 3) === 0) {
            $this->removeItem($actor, $sale, $itemId);

            return;
        }

        DB::transaction(function () use ($actor, $sale, $itemId, $qty): void {
            $locked = $this->lockDraft($actor, $sale);
            $item = $locked->items()->findOrFail($itemId);

            $this->reprice(
                $item,
                $qty,
                Money::norm($item->unit_price),
                Money::norm($item->discount_amount),
            );
            $this->recalculate($locked);
        });
    }

    /** تغيير السعر = override، بصلاحية override-price. الرجوع للسعر الأصلي مش بيحتاج صلاحية. */
    public function setPrice(User $actor, Sale $sale, int $itemId, string $unitPrice): void
    {
        $price = $this->money($unitPrice);

        DB::transaction(function () use ($actor, $sale, $itemId, $price): void {
            $locked = $this->lockDraft($actor, $sale);
            $item = $locked->items()->findOrFail($itemId);

            $overrider = $this->resolveOverrider($actor, $price, Money::norm($item->original_unit_price));

            $this->reprice(
                $item,
                Money::norm($item->quantity),
                $price,
                Money::norm($item->discount_amount),
                ['price_overridden_by' => $overrider],
            );
            $this->recalculate($locked);
        });
    }

    public function setItemDiscount(User $actor, Sale $sale, int $itemId, string $discount): void
    {
        $amount = $this->money($discount);

        DB::transaction(function () use ($actor, $sale, $itemId, $amount): void {
            $locked = $this->lockDraft($actor, $sale);
            $item = $locked->items()->findOrFail($itemId);

            $this->reprice(
                $item,
                Money::norm($item->quantity),
                Money::norm($item->unit_price),
                $amount,
            );
            $this->recalculate($locked);
        });
    }

    public function removeItem(User $actor, Sale $sale, int $itemId): void
    {
        DB::transaction(function () use ($actor, $sale, $itemId): void {
            $locked = $this->lockDraft($actor, $sale);

            $locked->items()->findOrFail($itemId)->delete();
            $this->recalculate($locked);
        });
    }

    /** خصم على مستوى الفاتورة. */
    public function setDiscount(User $actor, Sale $sale, string $discount): void
    {
        $amount = $this->money($discount);

        DB::transaction(function () use ($actor, $sale, $amount): void {
            $locked = $this->lockDraft($actor, $sale);

            if (Money::cmp($amount, $this->subtotalOf($locked), 2) === 1) {
                throw new InvalidArgumentException(__('الخصم أكبر من إجمالي الفاتورة.'));
            }

            $this->recalculate($locked, $amount);
        });
    }

    public function discardDraft(User $actor, Sale $sale): void
    {
        DB::transaction(function () use ($actor, $sale): void {
            $locked = $this->lockDraft($actor, $sale);

            $locked->items()->get()->each(fn (SaleItem $item) => $item->delete());
            $locked->delete();
        });
    }

    /**
     * Post the invoice: stock + payment + ledger in a single transaction.
     *
     * $paid = amount received. It may exceed the total only for cash;
     * the excess is treated as change and is never recorded as revenue.
     */
    /**
 * Post the invoice: stock + payment + ledger in a single transaction.
 *
 * $paid = amount received. It may exceed the total only for cash;
 * the excess is treated as change and is never recorded as revenue.
 */
public function complete(User $actor, Sale $sale, ?PaymentMethod $method, string $paid, ?string $notes = null): Sale
{
    Gate::forUser($actor)->authorize('complete', $sale);
    $paid = $this->money($paid);

    return DB::transaction(function () use ($actor, $sale, $method, $paid, $notes): Sale {
        // Locks the sale row, re-authorizes, and guarantees it is still a draft.
        $locked = $this->lockDraft($actor, $sale);

        // Stable ordering keeps lock acquisition deterministic (deadlock risk).
        $items = $locked->items()->orderBy('product_id')->orderBy('id')->get();

        if ($items->isEmpty()) {
            throw new DomainException(__('الفاتورة فاضية. ضيف بند واحد على الأقل.'));
        }

        // ---- Integrity check: stored totals must match the line items ----
        $subtotal = $this->subtotalOf($locked);
        $expectedTotal = Money::sub($subtotal, Money::norm($locked->discount_amount), 2);

        if (Money::cmp($subtotal, $locked->subtotal, 2) !== 0
            || Money::cmp($expectedTotal, $locked->total_amount, 2) !== 0
        ) {
            throw new DomainException(__('إجمالي الفاتورة غير محدّث. أعد فتح الفاتورة وحاول مرة أخرى.'));
        }

        // ---- Payment validation (before any stock movement) ----
        $total = Money::norm($locked->total_amount);
        $overpaid = Money::cmp($paid, $total, 2) === 1;

        if ($overpaid && $method !== PaymentMethod::Cash) {
            throw new DomainException(__('المبلغ المدفوع أكبر من إجمالي الفاتورة.'));
        }

        if (Money::cmp($paid, '0', 2) === 1 && $method === null) {
            throw new DomainException(__('اختار طريقة الدفع.'));
        }

        $applied = $overpaid ? $total : $paid;   // Change is not revenue.
        $due = Money::sub($total, $applied, 2);

        // ---- Customer validation ----
        $customerId = $locked->customer_id;

        if ($customerId !== null) {
            $customerActive = Customer::query()
                ->where('is_active', true)
                ->whereKey($customerId)
                ->exists();

            if (! $customerActive) {
                throw new DomainException(__('العميل غير نشط. اختار عميل نشط.'));
            }
        } elseif (Money::cmp($due, '0', 2) === 1) {
            throw new DomainException(__('فيه مبلغ متبقي. اختار عميل نشط عشان يتسجل عليه.'));
        }

        // ---- Stock + freeze the cost from the actual sale movement ----
        foreach ($items as $item) {
            $product = Product::query()->find((int) $item->product_id);

            if ($product === null || ! $product->is_active) {
                throw new DomainException(__('المنتج ":name" محذوف أو موقوف.', ['name' => $item->product_name]));
            }

            try {
                $movement = $this->stock->sale(
                    $product,
                    (string) $item->quantity,
                    $locked,
                    'فاتورة #'.$locked->invoice_number,
                );
            } catch (InsufficientStockException) {
                throw new DomainException(__('الرصيد لا يكفي للمنتج ":name".', ['name' => $item->product_name]));
            }

            // Sale is still a draft in the DB here, so the SaleItem guard allows the write.
            $item->forceFill(['unit_cost' => Money::norm($movement->unit_cost)])->save();
        }

        // ---- Payment and ledger ----
        $now = now();
        $payment = null;

        if (Money::cmp($applied, '0', 2) === 1) {
            if ($method === null) {
                throw new DomainException(__('اختار طريقة الدفع.'));
            }

            $payment = Payment::query()->create([
                'sale_id' => $locked->id,
                'customer_id' => $customerId,
                'received_by' => $actor->id,
                'amount' => $applied,
                'method' => $method->value,
                'paid_at' => $now,
            ]);
        }

        if ($customerId !== null && Money::cmp($total, '0', 2) === 1) {
            CustomerLedger::query()->create([
                'customer_id' => $customerId,
                'sale_id' => $locked->id,
                'created_by' => $actor->id,
                'source' => LedgerSource::Invoice,
                'direction' => LedgerDirection::Debit,
                'amount' => $total,
                'occurred_at' => $now,
            ]);

            if ($payment !== null) {
                CustomerLedger::query()->create([
                    'customer_id' => $customerId,
                    'sale_id' => $locked->id,
                    'payment_id' => $payment->id,
                    'created_by' => $actor->id,
                    'source' => LedgerSource::Payment,
                    'direction' => LedgerDirection::Credit,
                    'amount' => $applied,
                    'occurred_at' => $now,
                ]);
            }
        }

        // ---- Final step: flip the status ----
        $locked->forceFill([
            'notes' => $notes ?? $locked->notes,
            'status' => SaleStatus::Completed,
            'completed_by' => $actor->id,
            'completed_at' => $now,
        ])->save();

        return $locked->refresh();
    });
}
    // ---------- internals ----------

    private function lockDraft(User $actor, Sale $sale): Sale
    {
        Gate::forUser($actor)->authorize('update', $sale);

        $locked = Sale::query()->lockForUpdate()->findOrFail($sale->id);

        if ($locked->status !== SaleStatus::Draft) {
            throw new DomainException(__('الفاتورة دي مش مسودة ومينفعش تتعدل.'));
        }

        return $locked;
    }

    private function resolveOverrider(User $actor, string $price, string $original): ?int
    {
        if (Money::cmp($price, $original, 2) === 0) {
            return null;
        }

        Gate::forUser($actor)->authorize('override-price');

        return $actor->id;
    }

    /** @param array<string, mixed> $extra */
    private function reprice(SaleItem $item, string $quantity, string $price, string $discount, array $extra = []): void
    {
        $gross = Money::round(Money::mul($quantity, $price, 5), 2);

        if (Money::cmp($discount, $gross, 2) === 1) {
            throw new InvalidArgumentException(__('الخصم أكبر من إجمالي البند.'));
        }

        $item->forceFill([
            ...$extra,
            'quantity' => $quantity,
            'unit_price' => $price,
            'discount_amount' => $discount,
            'line_total' => Money::sub($gross, $discount, 2),
        ])->save();
    }

    /** @return numeric-string */
    private function subtotalOf(Sale $sale): string
    {
        $subtotal = '0.00';

        foreach ($sale->items()->get() as $item) {
            $subtotal = Money::add($subtotal, $item->line_total, 2);
        }

        return $subtotal;
    }

    /** المكان الوحيد اللي بيكتب المجاميع، عشان CHECK الـ total = subtotal - discount دايمًا يتحقق. */
    private function recalculate(Sale $sale, ?string $discount = null): void
    {
        $subtotal = $this->subtotalOf($sale);
        $discount ??= Money::norm($sale->discount_amount);

        // لو حذفنا بنود والخصم بقى أكبر من المجموع، نقصّه بدل ما نكسر الـ CHECK.
        if (Money::cmp($discount, $subtotal, 2) === 1) {
            $discount = $subtotal;
        }

        $sale->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total_amount' => Money::sub($subtotal, $discount, 2),
        ])->save();
    }

    /** @return numeric-string */
    private function quantity(string $value): string
    {
        $qty = $this->decimal($value, 3);

        if (Money::cmp($qty, '0', 3) !== 1) {
            throw new InvalidArgumentException(__('الكمية لازم تكون أكبر من صفر.'));
        }

        return $qty;
    }

    /** @return numeric-string */
    private function money(string $value): string
    {
        return $this->decimal($value, 2);
    }

    /**
     * @param  int<0, max>  $scale
     * @return numeric-string
     */
    private function decimal(string $value, int $scale): string
    {
        $value = trim($value);

        if (preg_match('/^\d{1,9}(\.\d{1,'.$scale.'})?$/', $value) !== 1) {
            throw new InvalidArgumentException(__('قيمة غير صالحة.'));
        }

        assert(is_numeric($value));

        return bcadd($value, '0', $scale);
    }
}
