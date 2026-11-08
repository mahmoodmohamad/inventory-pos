@props(['shop', 'title' => null])
@php
    $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $cartCount = app(\App\Services\Storefront\StorefrontCart::class)->count($shop);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $shop->name }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700|inter:400,500,600,700,800&display=swap" rel="stylesheet">
    {{-- add both to vite.config input[]: shared first, locale second --}}
    @vite(['resources/css/storefront.css', "resources/css/{$locale}.css"])
</head>
<body class="sf-body">
    <header class="sf-header">
        <div class="sf-wrap sf-header-inner">
            <a class="sf-brand" href="{{ route('storefront.index', ['shopSlug' => $shop->slug]) }}">
                <span class="sf-brand-name">{{ $shop->name }}</span>
            </a>

            <div class="sf-header-actions">
                @if ($shop->phone)
                    <a class="sf-call" href="tel:{{ $shop->phone }}"><span class="sf-call-num">{{ $shop->phone }}</span></a>
                @endif
                <a class="sf-cartlink" href="{{ route('storefront.cart.show', ['shopSlug' => $shop->slug]) }}">
                    {{ $locale === 'ar' ? 'السلة' : 'Cart' }}
                    @if ($cartCount > 0)<span class="sf-badge">{{ $cartCount }}</span>@endif
                </a>
            </div>
        </div>
    </header>

    <main class="sf-main">
        <div class="sf-wrap">
            @if (session('status'))
                <div class="sf-flash" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>

    @if ($shop->address)
        <footer class="sf-footer"><div class="sf-wrap">{{ $shop->address }}</div></footer>
    @endif
</body>
</html>