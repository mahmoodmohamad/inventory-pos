<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\Shop;
use App\Support\Money;

/** يشتغل جوه سياق محل (ResolvePublicShop). الـ session بتخزّن id => qty بس. */
final class StorefrontCart
{
    public const MAX_QTY = 99;

    /** يرجّع false لو المنتج مش موجود/موقوف/غير متوفر (الـ ShopScope بيمنع منتجات المحلات التانية). */
    public function add(Shop $shop, int $productId, int $qty = 1): bool
    {
        $available = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->where('stock_qty', '>', 0)
            ->exists();

        if (! $available) {
            return false;
        }

        $items = $this->items($shop);
        $items[$productId] = min(self::MAX_QTY, ($items[$productId] ?? 0) + max(1, $qty));
        $this->put($shop, $items);

        return true;
    }

    /** qty <= 0 يحذف السطر. */
    public function set(Shop $shop, int $productId, int $qty): void
    {
        $items = $this->items($shop);

        if (! isset($items[$productId])) {
            return;
        }

      
        if ($qty <= 0) {
    unset($items[$productId]);
} else {
    $items[$productId] = min(self::MAX_QTY, $qty);
}
$this->put($shop, $items);
    }

    public function clear(Shop $shop): void
    {
        session()->forget($this->key($shop));
    }

    public function count(Shop $shop): int
    {
        return (int) array_sum($this->items($shop));
    }

    /** @return array<int, int> */
    private function items(Shop $shop): array
    {
        return array_map('intval', (array) session()->get($this->key($shop), []));
    }

    /** @param array<int, int> $items */
    private function put(Shop $shop, array $items): void
    {
        session()->put($this->key($shop), $items);
    }

    private function key(Shop $shop): string
    {
        return 'storefront_cart.'.$shop->id;
    }

    /**
     * @return array{lines: list<array<string, mixed>>, total: numeric-string, count: int, has_unpriced: bool}
     */
    public function summary(Shop $shop): array
    {
        $items = $this->items($shop);

        $products = $items === [] ? collect() : Product::query()
            ->with('brand')
            ->where('is_active', true)
            ->where('stock_qty', '>', 0)
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $lines = [];
        $clean = [];
        $total = '0.00';
        $unpriced = false;

        foreach ($items as $id => $qty) {
            $p = $products->get($id);

            if ($p === null) {
                continue; // اتحذف أو اتوقف أو خلص من المخزون بعد ما اتضاف (من غير ما نكشف الكمية)
            }

            $price = Money::norm($p->retail_price);
            $subtotal = Money::mul($price, (string) $qty, 2);
            $total = Money::add($total, $subtotal, 2);
            $unpriced = $unpriced || Money::cmp($price, '0', 2) <= 0;
            $clean[$id] = $qty;

            $lines[] = [
                'id' => $p->id,
                'name' => $p->name,
                'part_number' => $p->part_number,
                'condition' => $p->condition->label(),
                'brand' => $p->brand?->name,
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $subtotal,
            ];
        }

        if (count($clean) !== count($items)) {
            $this->put($shop, $clean);
        }

        return [
            'lines' => $lines,
            'total' => $total,
            'count' => (int) array_sum($clean),
            'has_unpriced' => $unpriced,
        ];
    }
}
