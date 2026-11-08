@props(['shop' => null, 'title' => null])

@php
    $locale = in_array(app()->getLocale(), ['ar', 'ar-EG'], true)
        ? 'ar'
        : 'en';

    $isRtl = $locale === 'ar';
    $direction = $isRtl ? 'rtl' : 'ltr';

    $pageTitle = $title
        ?? ($shop?->name ?? config('app.name'));

    $description = $shop?->name ?? config('app.name');

    $cartCount = $shop
        ? app(\App\Services\Storefront\StorefrontCart::class)->count($shop)
        : 0;

    $cssPath = public_path('assets/css/storefront.css');

    $cssVersion = is_file($cssPath)
        ? filemtime($cssPath)
        : 1;
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="theme-color" content="#0f3442">

    <title>{{ $pageTitle }}</title>

    <meta name="description" content="{{ $description }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>

    @if ($isRtl)
        <link
            href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700,800&display=swap"
            rel="stylesheet"
        >
    @else
        <link
            href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap"
            rel="stylesheet"
        >
    @endif

    {{-- Main Stylesheet --}}

<link
    rel="stylesheet"
    href="{{ asset('assets/css/storefront.css') }}?v={{ $cssVersion }}"
>

@if (app()->getLocale() === 'en')
    <link
        rel="stylesheet"
        href="{{ asset('assets/css/en.css') }}?v={{ file_exists(public_path('assets/css/en.css')) ? filemtime(public_path('assets/css/en.css')) : time() }}"
    >
@endif

    {{-- Additional Page Styles --}}
    @stack('styles')
</head>

<body class="sf-body {{ $isRtl ? 'sf-rtl' : 'sf-ltr' }}" dir="{{ $direction }}">

    {{-- Accessibility --}}
    <a class="sf-skip" href="#content">
        {{ $isRtl ? 'تخطي إلى المحتوى' : 'Skip to content' }}
    </a>

    {{-- Header --}}
    @include('layouts.partials.storefront-header', [
        'shop' => $shop,
        'cartCount' => $cartCount,
    ])

    {{-- Main Content --}}
    <main id="content" class="sf-main">

        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="sf-wrap">
                <div class="sf-flash" role="status">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        <div class="sf-wrap">
            {{ $slot }}
        </div>

    </main>

    {{-- Footer --}}
    @if ($shop && ($shop->address || $shop->phone))
        <footer class="sf-footer">
            <div class="sf-wrap sf-footer-inner">

                <span>
                    <strong>{{ $shop->name }}</strong>

                    @if ($shop->address)
                        — {{ $shop->address }}
                    @endif
                </span>

                @if ($shop->phone)
                    <a
                        href="tel:{{ preg_replace('/[^0-9+]/', '', $shop->phone) }}"
                        dir="ltr"
                    >
                        {{ $shop->phone }}
                    </a>
                @endif

            </div>
        </footer>
    @endif

    {{-- Additional Page Scripts --}}
    @stack('scripts')

</body>
</html>