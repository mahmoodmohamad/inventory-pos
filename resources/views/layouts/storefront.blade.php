@props(['shop', 'title' => null])

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $shop->name }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sf-bg: #F2F4F3;
            --sf-surface: #FFFFFF;
            --sf-ink: #16222B;
            --sf-muted: #5F6B76;
            --sf-line: #D8DEE2;
            --sf-petrol: #123B4A;
            --sf-petrol-soft: #BFD3DB;
            --sf-amber: #F0A81B;
            --sf-amber-ink: #2A1D00;
            --sf-ok: #18794E;
            --sf-bad: #B3321F;
            --sf-focus: #1F7AA8;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--sf-bg);
            color: var(--sf-ink);
            font-family: 'IBM Plex Sans Arabic', Tahoma, system-ui, sans-serif;
            font-size: 16px;
            line-height: 1.6;
        }

        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--sf-focus); outline-offset: 2px; }

        .sf-wrap { width: 100%; max-width: 72rem; margin-inline: auto; padding-inline: 1rem; }
        .sf-main { flex: 1; padding-bottom: 2rem; }

        /* Header */
        .sf-header { background: var(--sf-surface); border-bottom: 1px solid var(--sf-line); }
        .sf-header .sf-wrap { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 3.5rem; }
        .sf-brand { font-size: 1.125rem; font-weight: 700; text-decoration: none; }
        .sf-call { direction: ltr; unicode-bidi: isolate; font-size: .9375rem; font-weight: 600; text-decoration: none; color: var(--sf-petrol); }

        /* Car picker (the one bold element) */
        .sf-picker { margin-block: 1.25rem; padding: 1.25rem; border-radius: 1rem; background: var(--sf-petrol); color: #fff; }
        .sf-picker h1 { margin: 0 0 .25rem; font-size: 1.375rem; line-height: 1.35; }
        .sf-picker p { margin: 0 0 1rem; color: var(--sf-petrol-soft); font-size: .9375rem; }
        .sf-selects { display: grid; gap: .625rem; grid-template-columns: 1fr; }
        .sf-field { display: flex; flex-direction: column; gap: .25rem; }
        .sf-field label { font-size: .8125rem; color: var(--sf-petrol-soft); }
        .sf-select, .sf-input {
            width: 100%; height: 2.75rem; padding-inline: .75rem;
            border: 1px solid transparent; border-radius: .5rem;
            background: #fff; color: var(--sf-ink); font: inherit; font-size: .9375rem;
        }
        .sf-select:disabled { opacity: .55; }
        .sf-searchrow { display: flex; gap: .625rem; margin-top: .625rem; }
        .sf-searchrow .sf-input { flex: 1; min-width: 0; }
        .sf-btn {
            height: 2.75rem; padding-inline: 1.25rem; border: 0; border-radius: .5rem;
            background: var(--sf-amber); color: var(--sf-amber-ink);
            font: inherit; font-weight: 700; cursor: pointer; white-space: nowrap;
        }

        /* Results bar */
        .sf-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
        .sf-count { margin: 0; font-weight: 600; }
        .sf-clear { font-size: .875rem; color: var(--sf-muted); }
        .sf-chips { display: flex; flex-wrap: wrap; gap: .375rem; }
        .sf-chip {
            padding: .25rem .75rem; border: 1px solid var(--sf-line); border-radius: 999px;
            background: var(--sf-surface); font-size: .875rem; text-decoration: none;
        }
        .sf-chip[aria-current="true"] { background: var(--sf-petrol); border-color: var(--sf-petrol); color: #fff; }

        /* Product grid */
        .sf-grid { display: grid; gap: .875rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
        .sf-card {
            display: flex; flex-direction: column; gap: .5rem;
            padding: 1rem; border: 1px solid var(--sf-line); border-radius: .75rem; background: var(--sf-surface);
        }
        .sf-card-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .sf-cond { display: inline-flex; align-items: center; gap: .375rem; font-size: .8125rem; font-weight: 600; }
        .sf-cond::before { content: ""; width: .5rem; height: .5rem; border-radius: 50%; background: var(--c, #888); }
        .sf-cond[data-k="original"] { --c: #123B4A; }
        .sf-cond[data-k="commercial"] { --c: #C98A00; }
        .sf-cond[data-k="imported"] { --c: #3F6FB5; }
        .sf-cond[data-k="used"] { --c: #7A7F85; }
        .sf-stock { font-size: .8125rem; font-weight: 600; }
        .sf-stock.is-in { color: var(--sf-ok); }
        .sf-stock.is-out { color: var(--sf-bad); }
        .sf-name { margin: 0; font-size: 1.0625rem; font-weight: 600; line-height: 1.45; }
        .sf-codes { display: flex; flex-wrap: wrap; gap: .375rem; }
        .sf-code {
            direction: ltr; unicode-bidi: isolate;
            padding: .125rem .5rem; border-radius: .375rem; background: #EEF1F2;
            font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .8125rem;
        }
        .sf-brand-line { margin: 0; font-size: .875rem; color: var(--sf-muted); }
        .sf-foot {
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            margin-top: auto; padding-top: .75rem; border-top: 1px solid var(--sf-line);
        }
        .sf-price { font-size: 1.25rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .sf-price small { margin-inline-start: .25rem; font-size: .8125rem; font-weight: 500; color: var(--sf-muted); }
        .sf-price.is-ask { font-size: .9375rem; font-weight: 600; color: var(--sf-muted); }
        .sf-wa {
            display: inline-flex; align-items: center; height: 2.25rem; padding-inline: .875rem;
            border: 1px solid var(--sf-ok); border-radius: .5rem;
            color: var(--sf-ok); font-size: .875rem; font-weight: 600; text-decoration: none; white-space: nowrap;
        }

        .sf-empty {
            grid-column: 1 / -1; padding: 2.5rem 1rem; text-align: center;
            border: 1px dashed var(--sf-line); border-radius: .75rem; color: var(--sf-muted);
        }
        .sf-empty strong { display: block; margin-bottom: .25rem; color: var(--sf-ink); }

        /* Pager */
        .sf-pager { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; }
        .sf-page {
            padding: .375rem 1rem; border: 1px solid var(--sf-line); border-radius: .5rem;
            background: var(--sf-surface); text-decoration: none; font-size: .9375rem;
        }
        .sf-page.is-off { opacity: .45; }
        .sf-muted { color: var(--sf-muted); font-size: .875rem; }

        .sf-footer { padding-block: 1.25rem; border-top: 1px solid var(--sf-line); color: var(--sf-muted); font-size: .875rem; }

        @media (min-width: 720px) {
            .sf-picker { padding: 1.5rem; }
            .sf-selects { grid-template-columns: repeat(3, 1fr); }
        }
        .sf-cartlink { font-weight: 600; text-decoration: none; color: var(--sf-petrol); }
.sf-badge { display: inline-block; min-width: 1.25rem; margin-inline-start: .25rem; padding: 0 .35rem; border-radius: 999px; background: var(--sf-amber); color: var(--sf-amber-ink); font-size: .75rem; text-align: center; }
.sf-flash { margin-block: 1rem; padding: .625rem .875rem; border-radius: .5rem; background: #E3F1EA; color: var(--sf-ok); font-weight: 600; }
.sf-add { height: 2.25rem; padding-inline: .875rem; border: 0; border-radius: .5rem; background: var(--sf-petrol); color: #fff; font: inherit; font-size: .875rem; font-weight: 600; cursor: pointer; }
.sf-actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.sf-cart-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .875rem 0; border-top: 1px solid var(--sf-line); }
.sf-cart-row:first-child { border-top: 0; }
.sf-qty { width: 4.5rem; height: 2.25rem; padding-inline: .5rem; border: 1px solid var(--sf-line); border-radius: .5rem; font: inherit; }
.sf-total { display: flex; justify-content: space-between; padding-top: 1rem; border-top: 2px solid var(--sf-ink); font-size: 1.125rem; font-weight: 700; }
.sf-link-btn { background: none; border: 0; padding: 0; color: var(--sf-bad); font: inherit; font-size: .875rem; cursor: pointer; text-decoration: underline; }
    </style>
</head>
<body>
    <header class="sf-header">
        <div class="sf-wrap">
            <a class="sf-brand" href="{{ route('storefront.index', ['shopSlug' => $shop->slug]) }}">{{ $shop->name }}</a>

            @if ($shop->phone)
                <a class="sf-call" href="tel:{{ $shop->phone }}">{{ $shop->phone }}</a>
            @endif
            @php $cartCount = app(\App\Services\Storefront\StorefrontCart::class)->count($shop); @endphp
<a class="sf-cartlink" href="{{ route('storefront.cart.show', ['shopSlug' => $shop->slug]) }}">
    السلة @if ($cartCount > 0)<span class="sf-badge">{{ $cartCount }}</span>@endif
</a>
        </div>
    </header>

    <main class="sf-main">
        @if (session('status'))
    <div class="sf-flash" role="status">{{ session('status') }}</div>
@endif
        <div class="sf-wrap">
            {{ $slot }}
        </div>
    </main>

    @if ($shop->address)
        <footer class="sf-footer">
            <div class="sf-wrap">{{ $shop->address }}</div>
        </footer>
    @endif
</body>
</html>