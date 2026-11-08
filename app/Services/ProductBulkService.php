<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
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

    /**
     * @param  list<int>  $ids
     * @return array{done: int, skipped: int}
     */
    public function apply(User $actor, string $action, array $ids): array
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown bulk action [{$action}].");
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return ['done' => 0, 'skipped' => 0];
        }

        $done = 0;
        $skipped = 0;

        DB::transaction(function () use ($actor, $action, $ids, &$done, &$skipped): void {
            // الـ ShopScope بيضمن إن الـ ids الغريبة مش هترجع أصلًا.
            // lockForUpdate بيمنع StockService من تغيير الرصيد بين الفحص والحذف،
            // والترتيب بالـ id بيقلل احتمال الـ deadlock بين عمليتين جماعيتين.
            $products = Product::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // ids مش لاقيينها (محل تاني أو اتحذفت) بتتحسب متخطية
            $skipped += count($ids) - $products->count();

            foreach ($products as $product) {
                if ($this->handle($actor, $action, $product)) {
                    $done++;
                } else {
                    $skipped++;
                }
            }
        });

        return ['done' => $done, 'skipped' => $skipped];
    }

    private function handle(User $actor, string $action, Product $product): bool
    {
        $gate = Gate::forUser($actor);

        if ($action === self::DELETE) {
            // مينفعش نحذف منتج رصيده مش صفر
            if (! $gate->allows('delete', $product) || ! $this->hasZeroStock($product)) {
                return false;
            }

            $product->delete();

            return true;
        }

        if (! $gate->allows('update', $product)) {
            return false;
        }

        $product->update(['is_active' => $action === self::ACTIVATE]);

        return true;
    }

    private function hasZeroStock(Product $product): bool
    {
        return Money::cmp($product->stock_qty, '0', 3) === 0;
    }
}
