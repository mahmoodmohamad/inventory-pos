@props(['shop', 'title' => null])

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
            </div>
        @endif

        <div class="sf-wrap">
            {{ $slot }}
        </div>

    </main>

    {{-- Footer --}}
    @if ($shop->address)
        <footer class="sf-footer">
            <div class="sf-wrap">
                {{ $shop->address }}
            </div>
        </footer>
    @endif

    @stack('scripts')

</body>
</html>