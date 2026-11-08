
@php
    $base = route('storefront.index', ['shopSlug' => $shop->slug]);

    $currency = $shop->currency === 'EGP'
        ? __('lang.currency_egp')
        : $shop->currency;

    $money = fn (float $n): string => number_format(
        $n,
        floor($n) == $n ? 0 : 2
    );

    $price = (float) $product->retail_price;
    $inStock = (bool) ($inStock ?? false);
    $fitments = $fitments ?? collect();

    $msg = __('lang.whatsapp_product_message', [
        'product' => $product->name,
    ]);

    if ($product->part_number) {
        $msg .= ' (' . $product->part_number . ')';
    }

    $conditionLabel = $product->condition instanceof \BackedEnum
        ? $product->condition->value
        : $product->condition;

    $waUrl = $whatsapp
        ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($msg)
        : null;
@endphp

<x-layouts::storefront :shop="$shop">

    <main
        class="sf-product-page"
        dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
    >

        {{-- Breadcrumb --}}
        <nav class="sf-product-breadcrumb" aria-label="@lang('lang.breadcrumb')">
            <a href="{{ $base }}">
                @lang('lang.home')
            </a>

            <span aria-hidden="true">/</span>

            @if ($product->category)
                <span>{{ $product->category->name }}</span>
                <span aria-hidden="true">/</span>
            @endif

            <span class="is-current" aria-current="page">
                {{ $product->name }}
            </span>
        </nav>

        {{-- Product overview --}}
        <section class="sf-product-layout">

            <div class="sf-product-main">

                {{-- Product heading --}}
                <div class="sf-product-heading">

                    <div class="sf-product-badges">

                        <span class="sf-cond">
                            {{ $conditionLabel }}
                        </span>

                        <span class="sf-stock {{ $inStock ? 'is-in' : 'is-out' }}">
                            <span class="sf-stock-dot" aria-hidden="true"></span>

                            {{ $inStock ? __('lang.in_stock') : __('lang.out_of_stock') }}
                        </span>

                    </div>

                    <h1 class="sf-product-title">
                        {{ $product->name }}
                    </h1>

                    @if ($product->brand || $product->category)
                        <div class="sf-product-meta">

                            @if ($product->brand)
                                <span>
                                    @lang('lang.brand'):
                                    <strong>{{ $product->brand->name }}</strong>
                                </span>
                            @endif

                            @if ($product->category)
                                <span>
                                    @lang('lang.category'):
                                    <strong>{{ $product->category->name }}</strong>
                                </span>
                            @endif

                        </div>
                    @endif

                </div>

                {{-- Product identification --}}
                <section
                    class="sf-product-section"
                    aria-labelledby="sf-identification-title"
                >

                    <div class="sf-section-heading">
                        <div>
                            <h2 id="sf-identification-title">
                                @lang('lang.product_identification')
                            </h2>

                            <p>
                                @lang('lang.verify_part_numbers')
                            </p>
                        </div>
                    </div>

                    <div class="sf-product-identifiers">

                        @if ($product->part_number)
                            <div class="sf-identifier">
                                <span>@lang('lang.part_number')</span>
                                <strong dir="ltr">{{ $product->part_number }}</strong>
                            </div>
                        @endif

                        @if ($product->oem_number)
                            <div class="sf-identifier">
                                <span>@lang('lang.oem_number')</span>
                                <strong dir="ltr">{{ $product->oem_number }}</strong>
                            </div>
                        @endif

                        @if ($product->barcode)
                            <div class="sf-identifier">
                                <span>@lang('lang.barcode')</span>
                                <strong dir="ltr">{{ $product->barcode }}</strong>
                            </div>
                        @endif

                        @if (!$product->part_number && !$product->oem_number && !$product->barcode)
                            <p class="sf-product-muted">
                                @lang('lang.no_identification_numbers')
                            </p>
                        @endif

                    </div>

                </section>

                {{-- Description --}}
                @if ($product->notes)
                    <section
                        class="sf-product-section"
                        aria-labelledby="sf-description-title"
                    >
                        <div class="sf-section-heading">
                            <div>
                                <h2 id="sf-description-title">
                                    @lang('lang.product_details')
                                </h2>

                                <p>
                                    @lang('lang.additional_product_information')
                                </p>
                            </div>
                        </div>

                        <div class="sf-product-description">
                            {{ $product->notes }}
                        </div>
                    </section>
                @endif

            </div>

            {{-- Purchase panel --}}
            <aside
                class="sf-purchase-panel"
                aria-label="@lang('lang.purchase_details')"
            >

                <div class="sf-purchase-status">

                    <span class="sf-status-indicator {{ $inStock ? 'is-in' : 'is-out' }}"></span>

                    <span>
                        {{ $inStock ? __('lang.available_to_order') : __('lang.currently_unavailable') }}
                    </span>

                </div>

                <div class="sf-purchase-price">

                    <span class="sf-price-label">
                        @lang('lang.product_price')
                    </span>

                    @if ($price > 0)
                        <div class="sf-price-value">
                            <strong>{{ $money($price) }}</strong>
                            <small>{{ $currency }}</small>
                        </div>
                    @else
                        <strong class="sf-price-ask">
                            @lang('lang.price_on_request')
                        </strong>
                    @endif

                </div>

                <div class="sf-purchase-actions">

                    @if ($inStock)
                        <form
                            method="POST"
                            action="{{ route('storefront.cart.store', ['shopSlug' => $shop->slug]) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="product_id"
                                value="{{ $product->id }}"
                            >

                            <button type="submit" class="sf-add sf-add-large">
                                <span>@lang('lang.add_to_cart')</span>
                                <span aria-hidden="true">←</span>
                            </button>
                        </form>
                    @endif

                    @if ($waUrl)
                        <a
                            class="sf-wa sf-wa-large"
                            href="{{ $waUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            @lang('lang.ask_on_whatsapp')
                        </a>
                    @endif

                </div>

                <div class="sf-purchase-note">
                    <span aria-hidden="true">✓</span>

                    <p>
                        @lang('lang.check_compatibility_before_order')
                    </p>
                </div>

            </aside>

        </section>

        {{-- Vehicle compatibility --}}
        @if ($fitments->isNotEmpty())

            <section
                class="sf-fitments-section"
                aria-labelledby="sf-fitments-title"
            >

                <div class="sf-section-heading sf-fitments-heading">

                    <div>
                        <h2 id="sf-fitments-title">
                            @lang('lang.compatible_vehicles')
                        </h2>

                        <p>
                            @lang('lang.compatible_vehicles_description')
                        </p>
                    </div>

                    <span class="sf-fitments-count">
                        @lang('lang.fitments_count', ['count' => $fitments->count()])
                    </span>

                </div>

                <div class="sf-fitments-grid">

                    @foreach ($fitments as $fitment)
                        @php
                            $variant = $fitment->variant;
                            $vehicleModel = $variant?->vehicleModel;
                        @endphp

                        <article class="sf-fitment-card">

                            <div class="sf-fitment-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path
                                        d="M3 13.5L5.1 8.2A2 2 0 0 1 7 7h10a2 2 0 0 1 1.9 1.2l2.1 5.3v5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H6v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-5Z"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M4 13.5h16M7 16h.01M17 16h.01M6 10h12"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>

                            <div class="sf-fitment-info">

                                <h3>
                                    {{ $vehicleModel?->make?->name }}
                                    {{ $vehicleModel?->name }}
                                </h3>

                                @if ($variant?->name)
                                    <p>{{ $variant->name }}</p>
                                @endif

                                <span class="sf-fitment-years" dir="ltr">
                                    {{ $variant?->year_from ?? '—' }}
                                    –
                                    {{ $variant?->year_to ?? __('lang.present') }}
                                </span>

                            </div>

                            <span
                                class="sf-fitment-check"
                                aria-label="@lang('lang.compatible')"
                            >
                                ✓
                            </span>

                        </article>
                    @endforeach

                </div>

            </section>

        @else

            <section class="sf-fitments-empty">

                <h2>@lang('lang.vehicle_compatibility')</h2>

                <p>
                    @lang('lang.no_fitment_data')
                </p>

                @if ($waUrl)
                    <a
                        class="sf-wa"
                        href="{{ $waUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        @lang('lang.ask_about_compatibility')
                    </a>
                @endif

            </section>

        @endif

        {{-- Back navigation --}}
        <div class="sf-product-back">
            <a href="{{ $base }}">
                <span aria-hidden="true">→</span>
                @lang('lang.back_to_products')
            </a>
        </div>

    </main>

</x-layouts::storefront>
