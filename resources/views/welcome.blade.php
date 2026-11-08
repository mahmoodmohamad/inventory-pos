@php
    // app()->getLocale() يتظبط من الـ route (ar | en)، والـ layout بيختار ar.css / en.css منه.
    $lang = app()->getLocale() === 'ar' ? 'ar' : 'en';
    $rtl = $lang === 'ar';
    $other = $rtl ? 'en' : 'ar';
    $demoUrl = route('storefront.index', ['shopSlug' => $shop->slug]);
@endphp

<x-layouts::storefront :shop="$shop" :title="$shop->name.' — '.$t['h1']">
    <div class="lp">

        {{-- Anchor nav + language switch (reuses storefront chip styles) --}}
        <div class="lp-top">
            <nav class="sf-chips" aria-label="{{ $rtl ? 'أقسام الصفحة' : 'Page sections' }}">
                @foreach ($t['nav'] as $id => $label)
                    <a class="sf-chip" href="#{{ $id }}">{{ $label }}</a>
                @endforeach
            </nav>
            <a class="lp-lang" href="{{ route('home', ['lang' => $other]) }}" hreflang="{{ $other }}" lang="{{ $other }}">{{ $t['switch'] }}</a>
        </div>

        {{-- Hero --}}
        <section class="lp-hero">
            <div>
                <h1>{{ $t['h1'] }}</h1>
                <p class="lp-lead">{{ $t['sub'] }}</p>
                <div class="lp-cta">
                    @auth
                        <a class="lp-btn lp-amber" href="{{ route('dashboard') }}">{{ $t['dash'] }}</a>
                    @else
                        @if (Route::has('register'))<a class="lp-btn lp-amber" href="{{ route('register') }}">{{ $t['register'] }}</a>@endif
                        @if (Route::has('login'))<a class="lp-btn lp-ghost" href="{{ route('login') }}">{{ $t['login'] }}</a>@endif
                    @endauth
                    <a class="lp-btn lp-ghost" href="{{ $demoUrl }}">{{ $t['cta2'] }}</a>
                </div>
                <p class="lp-note">{{ $t['note'] }}</p>
            </div>

            <div class="lp-mock" role="img" aria-label="{{ $t['mock_title'] }}: {{ $t['mock_count'] }}">
                <h2>{{ $t['mock_title'] }}</h2>
                <div class="lp-sel" aria-hidden="true">
                    @foreach ($t['mock'] as $v)<div>{{ $v }}</div>@endforeach
                </div>
                <p class="lp-count" aria-hidden="true">{{ $t['mock_count'] }}</p>
                @foreach ($t['parts'] as [$name, $cond, $stock, $in, $price])
                    <div class="lp-part" aria-hidden="true">
                        <div>
                            <b>{{ $name }}</b>
                            <small><span class="sf-cond" data-k="{{ $cond === 'Imported' || $cond === 'مستورد' ? 'imported' : 'original' }}">{{ $cond }}</span>
                                <span class="sf-stock {{ $in ? 'is-in' : 'is-out' }}">{{ $stock }}</span></small>
                        </div>
                        <span class="sf-price"><span dir="ltr">{{ $price }}</span><small>{{ $t['cur'] }}</small></span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Trust facts --}}
        <div class="lp-facts">
            @foreach ($t['facts'] as [$h, $d])
                <div><b>{{ $h }}</b><span>{{ $d }}</span></div>
            @endforeach
        </div>

        {{-- Features --}}
        <section id="features">
            <h2 class="lp-h">{{ $t['f_title'] }}</h2>
            <p class="lp-sub">{{ $t['f_sub'] }}</p>
            <div class="lp-grid">
                @foreach ($t['features'] as [$h, $d])
                    <article class="sf-card lp-feat">
                        <h3>{{ $h }}</h3>
                        <p>{{ $d }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- How it works --}}
        <section id="how">
            <h2 class="lp-h" style="margin-bottom:var(--sf-space-5)">{{ $t['h_title'] }}</h2>
            <ol class="lp-steps">
                @foreach ($t['steps'] as [$h, $d])
                    <li><h3>{{ $h }}</h3><p>{{ $d }}</p></li>
                @endforeach
            </ol>
        </section>

        {{-- Storefront --}}
        <section id="store" class="lp-store">
            <div>
                <h2 class="lp-h">{{ $t['s_title'] }}</h2>
                <p class="lp-sub" style="margin-bottom:var(--sf-space-4)">{{ $t['s_sub'] }}</p>
                <ul class="lp-pts">
                    @foreach ($t['s_points'] as $p)<li>{{ $p }}</li>@endforeach
                </ul>
            </div>
            <div class="lp-vis" aria-hidden="true">
                @foreach ($t['vis'] as [$label, $shown])
                    <div><span>{{ $label }}</span><span class="{{ $shown ? 'is-on' : 'is-off' }}">{{ $shown ? $t['on'] : $t['off'] }}</span></div>
                @endforeach
            </div>
        </section>

        {{-- FAQ --}}
        <section id="faq" class="lp-faq">
            <h2 class="lp-h" style="margin-bottom:var(--sf-space-5)">{{ $t['faq_title'] }}</h2>
            @foreach ($t['faq'] as [$q, $a])
                <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
            @endforeach
        </section>

        {{-- Final CTA --}}
        <section class="lp-final">
            <h2>{{ $t['final'] }}</h2>
            <p>{{ $t['final_sub'] }}</p>
            @auth
                <a class="lp-btn lp-amber" href="{{ route('dashboard') }}">{{ $t['dash'] }}</a>
            @elseif (Route::has('register'))
                <a class="lp-btn lp-amber" href="{{ route('register') }}">{{ $t['register'] }}</a>
            @endauth
        </section>
    </div>
</x-layouts::storefront>