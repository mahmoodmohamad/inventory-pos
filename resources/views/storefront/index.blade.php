@php
    $base = route('storefront.index', ['shopSlug' => $shop->slug]);
    $currency = $shop->currency === 'EGP' ? 'ج.م' : $shop->currency;
    $hasFilters = filled($filters['q'] ?? null) || filled($filters['make'] ?? null) || filled($filters['condition'] ?? null);
    $money = fn (float $n): string => number_format($n, floor($n) == $n ? 0 : 2);
    $products->withQueryString();
@endphp

<x-layouts::storefront :shop="$shop">
    <form method="GET" action="{{ $base }}" class="sf-picker" role="search">
        <h1>اختار عربيتك</h1>
        <p>هنعرض لك القطع اللي بتركب عليها حسب الماركة والموديل وسنة الصنع.</p>

        @if (filled($filters['condition'] ?? null))
            <input type="hidden" name="condition" value="{{ $filters['condition'] }}">
        @endif

        <div class="sf-selects">
            <div class="sf-field">
                <label for="sf-make">الماركة</label>
                <select id="sf-make" name="make" class="sf-select"
                    onchange="this.form.model.value=''; this.form.variant.value=''; this.form.submit()">
                    <option value="">كل الماركات</option>
                    @foreach ($makes as $make)
                        <option value="{{ $make->id }}" @selected((string) ($filters['make'] ?? '') === (string) $make->id)>{{ $make->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sf-field">
                <label for="sf-model">الموديل</label>
                <select id="sf-model" name="model" class="sf-select" @disabled($models->isEmpty())
                    onchange="this.form.variant.value=''; this.form.submit()">
                    <option value="">{{ $models->isEmpty() ? 'اختار الماركة الأول' : 'كل الموديلات' }}</option>
                    @foreach ($models as $model)
                        <option value="{{ $model->id }}" @selected((string) ($filters['model'] ?? '') === (string) $model->id)>{{ $model->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sf-field">
                <label for="sf-variant">الفئة وسنة الصنع</label>
                <select id="sf-variant" name="variant" class="sf-select" @disabled($variants->isEmpty()) onchange="this.form.submit()">
                    <option value="">{{ $variants->isEmpty() ? 'اختار الموديل الأول' : 'كل الفئات' }}</option>
                    @foreach ($variants as $variant)
                        <option value="{{ $variant->id }}" @selected((string) ($filters['variant'] ?? '') === (string) $variant->id)>
                            {{ $variant->name }} ({{ $variant->year_from }}–{{ $variant->year_to ?? 'الآن' }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="sf-searchrow">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="sf-input"
                placeholder="رقم القطعة أو اسمها، مثال: 04465" aria-label="بحث برقم القطعة أو الاسم">
            <button type="submit" class="sf-btn">عرض القطع</button>
        </div>
    </form>

    <div class="sf-bar">
        <div>
            <p class="sf-count">
                {{ $products->total() }} قطعة@if ($carLabel) تركب على {{ $carLabel }}@endif
            </p>
            @if ($hasFilters)
                <a class="sf-clear" href="{{ $base }}">مسح الفلاتر</a>
            @endif
        </div>

        <nav class="sf-chips" aria-label="نوع القطعة">
            <a class="sf-chip" href="{{ request()->fullUrlWithQuery(['condition' => null, 'page' => null]) }}"
                @if (blank($filters['condition'] ?? null)) aria-current="true" @endif>الكل</a>
            @foreach ($conditions as $c)
                <a class="sf-chip" href="{{ request()->fullUrlWithQuery(['condition' => $c->value, 'page' => null]) }}"
                    @if (($filters['condition'] ?? null) === $c->value) aria-current="true" @endif>{{ $c->label() }}</a>
            @endforeach
        </nav>
    </div>

    <div class="sf-grid">
        @forelse ($products as $product)
            <article class="sf-card">
                <div class="sf-card-top">
                    <span class="sf-cond" data-k="{{ $product['condition_key'] }}">{{ $product['condition'] }}</span>
                    <span class="sf-stock {{ $product['in_stock'] ? 'is-in' : 'is-out' }}">
                        {{ $product['in_stock'] ? 'متوفر' : 'غير متوفر حاليًا' }}
                    </span>
                </div>

                <h2 class="sf-name">{{ $product['name'] }}</h2>

                @if ($product['part_number'] || $product['oem_number'])
                    <div class="sf-codes">
                        @if ($product['part_number'])
                            <span class="sf-code">{{ $product['part_number'] }}</span>
                        @endif
                        @if ($product['oem_number'] && $product['oem_number'] !== $product['part_number'])
                            <span class="sf-code">OEM {{ $product['oem_number'] }}</span>
                        @endif
                    </div>
                @endif

                @if ($product['brand'])
                    <p class="sf-brand-line">{{ $product['brand'] }}</p>
                @endif

                <div class="sf-foot">
                    @if ($product['price'] > 0)
                        <span class="sf-price">{{ $money($product['price']) }}<small>{{ $currency }}</small></span>
                    @else
                        <span class="sf-price is-ask">السعر عند السؤال</span>
                    @endif

                    <div class="sf-actions">
    @if ($product['in_stock'])
        <form method="POST" action="{{ route('storefront.cart.store', ['shopSlug' => $shop->slug]) }}">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product['id'] }}">
            <button type="submit" class="sf-add">أضف للسلة</button>
        </form>
    @endif

    @if ($whatsapp)
        @php
            $msg = 'السلام عليكم، عايز أسأل عن: '.$product['name']
                .($product['part_number'] ? ' ('.$product['part_number'].')' : '');
        @endphp
        <a class="sf-wa" target="_blank" rel="noopener"
            href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode($msg) }}">اسأل على واتساب</a>
    @endif
</div>
                </div>
            </article>
        @empty
            <div class="sf-empty">
                <strong>مفيش قطع مطابقة</strong>
                جرّب تشيل فلتر النوع، أو ابحث برقم القطعة بس.
                @if ($hasFilters)
                    <div style="margin-top:.75rem"><a href="{{ $base }}">عرض كل القطع</a></div>
                @endif
            </div>
        @endforelse
    </div>

    @if ($products->hasPages())
        <nav class="sf-pager" aria-label="الصفحات">
            @if ($products->onFirstPage())
                <span class="sf-page is-off">السابق</span>
            @else
                <a class="sf-page" href="{{ $products->previousPageUrl() }}">السابق</a>
            @endif

            <span class="sf-muted">صفحة {{ $products->currentPage() }} من {{ $products->lastPage() }}</span>

            @if ($products->hasMorePages())
                <a class="sf-page" href="{{ $products->nextPageUrl() }}">التالي</a>
            @else
                <span class="sf-page is-off">التالي</span>
            @endif
        </nav>
    @endif
</x-layouts::storefront>