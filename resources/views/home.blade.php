
@php
    $lang = app()->getLocale() === 'ar' ? 'ar' : 'en';
    $rtl = $lang === 'ar';
    $other = $rtl ? 'en' : 'ar';
    $demoUrl = route('storefront.index', ['shopSlug' => $shop->slug]);

    $hasRegister = Route::has('register');
    $hasLogin = Route::has('login');

    $primaryUrl = auth()->check()
        ? route('dashboard')
        : ($hasRegister
            ? route('register')
            : ($hasLogin ? route('login') : $demoUrl));

    $primaryLabel = auth()->check()
        ? __('lang.dash')
        : ($hasRegister
            ? __('lang.register')
            : ($hasLogin ? __('lang.login') : __('lang.cta2')));
@endphp

<x-layouts::storefront :shop="$shop" :title="$shop->name.' — '.__('lang.h1')">

    <div class="lp lp-advanced" dir="{{ $rtl ? 'rtl' : 'ltr' }}">


        <div id="main">

            {{-- Hero --}}
            <section class="lp-hero lp-hero-advanced">

                <div class="lp-hero-content">

                    <div class="lp-eyebrow">
                        <span class="lp-live-dot"></span>
                        {{ __('lang.note') }}
                    </div>

                    <h1>{{ __('lang.h1') }}</h1>

                    <p class="lp-lead">
                        {{ __('lang.sub') }}
                    </p>

                    <div class="lp-cta">

                        @auth
                            <a class="lp-btn lp-amber lp-btn-primary"
                               href="{{ route('dashboard') }}">
                                {{ __('lang.dash') }}
                                <span aria-hidden="true">↗</span>
                            </a>
                        @else

                            @if ($hasRegister)
                                <a class="lp-btn lp-amber lp-btn-primary"
                                   href="{{ route('register') }}">
                                    {{ __('lang.register') }}
                                    <span aria-hidden="true">↗</span>
                                </a>
                            @endif

                            @if ($hasLogin)
                                <a class="lp-btn lp-ghost"
                                   href="{{ route('login') }}">
                                    {{ __('lang.login') }}
                                </a>
                            @endif

                        @endauth

                        <a class="lp-btn lp-ghost"
                           href="{{ $demoUrl }}">
                            {{ __('lang.cta2') }}
                            <span aria-hidden="true">→</span>
                        </a>

                    </div>

                    <div class="lp-hero-meta">
                        <span>✓ {{ __('lang.facts.0.0') }}</span>
                        <span>✓ {{ __('lang.facts.1.0') }}</span>
                    </div>

                </div>

                {{-- Product Preview --}}
                <div class="lp-preview-wrapper">

                    <div class="lp-preview-decoration lp-decoration-one"></div>
                    <div class="lp-preview-decoration lp-decoration-two"></div>

                    <div class="lp-mock lp-mock-advanced">

                        <div class="lp-mock-header">
                            <div>
                                <span class="lp-mock-indicator"></span>
                                <span>{{ __('lang.mock_title') }}</span>
                            </div>

                            <span class="lp-mock-dots">•••</span>
                        </div>

                        <div class="lp-mock-content">

                            <div class="lp-sel">
                                @foreach (__('lang.mock') as $v)
                                    <div>{{ $v }}</div>
                                @endforeach
                            </div>

                            <div class="lp-mock-summary">
                                <span>{{ __('lang.mock_count') }}</span>
                                <span class="lp-summary-icon">✦</span>
                            </div>

                            <div class="lp-mock-products">

                                @foreach (__('lang.parts') as $index => [$name, $cond, $stock, $in, $price])

                                    <article class="lp-part"
                                             style="--item-index: {{ $index }}">

                                        <div class="lp-part-info">
                                            <b>{{ $name }}</b>

                                            <small>
                                                <span class="sf-cond"
                                                      data-k="{{ $cond === 'Imported' || $cond === 'مستورد' ? 'imported' : 'original' }}">
                                                    {{ $cond }}
                                                </span>

                                                <span class="sf-stock {{ $in ? 'is-in' : 'is-out' }}">
                                                    {{ $stock }}
                                                </span>
                                            </small>
                                        </div>

                                        <span class="sf-price">
                                            <span dir="ltr">{{ $price }}</span>
                                            <small>{{ __('lang.cur') }}</small>
                                        </span>

                                    </article>

                                @endforeach

                            </div>

                        </div>

                        <div class="lp-mock-footer">
                            <span class="lp-footer-check">✓</span>
                            <span>{{ __('lang.mock_title') }}</span>
                        </div>

                    </div>

                </div>

            </section>

            {{-- Trust facts --}}
            <section class="lp-facts lp-facts-advanced"
                     aria-label="{{ __('lang.platform_highlights') }}">

                @foreach (__('lang.facts') as $index => [$h, $d])

                    <article class="lp-fact">

                        <span class="lp-fact-number">
                            {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>

                        <div>
                            <b>{{ $h }}</b>
                            <span>{{ $d }}</span>
                        </div>

                    </article>

                @endforeach

            </section>

            {{-- Features --}}
            <section id="features" class="lp-section">

                <div class="lp-section-heading">

                    <span class="lp-section-tag">
                        {{ __('lang.features_tag') }}
                    </span>

                    <h2 class="lp-h">{{ __('lang.f_title') }}</h2>

                    <p class="lp-sub">{{ __('lang.f_sub') }}</p>

                </div>

                <div class="lp-grid lp-grid-advanced">

                    @foreach (__('lang.features') as $index => [$h, $d])

                        <article class="sf-card lp-feat lp-feat-advanced">

                            <div class="lp-feature-top">

                                <span class="lp-feature-number">
                                    {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                                </span>

                                <span class="lp-feature-arrow" aria-hidden="true">
                                    ↗
                                </span>

                            </div>

                            <h3>{{ $h }}</h3>

                            <p>{{ $d }}</p>

                            <div class="lp-feature-line"></div>

                        </article>

                    @endforeach

                </div>

            </section>

            {{-- How it works --}}
            <section id="how" class="lp-section lp-how">

                <div class="lp-section-heading">

                    <span class="lp-section-tag">
                        {{ __('lang.how_tag') }}
                    </span>

                    <h2 class="lp-h">{{ __('lang.h_title') }}</h2>

                </div>

                <ol class="lp-steps lp-steps-advanced">

                    @foreach (__('lang.steps') as $index => [$h, $d])

                        <li>

                            <div class="lp-step-marker">
                                <span>
                                    {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </div>

                            <div class="lp-step-content">
                                <h3>{{ $h }}</h3>
                                <p>{{ $d }}</p>
                            </div>

                        </li>

                    @endforeach

                </ol>

            </section>

            {{-- Storefront --}}
            <section id="store" class="lp-section lp-store lp-store-advanced">

                <div class="lp-store-content">

                    <span class="lp-section-tag">
                        {{ __('lang.store_tag') }}
                    </span>

                    <h2 class="lp-h">{{ __('lang.s_title') }}</h2>

                    <p class="lp-sub">{{ __('lang.s_sub') }}</p>

                    <ul class="lp-pts lp-pts-advanced">

                        @foreach (__('lang.s_points') as $p)

                            <li>
                                <span aria-hidden="true">✓</span>
                                {{ $p }}
                            </li>

                        @endforeach

                    </ul>

                    <a href="{{ $demoUrl }}" class="lp-btn lp-amber">
                        {{ __('lang.cta2') }}
                        <span aria-hidden="true">↗</span>
                    </a>

                </div>

                <div class="lp-vis lp-vis-advanced"
                     aria-label="{{ __('lang.s_title') }}">

                    <div class="lp-vis-header">

                        <span class="lp-vis-dot"></span>

                        <b>{{ __('lang.mock_title') }}</b>

                        <span class="lp-vis-live">✓</span>

                    </div>

                    <div class="lp-vis-content">

                        @foreach (__('lang.vis') as [$label, $shown])

                            <div class="lp-vis-row">

                                <span>{{ $label }}</span>

                                <span class="{{ $shown ? 'is-on' : 'is-off' }}">

                                    <span class="lp-vis-indicator"></span>

                                    {{ $shown ? __('lang.on') : __('lang.off') }}

                                </span>

                            </div>

                        @endforeach

                    </div>

                </div>

            </section>

            {{-- FAQ --}}
            <section id="faq" class="lp-section lp-faq lp-faq-advanced">

                <div class="lp-section-heading">

                    <span class="lp-section-tag">
                        {{ __('lang.faq_tag') }}
                    </span>

                    <h2 class="lp-h">{{ __('lang.faq_title') }}</h2>

                </div>

                <div class="lp-faq-list">

                    @foreach (__('lang.faq') as $index => [$q, $a])

                        <details class="lp-faq-item">

                            <summary>

                                <span class="lp-faq-number">
                                    {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                                </span>

                                <span class="lp-faq-question">
                                    {{ $q }}
                                </span>

                                <span class="lp-faq-icon" aria-hidden="true">
                                    +
                                </span>

                            </summary>

                            <div class="lp-faq-answer">
                                <p>{{ $a }}</p>
                            </div>

                        </details>

                    @endforeach

                </div>

            </section>

            {{-- Final CTA --}}
            <section class="lp-final lp-final-advanced">

                <div class="lp-final-decoration"></div>

                <div class="lp-final-content">

                    <span class="lp-section-tag">
                        {{ __('lang.final_tag') }}
                    </span>

                    <h2>{{ __('lang.final') }}</h2>

                    <p>{{ __('lang.final_sub') }}</p>

                    @auth

                        <a class="lp-btn lp-amber lp-btn-primary"
                           href="{{ route('dashboard') }}">

                            {{ __('lang.dash') }}

                            <span aria-hidden="true">↗</span>

                        </a>

                    @elseif ($hasRegister)

                        <a class="lp-btn lp-amber lp-btn-primary"
                           href="{{ route('register') }}">

                            {{ __('lang.register') }}

                            <span aria-hidden="true">↗</span>

                        </a>

                    @endif

                </div>

            </section>

        </div>

        {{-- Mobile Sticky CTA --}}
        <div class="lp-mobile-cta">

            <a href="{{ $primaryUrl }}" class="lp-btn lp-amber">
                {{ $primaryLabel }}
                <span aria-hidden="true">↗</span>
            </a>

        </div>

    </div>

    @push('scripts')

        <script>
            (() => {
                const page = document.querySelector('.lp-advanced');

                if (!page) return;

                const progress = page.querySelector('#lpProgress');

                const navLinks = [
                    ...page.querySelectorAll('.lp-nav-link')
                ];

                const sections = navLinks
                    .map(link => document.getElementById(link.dataset.section))
                    .filter(Boolean);

                let ticking = false;

                const updatePage = () => {

                    const scrollable =
                        document.documentElement.scrollHeight - window.innerHeight;

                    const percentage = scrollable > 0
                        ? (window.scrollY / scrollable) * 100
                        : 0;

                    if (progress) {
                        progress.style.width = percentage + '%';
                    }

                    ticking = false;
                };

                window.addEventListener('scroll', () => {

                    if (!ticking) {
                        window.requestAnimationFrame(updatePage);
                        ticking = true;
                    }

                }, { passive: true });

                updatePage();

                // Active navigation
                if ('IntersectionObserver' in window) {

                    const observer = new IntersectionObserver(entries => {

                        entries.forEach(entry => {

                            if (!entry.isIntersecting) return;

                            navLinks.forEach(link => {

                                const active =
                                    link.dataset.section === entry.target.id;

                                link.classList.toggle('is-active', active);

                                if (active) {
                                    link.setAttribute('aria-current', 'location');
                                } else {
                                    link.removeAttribute('aria-current');
                                }

                            });

                        });

                    }, {
                        rootMargin: '-20% 0px -65% 0px'
                    });

                    sections.forEach(section => observer.observe(section));

                }

                // FAQ accordion
                const faqs = [
                    ...page.querySelectorAll('.lp-faq-item')
                ];

                faqs.forEach(item => {

                    item.addEventListener('toggle', () => {

                        if (!item.open) return;

                        faqs.forEach(other => {

                            if (other !== item) {
                                other.open = false;
                            }

                        });

                    });

                });

            })();
        </script>

    @endpush

</x-layouts::storefront>
