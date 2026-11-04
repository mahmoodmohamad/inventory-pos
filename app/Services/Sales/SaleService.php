<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
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
    public function __construct(private InvoiceNumberGenerator $numbers) {}

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

            $product = Product::query()->where('is_active', true)->findOrFail($productId);

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
                $this->reprice(
                    $existing,
                    Money::add($existing->quantity, $qty, 3),
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
