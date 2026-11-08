<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PriceType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sales\SaleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * مسودات فواتير تجريبية عن طريق SaleService (نفس مسار الـ POS الحقيقي).
 *
 * لازم DemoShopSeeder و DemoProductSeeder و DemoStockSeeder و DemoCustomerSeeder يتنفذوا قبله.
 * آمن للتشغيل أكتر من مرة: لو فيه فواتير للمحل خلاص مش بيعمل حاجة.
 *
 * عمدًا مفيش فواتير مكتملة ولا مدفوعات ولا قيود حساب عميل هنا: دول بيتولدوا من
 * خدمة الإكمال (الخطوة 5.3) عشان المخزون والـ ledger يفضلوا متسقين.
 */
class DemoSaleSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::query()->where('slug', 'demo-shop')->first();

        if ($shop === null) {
            return;
        }

        app(TenantContext::class)->run($shop, function (): void {
            if (Sale::query()->exists()) {
                return;
            }

            $cashier = User::query()->where('email', 'cashier@demo.test')->first();

            if ($cashier === null) {
                return;
            }

            $sales = app(SaleService::class);

            // 1) فاتورة قطاعي لعميل مجهول
            $walkIn = $sales->createDraft($cashier, null, PriceType::Retail);
            $this->addItem($sales, $cashier, $walkIn, '6220000000021', '2');
            $this->addItem($sales, $cashier, $walkIn, '6220000000031', '4');

            // 2) فاتورة جملة لعميل، مع خصم على بند
            $uncle = Customer::query()->where('phone', '01011111111')->first();

            if ($uncle !== null) {
                $wholesale = $sales->createDraft($cashier, $uncle->id, PriceType::Wholesale);
                $item = $this->addItem($sales, $cashier, $wholesale, '6220000000011', '3');
                $this->addItem($sales, $cashier, $wholesale, '6220000000012', '5');

                if ($item !== null) {
                    $sales->setItemDiscount($cashier, $wholesale, $item, '50.00');
                }
            }

            // 3) فاتورة قطاعي لعميل مع خصم على مستوى الفاتورة
            $mostafa = Customer::query()->where('phone', '01022222222')->first();

            if ($mostafa !== null) {
                $retail = $sales->createDraft($cashier, $mostafa->id, PriceType::Retail);
                $this->addItem($sales, $cashier, $retail, '6220000000041', '1');
                $this->addItem($sales, $cashier, $retail, '6220000000021', '1');
                $sales->setDiscount($cashier, $retail, '30.00');
            }
        });
    }

    /** يرجّع id البند، أو null لو المنتج مش موجود. */
    private function addItem(SaleService $sales, User $actor, Sale $sale, string $barcode, string $quantity): ?int
    {
        $product = Product::query()->where('barcode', $barcode)->first();

        if ($product === null) {
            return null;
        }

        return $sales->addItem($actor, $sale, $product->id, $quantity)->id;
    }
}
