
@php
    $locale = app()->getLocale();
    $isRtl = in_array($locale, ['ar', 'ar-EG'], true);
@endphp

<header class="sf-header">
    <div class="sf-wrap">

        <div class="sf-header-inner">

            {{-- Brand --}}
            <a
                class="sf-brand"
                href="{{ route('storefront.index', ['shopSlug' => $shop->slug]) }}"
                aria-label="{{ $shop->name }}"
            >
                <span class="sf-brand-name">{{ $shop->name }}</span>
            </a>

            {{-- Header Actions --}}
            <nav class="sf-header-actions"
                 aria-label="{{ __('storefront.navigation') }}">

                {{-- Phone --}}
                @if ($shop->phone)
                    <a
                        class="sf-header-contact"
                        href="tel:{{ preg_replace('/[^0-9+]/', '', $shop->phone) }}"
                    >
                        <span class="sf-header-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2
                                19.79 19.79 0 0 1-8.63-3.07
                                19.5 19.5 0 0 1-6-6
                                A19.79 19.79 0 0 1 2.12 4.18
                                2 2 0 0 1 4.11 2h3
                                a2 2 0 0 1 2 1.72
                                c.12.96.36 1.9.7 2.8
                                a2 2 0 0 1-.45 2.11L8.09 9.91
                                a16 16 0 0 0 6 6l1.27-1.27
                                a2 2 0 0 1 2.11-.45
                                c.9.34 1.84.58 2.8.7
                                A2 2 0 0 1 22 16.92Z"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"/>
                            </svg>
                        </span>

                        <span class="sf-header-contact-text">
                            <small>{{ __('storefront.call_us') }}</small>
                            <strong dir="ltr">{{ $shop->phone }}</strong>
                        </span>
                    </a>
                @endif

                {{-- Language Switcher --}}
                <div class="sf-language-switcher"
                     aria-label="{{ __('storefront.language') }}">

                    <a
                        href="{{ route('language.switch', ['locale' => 'ar']) }}"
                        lang="ar"
                        hreflang="ar"
                        @if ($isRtl) aria-current="true" @endif
                        class="{{ $isRtl ? 'is-active' : '' }}"
                    >
                        العربية
                    </a>

                    <span class="sf-language-divider" aria-hidden="true"></span>

                    <a
                        href="{{ route('language.switch', ['locale' => 'en']) }}"
                        lang="en"
                        hreflang="en"
                        @if (!$isRtl) aria-current="true" @endif
                        class="{{ !$isRtl ? 'is-active' : '' }}"
                    >
                        English
                    </a>
                </div>

                {{-- Cart --}}
                <a
                    class="sf-cartlink"
                    href="{{ route('storefront.cart.show', ['shopSlug' => $shop->slug]) }}"
                    aria-label="{{ __('storefront.shopping_cart') }} ({{ $cartCount }})"
                >
                    <span class="sf-cart-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M3 3h2l2.4 12.2a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 8H6"
                                  stroke="currentColor"
                                  stroke-width="1.7"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"/>
                            <circle cx="10" cy="21" r="1" fill="currentColor"/>
                            <circle cx="19" cy="21" r="1" fill="currentColor"/>
                        </svg>
                    </span>

                    <span class="sf-cart-label">
                        {{ __('storefront.cart') }}
                    </span>

                    @if ($cartCount > 0)
                        <span class="sf-badge">{{ $cartCount }}</span>
                    @endif
                </a>

            </nav>
        </div>
    </div>
</header>
