@props(['shop' => null, 'title' => null])

@php
    $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
    $isRtl = $locale === 'ar';
    $cartCount = $shop ? app(\App\Services\Storefront\StorefrontCart::class)->count($shop) : 0;
    $pageTitle = $title ?? ($shop?->name ?? config('app.name'));
    $cssPath = public_path('assets/css/storefront.css');
    $cssVersion = is_file($cssPath) ? filemtime($cssPath) : 1;
@endphp

@php
    $locale = app()->getLocale();
    $isRtl  = in_array($locale, ['ar', 'ar-EG'], true);
    $dirCss = $isRtl ? 'ar.css' : 'en.css';

    $cartCount = app(\App\Services\Storefront\StorefrontCart::class)->count($shop);
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<<<<<<< HEAD
    <meta name="theme-color" content="#0f3442">

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $shop?->name ?? config('app.name') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    @if ($isRtl)
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700,800&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">
    @endif

    <link rel="stylesheet" href="{{ asset('assets/css/storefront.css') }}?v={{ $cssVersion }}">
    @stack('styles')
</head>

<body class="sf-body">
    <a class="sf-skip" href="#content">{{ $isRtl ? 'تخطي إلى المحتوى' : 'Skip to content' }}</a>

    @include('layouts.partials.storefront-header', ['shop' => $shop, 'cartCount' => $cartCount])

    <main id="content" class="sf-main">
        @if (session('status'))
            <div class="sf-wrap">
                <div class="sf-flash" role="status">{{ session('status') }}</div>
=======

    <title>{{ $title ?? $shop->name }}</title>

    <meta name="description" content="{{ $shop->name }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">

    @if ($isRtl)
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @endif

    {{-- Main Stylesheet — direction-aware --}}
    @if ($isRtl)
        <link rel="stylesheet" href="{{ asset('assets/css/ar.css') }}">
    @else
        <link rel="stylesheet" href="{{ asset('assets/css/en.css') }}">
    @endif

    @stack('styles')
</head>

<body
    class="sf-body {{ $isRtl ? 'sf-rtl' : 'sf-ltr' }}"
    dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
>

    {{-- Header --}}
    <header class="sf-header">
        @include('layouts.partials.storefront-header', [
            'shop' => $shop,
            'cartCount' => $cartCount,
        ])
    </header>

    {{-- Main Content --}}
    <main class="sf-main">

        @if (session('status'))
            <div class="sf-wrap">
                <div class="sf-flash" role="status">
                    {{ session('status') }}
                </div>
>>>>>>> 9dc89c6f7cc72fd12b6dc3d659c6145f1d61a51b
            </div>
        @endif

        <div class="sf-wrap">
            {{ $slot }}
        </div>

    </main>

<<<<<<< HEAD
    @if ($shop && ($shop->address || $shop->phone))
        <footer class="sf-footer">
            <div class="sf-wrap sf-footer-inner">
                <span><strong>{{ $shop->name }}</strong>@if ($shop->address) — {{ $shop->address }}@endif</span>
                @if ($shop->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop->phone) }}" dir="ltr">{{ $shop->phone }}</a>
                @endif
=======
    {{-- Footer --}}
    @if ($shop->address)
        <footer class="sf-footer">
            <div class="sf-wrap">
                {{ $shop->address }}
>>>>>>> 9dc89c6f7cc72fd12b6dc3d659c6145f1d61a51b
            </div>
        </footer>
    @endif

    @stack('scripts')
<<<<<<< HEAD
=======

>>>>>>> 9dc89c6f7cc72fd12b6dc3d659c6145f1d61a51b
</body>
</html>
