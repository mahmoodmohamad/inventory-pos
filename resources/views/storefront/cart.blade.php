@php
    $base = route('storefront.index', ['shopSlug' => $shop->slug]);
    $currency = $shop->currency === 'EGP' ? 'ج.م' : $shop->currency;
    $money = fn (float $n): string => number_format($n, floor($n) == $n ? 0 : 2);
@endphp

<x-layouts::storefront :shop="$shop" :title="'السلة - '.$shop->name">
    <h1 style="font-size:1.375rem;margin-block:1.25rem">سلة الطلب</h1>

    @if ($cart['lines'] === [])
        <div class="sf-empty">
            <strong>السلة فاضية</strong>
            <a href="{{ $base }}">ارجع للقطع</a>
        </div>
    @else
        <div class="sf-card">
            @foreach ($cart['lines'] as $l)
                <div class="sf-cart-row">
                    <div>
                        <div class="sf-name">{{ $l['name'] }}</div>
                        <div class="sf-codes">
                            @if ($l['part_number'])<span class="sf-code">{{ $l['part_number'] }}</span>@endif
                            <span class="sf-muted">{{ $l['condition'] }}@if ($l['brand']) · {{ $l['brand'] }}@endif</span>
                        </div>
                        <div class="sf-muted">
                            {{ $l['price'] > 0 ? $money($l['price']).' '.$currency : 'السعر عند السؤال' }}
                        </div>
                    </div>

                    <div class="sf-actions">
                        <form method="POST" action="{{ route('storefront.cart.update', ['shopSlug' => $shop->slug, 'productId' => $l['id']]) }}">
                            @csrf @method('PATCH')
                            <input type="number" name="qty" value="{{ $l['qty'] }}" min="0" max="99"
                                class="sf-qty" aria-label="الكمية" onchange="this.form.submit()">
                        </form>
                        <form method="POST" action="{{ route('storefront.cart.destroy', ['shopSlug' => $shop->slug, 'productId' => $l['id']]) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="sf-link-btn">حذف</button>
                        </form>
                    </div>
                </div>
            @endforeach

            @if ($cart['total'] > 0)
                <div class="sf-total">
                    <span>الإجمالي{{ $cart['has_unpriced'] ? ' (بدون قطع السعر عند السؤال)' : '' }}</span>
                    <span>{{ $money($cart['total']) }} {{ $currency }}</span>
                </div>
            @endif
        </div>

        <div class="sf-actions" style="margin-top:1rem">
            @if ($checkoutUrl)
                <a class="sf-btn" style="display:inline-flex;align-items:center;text-decoration:none"
                    target="_blank" rel="noopener" href="{{ $checkoutUrl }}">ابعت الطلب على واتساب</a>
            @else
                <p class="sf-muted">المحل لسه ما ضافش رقم واتساب لاستقبال الطلبات. كلّمهم على {{ $shop->phone }}.</p>
            @endif
            <a href="{{ $base }}">كمّل تسوق</a>
        </div>
    @endif
</x-layouts::storefront>