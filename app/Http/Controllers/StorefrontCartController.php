<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\Storefront\StorefrontCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontCartController extends Controller
{
    public function show(Request $request, StorefrontCart $cart): View
    {
        $shop = $this->shop($request);
        $summary = $cart->summary($shop);

        return view('storefront.cart', [
            'shop' => $shop,
            'cart' => $summary,
            'checkoutUrl' => $this->checkoutUrl($shop, $summary),
        ]);
    }

    public function store(Request $request, StorefrontCart $cart): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:'.StorefrontCart::MAX_QTY],
        ]);

        $added = $cart->add($this->shop($request), (int) $data['product_id'], (int) ($data['qty'] ?? 1));

        return back()->with('status', $added ? 'تمت الإضافة للسلة.' : 'القطعة دي مش متوفرة حاليًا.');
    }

    public function update(Request $request, StorefrontCart $cart, string $shopSlug, int $productId): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:'.StorefrontCart::MAX_QTY],
        ]);

        $cart->set($this->shop($request), $productId, (int) $data['qty']);

        return back();
    }

    public function destroy(Request $request, StorefrontCart $cart, string $shopSlug, int $productId): RedirectResponse
    {
        $cart->set($this->shop($request), $productId, 0);

        return back();
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('storefront_shop');
    }

    /** @param array{lines: list<array<string, mixed>>, total: float, has_unpriced: bool} $cart */
    private function checkoutUrl(Shop $shop, array $cart): ?string
    {
        $number = $shop->whatsappNumber();

        if ($number === null || $cart['lines'] === []) {
            return null;
        }

        $currency = $shop->currency === 'EGP' ? 'ج.م' : $shop->currency;
        $lines = ['السلام عليكم، عايز أطلب:', ''];

        foreach ($cart['lines'] as $l) {
            $lines[] = '• '.$l['name']
                .($l['part_number'] ? ' ('.$l['part_number'].')' : '')
                .' ['.$l['condition'].']'
                .' × '.$l['qty'];
        }

        if ($cart['total'] > 0) {
            $lines[] = '';
            $lines[] = 'الإجمالي التقريبي: '.number_format($cart['total'], 2).' '.$currency
                .($cart['has_unpriced'] ? ' (+ قطع السعر عند السؤال)' : '');
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode(implode("\n", $lines));
    }
}
