<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * العمليات الجماعية على المنتجات. الـ Service ده هو اللي بيقرر
 * إيه اللي يتنفذ وإيه اللي يتخطى، والـ component بس بيعرض النتيجة.
 *
 * الحذف الفردي كمان بيعدّي من هنا (apply بـ id واحد) عشان فحص الرصيد والحذف
 * يحصلوا تحت نفس الـ lock اللي بيستخدمه StockService.
 */
final class ProductBulkService
{
    public const ACTIVATE = 'activate';

    public const DEACTIVATE = 'deactivate';

    public const DELETE = 'delete';

    public const ACTIONS = [self::ACTIVATE, self::DEACTIVATE, self::DELETE];

    /** أقصى عدد منتجات في عملية واحدة (بيحمي من قفل صفوف كتير). */
    public const MAX_IDS = 500;

    /**
     * @param  list<int>  $ids
     * @return array{done: int, skipped: int}
     */
    public function apply(User $actor, string $action, array $ids): array
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown bulk action [{$action}].");
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0,
        )));

        if ($ids === []) {
            return ['done' => 0, 'skipped' => 0];
        }

        if (count($ids) > self::MAX_IDS) {
            throw new InvalidArgumentException(__('الحد الأقصى :max منتج في العملية الواحدة.', ['max' => self::MAX_IDS]));
        }

        if (! $actor->is_active || $actor->shop_id === null) {
            return ['done' => 0, 'skipped' => count($ids)];
        }

        // النتيجة بترجع من الـ closure نفسه، فمفيش عدّادات بتتراكم لو الـ transaction اتعادت.
        return DB::transaction(function () use ($actor, $action, $ids): array {
            // الـ ShopScope بيضمن إن الـ ids الغريبة مش هترجع أصلًا.
            // lockForUpdate بيمنع StockService من تغيير الرصيد بين الفحص والحذف،
            // والترتيب بالـ id بيقلل احتمال الـ deadlock بين عمليتين جماعيتين.
            $products = Product::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // ids مش لاقيينها (محل تاني أو اتحذفت) بتتحسب متخطية
            $done = 0;
            $skipped = count($ids) - $products->count();

            $inDraft = $action === self::DELETE
                ? $this->idsInDraftSales($products->modelKeys())
                : [];

            foreach ($products as $product) {
                if ($this->handle($actor, $action, $product, $inDraft)) {
                    $done++;
                } else {
                    $skipped++;
                }
            }

            return ['done' => $done, 'skipped' => $skipped];
        });
    }

    /**
     * @param  array<int, true>  $inDraft  ids المنتجات الموجودة في مسودات فواتير
     */
    private function handle(User $actor, string $action, Product $product, array $inDraft): bool
    {
        $gate = Gate::forUser($actor);

        if ($action === self::DELETE) {
            // مينفعش نحذف منتج رصيده مش صفر أو موجود في مسودة فاتورة
            if (! $gate->allows('delete', $product)
                || ! $this->hasZeroStock($product)
                || isset($inDraft[$product->id])
            ) {
                return false;
            }

            $product->delete();

            return true;
        }

        if (! $gate->allows('update', $product)) {
            return false;
        }

        $target = $action === self::ACTIVATE;

        // الحالة مطابقة أصلًا: نجاح من غير كتابة
        if ($product->is_active === $target) {
            return true;
        }

        $product->update(['is_active' => $target]);

        return true;
    }

    private function hasZeroStock(Product $product): bool
    {
        return Money::cmp($product->stock_qty, '0', 3) === 0;
    }

    /**
     * @param  array<int, int|string>  $productIds
     * @return array<int, true>
     */
    private function idsInDraftSales(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        // الـ ShopScope متطبق على SaleItem و Sale
        return SaleItem::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('sale', fn ($q) => $q->where('status', SaleStatus::Draft->value))
            ->distinct()
            ->pluck('product_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }
}
