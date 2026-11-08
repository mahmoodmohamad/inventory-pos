@php
    $base = route('storefront.index', ['shopSlug' => $shop->slug]);
    $currency = $shop->currency === 'EGP' ? 'ج.م' : $shop->currency;
    $money = fn (float $n): string => number_format($n, floor($n) == $n ? 0 : 2);

    $price = (float) $product->retail_price;
    $inStock = (bool) ($inStock ?? false);
    $fitments = $fitments ?? collect();
    $alternatives = $alternatives ?? [];

    $msg = 'السلام عليكم، عايز أسأل عن: '.$product->name
        .($product->part_number ? ' ('.$product->part_number.')' : '');
    $waUrl = $whatsapp ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($msg) : null;

    $condKey = $product->condition->value;

    // Group fitments by "Make Model": one row per car, year pills inside.
    $groups = $fitments
        ->filter(fn ($f) => $f->variant?->vehicleModel)
        ->groupBy(fn ($f) => trim(($f->variant->vehicleModel->make?->name ?? '').' '.$f->variant->vehicleModel->name))
        ->sortKeys();

    $copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';
@endphp

<x-layouts::storefront :shop="$shop" :title="$product->name.' - '.$shop->name">
    <div class="sf-product-page">

        <nav class="sf-crumbs" aria-label="مسار التنقل">
            <ol>
                <li><a href="{{ $base }}">الرئيسية</a></li>
                @if ($product->category)
                    <li><span>{{ $product->category->name }}</span></li>
                @endif
                <li><span aria-current="page">{{ $product->name }}</span></li>
            </ol>
        </nav>

        <div class="sf-pd-layout">

            {{-- Main column --}}
            <div class="sf-pd-main">

                <header class="sf-pd-head" style="--c: var(--sf-petrol)" data-k="{{ $condKey }}">
                    <div class="sf-badges">
                        <span class="sf-cond" data-k="{{ $condKey }}">{{ $product->condition->label() }}</span>
                        <span class="sf-stock {{ $inStock ? 'is-in' : 'is-out' }}">
                            <span class="sf-stock-dot" aria-hidden="true"></span>
                            {{ $inStock ? 'متوفر في المخزون' : 'غير متوفر حاليًا' }}
                        </span>
                    </div>

                    <h1 class="sf-pd-title">{{ $product->name }}</h1>

                    @if ($product->brand || $product->category)
                        <div class="sf-pd-meta">
                            @if ($product->brand)
                                <span>الماركة: <strong>{{ $product->brand->name }}</strong></span>
                            @endif
                            @if ($product->category)
                                <span>القسم: <strong>{{ $product->category->name }}</strong></span>
                            @endif
                        </div>
                    @endif
                </header>

                {{-- Identifiers --}}
                <section class="sf-panel" aria-labelledby="sf-ids-title">
                    <div class="sf-panel-h">
                        <div>
                            <h2 id="sf-ids-title">بيانات القطعة</h2>
                            <p>تأكد من أرقام القطعة قبل إتمام الطلب</p>
                        </div>
                    </div>

                    <div class="sf-ids">
                        @foreach ([['رقم القطعة', $product->part_number], ['رقم OEM', $product->oem_number], ['الباركود', $product->barcode]] as [$label, $value])
                            @if ($value)
                                <div class="sf-id">
                                    <span>{{ $label }}</span>
                                    <div class="sf-id-row">
                                        <span class="sf-code">{{ $value }}</span>
                                        <button type="button" class="sf-copy" data-copy="{{ $value }}" aria-label="نسخ {{ $label }}">{!! $copyIcon !!}</button>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @if (! $product->part_number && ! $product->oem_number && ! $product->barcode)
                            <p class="sf-muted">لا توجد أرقام تعريف مسجلة لهذه القطعة.</p>
                        @endif
                    </div>
                </section>

                @if ($product->notes)
                    <section class="sf-panel" aria-labelledby="sf-desc-title">
                        <div class="sf-panel-h">
                            <div>
                                <h2 id="sf-desc-title">تفاصيل المنتج</h2>
                            </div>
                        </div>
                        <div class="sf-product-description">{{ $product->notes }}</div>
                    </section>
                @endif

                {{-- Fitments --}}
                @if ($groups->isNotEmpty())
                    <section class="sf-panel" aria-labelledby="sf-fit-title">
                        <div class="sf-panel-h">
                            <div>
                                <h2 id="sf-fit-title">السيارات المتوافق معها</h2>
                                <p>اضغط على سنة لعرض كل القطع اللي تركب على نفس السيارة</p>
                            </div>
                            <span class="sf-pill-count">{{ $fitments->count() }} توافق</span>
                        </div>

                        <div class="sf-fit-groups">
                            @foreach ($groups as $label => $items)
                                <div class="sf-fit-group">
                                    <h3>{{ $label }}</h3>
                                    <div class="sf-fit-pills">
                                        @foreach ($items->sortBy(fn ($f) => $f->variant->year_from) as $fitment)
                                            @php $v = $fitment->variant; @endphp
                                            <a class="sf-fit-pill"
                                                href="{{ route('storefront.index', ['shopSlug' => $shop->slug, 'make' => $v->vehicleModel->vehicle_make_id, 'model' => $v->vehicle_model_id, 'variant' => $v->id]) }}">
                                                {{ $v->name }}
                                                <bdi>{{ $v->year_from }}–{{ $v->year_to ?? 'الآن' }}</bdi>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @else
                    <section class="sf-panel sf-fit-empty">
                        <div class="sf-panel-h"><div><h2>توافق السيارات</h2></div></div>
                        <p>لم تتم إضافة بيانات توافق السيارات لهذه القطعة بعد. تواصل معنا للتأكد من مناسبتها لسيارتك.</p>
                        @if ($waUrl)
                            <a class="sf-wa" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">اسأل عن التوافق</a>
                        @endif
                    </section>
                @endif

                {{-- Same part number, other conditions --}}
                @if (count($alternatives) > 0)
                    <section class="sf-panel" aria-labelledby="sf-alt-title">
                        <div class="sf-panel-h">
                            <div>
                                <h2 id="sf-alt-title">نفس القطعة بأنواع تانية</h2>
                                <p>نفس رقم القطعة، بحالة أو ماركة مختلفة</p>
                            </div>
                        </div>
                        <div class="sf-alts">
                            @foreach ($alternatives as $alt)
                                <a class="sf-alt" href="{{ route('storefront.products.show', ['shopSlug' => $shop->slug, 'productId' => $alt['id']]) }}">
                                    <span class="sf-alt-main">
                                        <span class="sf-cond" data-k="{{ $alt['condition_key'] }}">{{ $alt['condition'] }}</span>
                                        @if ($alt['brand'])<span class="sf-muted">{{ $alt['brand'] }}</span>@endif
                                        <span class="sf-stock {{ $alt['in_stock'] ? 'is-in' : 'is-out' }}">{{ $alt['in_stock'] ? 'متوفر' : 'غير متوفر' }}</span>
                                    </span>
                                    <strong>{{ $alt['price'] > 0 ? $money($alt['price']).' '.$currency : 'السعر عند السؤال' }}</strong>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- Buy panel --}}
            <aside class="sf-buy" aria-label="تفاصيل الشراء">
                <div class="sf-buy-status {{ $inStock ? 'is-in' : 'is-out' }}">
                    <span class="sf-stock-dot" aria-hidden="true"></span>
                    {{ $inStock ? 'القطعة متاحة للطلب' : 'القطعة غير متاحة حاليًا' }}
                </div>

                <div class="sf-buy-price">
                    <span>سعر المنتج</span>
                    @if ($price > 0)
                        <strong>{{ $money($price) }}</strong><small>{{ $currency }}</small>
                    @else
                        <strong class="is-ask">السعر عند السؤال</strong>
                    @endif
                </div>

                <div class="sf-buy-actions">
                    @if ($inStock)
                        <form method="POST" action="{{ route('storefront.cart.store', ['shopSlug' => $shop->slug]) }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="sf-add">أضف للسلة</button>
                        </form>
                    @endif

                    @if ($waUrl)
                        <a class="sf-wa" href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer">اسأل عن المنتج على واتساب</a>
                    @endif
                </div>

                <div class="sf-buy-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                    <p>راجع رقم القطعة وتوافقها مع سيارتك قبل الطلب.</p>
                </div>
            </aside>
        </div>

        <div class="sf-back">
            <a href="{{ $base }}">
                <svg class="sf-flip" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
                العودة إلى جميع المنتجات
            </a>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('click', function (e) {
                var b = e.target.closest('[data-copy]');
                if (!b || !navigator.clipboard) return;
                navigator.clipboard.writeText(b.dataset.copy).then(function () {
                    b.classList.add('is-done');
                    setTimeout(function () { b.classList.remove('is-done'); }, 1400);
                });
            });
        </script>
    @endpush
</x-layouts::storefront>
