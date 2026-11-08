
@php
    $base = route('storefront.index', ['shopSlug' => $shop->slug]);
    $currency = $shop->currency === 'EGP' ? __('lang.currency_egp') : $shop->currency;

    $hasFilters = filled($filters['q'] ?? null)
        || filled($filters['make'] ?? null)
        || filled($filters['condition'] ?? null);

    $money = fn (float $n): string => number_format($n, floor($n) == $n ? 0 : 2);
    $without = fn (array $keys) => request()->fullUrlWithoutQuery([...$keys, 'page']);

    $products->withQueryString();
@endphp

<x-layouts::storefront :shop="$shop">

    {{-- Car picker --}}
    <form method="GET" action="{{ $base }}" class="sf-picker" role="search">

        <h1>@lang('lang.choose_car')</h1>
        <p>@lang('lang.choose_car_description')</p>

        @if (filled($filters['condition'] ?? null))
            <input type="hidden" name="condition" value="{{ $filters['condition'] }}">
        @endif

        <div class="sf-selects">

            <div class="sf-field">
                <label for="sf-make">
                    <span class="sf-step">1</span>
                    @lang('lang.make')
                </label>

                <select
                    id="sf-make"
                    name="make"
                    class="sf-select"
                    onchange="this.form.model.value=''; this.form.variant.value=''; this.form.submit()"
                >
                    <option value="">@lang('lang.all_makes')</option>

                    @foreach ($makes as $make)
                        <option
                            value="{{ $make->id }}"
                            @selected((string) ($filters['make'] ?? '') === (string) $make->id)
                        >
                            {{ $make->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sf-field">
                <label for="sf-model">
                    <span class="sf-step">2</span>
                    @lang('lang.model')
                </label>

                <select
                    id="sf-model"
                    name="model"
                    class="sf-select"
                    @disabled($models->isEmpty())
                    onchange="this.form.variant.value=''; this.form.submit()"
                >
                    <option value="">
                        {{ $models->isEmpty() ? __('lang.select_make_first') : __('lang.all_models') }}
                    </option>

                    @foreach ($models as $model)
                        <option
                            value="{{ $model->id }}"
                            @selected((string) ($filters['model'] ?? '') === (string) $model->id)
                        >
                            {{ $model->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sf-field">
                <label for="sf-variant">
                    <span class="sf-step">3</span>
                    @lang('lang.variant_year')
                </label>

                <select
                    id="sf-variant"
                    name="variant"
                    class="sf-select"
                    @disabled($variants->isEmpty())
                    onchange="this.form.submit()"
                >
                    <option value="">
                        {{ $variants->isEmpty() ? __('lang.select_model_first') : __('lang.all_variants') }}
                    </option>

                    @foreach ($variants as $variant)
                        <option
                            value="{{ $variant->id }}"
                            @selected((string) ($filters['variant'] ?? '') === (string) $variant->id)
                        >
                            {{ $variant->name }}
                            ({{ $variant->year_from }}–{{ $variant->year_to ?? __('lang.present') }})
                        </option>
                    @endforeach
                </select>
            </div>

        </div>

        <div class="sf-searchrow">
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                class="sf-input"
                placeholder="@lang('lang.search_placeholder')"
                aria-label="@lang('lang.search_aria_label')"
            >

            <button type="submit" class="sf-btn">
                @lang('lang.show_products')
            </button>
        </div>
    </form>

    {{-- Results bar --}}
    <div class="sf-bar">

        <div>
            <p class="sf-count">
                @lang('lang.products_count', ['count' => $products->total()])

                @if ($carLabel)
                    @lang('lang.fits_on_car', ['car' => $carLabel])
                @endif
            </p>

            @if ($hasFilters)
                <div class="sf-active">

                    @if ($carLabel)
                        <a class="sf-tag" href="{{ $without(['make', 'model', 'variant']) }}">
                            {{ $carLabel }}
                            <span aria-hidden="true">×</span>
                            <span class="sf-sr">@lang('lang.remove_car_filter')</span>
                        </a>
                    @endif

                    @if (filled($filters['q'] ?? null))
                        <a class="sf-tag" href="{{ $without(['q']) }}">
                            <bdi>{{ $filters['q'] }}</bdi>
                            <span aria-hidden="true">×</span>
                            <span class="sf-sr">@lang('lang.remove_search_filter')</span>
                        </a>
                    @endif

                    <a class="sf-clear" href="{{ $base }}">
                        @lang('lang.clear_all')
                    </a>
                </div>
            @endif
        </div>

        <nav class="sf-chips" aria-label="@lang('lang.product_condition')">

            <a
                class="sf-chip"
                href="{{ request()->fullUrlWithQuery(['condition' => null, 'page' => null]) }}"
                @if (blank($filters['condition'] ?? null)) aria-current="true" @endif
            >
                @lang('lang.all')
            </a>

            @foreach ($conditions as $c)
                <a
                    class="sf-chip"
                    href="{{ request()->fullUrlWithQuery(['condition' => $c->value, 'page' => null]) }}"
                    @if (($filters['condition'] ?? null) === $c->value) aria-current="true" @endif
                >
                    {{ $c->label() }}
                </a>
            @endforeach

        </nav>
    </div>

    {{-- Products grid --}}
    <div class="sf-grid">

        @forelse ($products as $product)

            <article class="sf-card" data-k="{{ $product['condition_key'] }}">

                <div class="sf-card-top">

                    <span class="sf-cond" data-k="{{ $product['condition_key'] }}">
                        {{ $product['condition'] }}
                    </span>

                    <span class="sf-stock {{ $product['in_stock'] ? 'is-in' : 'is-out' }}">
                        <span class="sf-stock-dot" aria-hidden="true"></span>

                        {{ $product['in_stock'] ? __('lang.in_stock') : __('lang.out_of_stock') }}
                    </span>

                </div>

                <h2 class="sf-name">
                    <a href="{{ route('storefront.products.show', [
                        'shopSlug' => $shop->slug,
                        'productId' => $product['id'],
                    ]) }}">
                        {{ $product['name'] }}
                    </a>
                </h2>

                @if ($product['part_number'] || $product['oem_number'])
                    <div class="sf-codes">

                        @if ($product['part_number'])
                            <span class="sf-code">{{ $product['part_number'] }}</span>
                        @endif

                        @if ($product['oem_number'] && $product['oem_number'] !== $product['part_number'])
                            <span class="sf-code">
                                OEM {{ $product['oem_number'] }}
                            </span>
                        @endif

                    </div>
                @endif

                @if ($product['brand'])
                    <p class="sf-brand-line">{{ $product['brand'] }}</p>
                @endif

                @if ($carLabel)
                    <span class="sf-fits">
                        ✓ @lang('lang.fits_on_car', ['car' => $carLabel])
                    </span>
                @endif

                <div class="sf-foot">

                    @if ($product['price'] > 0)
                        <span class="sf-price">
                            {{ $money($product['price']) }}
                            <small>{{ $currency }}</small>
                        </span>
                    @else
                        <span class="sf-price is-ask">
                            @lang('lang.price_on_request')
                        </span>
                    @endif

                    <div class="sf-actions">

                        @if ($product['in_stock'])
                            <form
                                method="POST"
                                action="{{ route('storefront.cart.store', ['shopSlug' => $shop->slug]) }}"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="{{ $product['id'] }}"
                                >

                                <button type="submit" class="sf-add">
                                    @lang('lang.add_to_cart')
                                </button>
                            </form>
                        @endif

                        @if ($whatsapp)
                            @php
                                $msg = __('lang.whatsapp_product_message', [
                                    'product' => $product['name'],
                                    'part_number' => $product['part_number'] ?? '',
                                ]);
                            @endphp

                            <a
                                class="sf-wa"
                                target="_blank"
                                rel="noopener noreferrer"
                                href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode($msg) }}"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.7-5.2A8.5 8.5 0 1 1 21 11.5Z"/>
                                </svg>

                                @lang('lang.whatsapp')
                            </a>
                        @endif

                    </div>
                </div>
            </article>

        @empty

            <div class="sf-empty">
                <strong>@lang('lang.no_matching_products')</strong>

                @lang('lang.no_matching_products_hint')

                @if ($hasFilters)
                    <div style="margin-block-start:.75rem">
                        <a href="{{ $base }}">
                            @lang('lang.show_all_products')
                        </a>
                    </div>
                @endif
            </div>

        @endforelse

    </div>

    {{-- Pagination --}}
    @if ($products->hasPages())
        <nav class="sf-pager" aria-label="@lang('lang.pagination')">

            @if ($products->onFirstPage())
                <span class="sf-page is-off">
                    @lang('lang.previous')
                </span>
            @else
                <a class="sf-page" href="{{ $products->previousPageUrl() }}" rel="prev">
                    @lang('lang.previous')
                </a>
            @endif

            <span class="sf-muted">
                @lang('lang.page_of', [
                    'current' => $products->currentPage(),
                    'last' => $products->lastPage(),
                ])
            </span>

            @if ($products->hasMorePages())
                <a class="sf-page" href="{{ $products->nextPageUrl() }}" rel="next">
                    @lang('lang.next')
                </a>
            @else
                <span class="sf-page is-off">
                    @lang('lang.next')
                </span>
            @endif

        </nav>
    @endif

</x-layouts::storefront>
