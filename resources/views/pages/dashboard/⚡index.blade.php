<?php

use App\Services\ShopDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('لوحة التحكم')] class extends Component
{
    /** @return array<string, mixed> */
    #[Computed]
    public function stats(): array
    {
        return app(ShopDashboardService::class)->summary(Auth::user());
    }
}; ?>
<style>
    .dash { max-width: 80rem; margin-inline: auto; display: flex; flex-direction: column; gap: 2rem; }
    .dash-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
    .dash-meta { display: flex; align-items: center; gap: .5rem; margin-top: .5rem; }

    .dash-kpis { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
    .dash-kpi { display: flex; align-items: center; gap: 1rem; }
    .dash-ico { width: 3rem; height: 3rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: .75rem; }
    .dash-ico svg { width: 1.5rem; height: 1.5rem; }
    .dash-label { font-size: .875rem; color: #71717a; }
    .dash-num { font-size: 1.875rem; line-height: 2.25rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .dash-cur { font-size: .875rem; font-weight: 400; color: #71717a; }

    .t-blue   { background: rgb(59 130 246 / .12);  color: #3b82f6; }
    .t-green  { background: rgb(16 185 129 / .12);  color: #10b981; }
    .t-amber  { background: rgb(245 158 11 / .14);  color: #f59e0b; }
    .t-red    { background: rgb(239 68 68 / .12);   color: #ef4444; }
    .t-violet { background: rgb(139 92 246 / .12);  color: #8b5cf6; }
    .t-sky    { background: rgb(14 165 233 / .12);  color: #0ea5e9; }

    .dash-main { display: grid; gap: 1.5rem; grid-template-columns: 2fr 1fr; align-items: start; }
    .dash-side { display: flex; flex-direction: column; gap: 1.5rem; }
    @media (max-width: 1023px) { .dash-main { grid-template-columns: minmax(0, 1fr); } }

    .dash-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .dash-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding-block: .75rem; border-top: 1px solid rgb(113 113 122 / .25); }
    .dash-row:first-of-type { border-top: 0; }
    .dash-muted { color: #71717a; }
    .dash-mono { font-family: ui-monospace, monospace; font-size: .75rem; color: #71717a; }
    .dash-empty { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding-block: 2.5rem; text-align: center; }

    .dash-table-wrap { overflow-x: auto; }
    .dash-table { width: 100%; min-width: 40rem; border-collapse: collapse; font-size: .875rem; }
    .dash-table th { text-align: start; font-weight: 500; color: #71717a; padding: .5rem 1rem .5rem 0; border-bottom: 1px solid rgb(113 113 122 / .3); }
    .dash-table td { padding: .75rem 1rem .75rem 0; border-top: 1px solid rgb(113 113 122 / .2); }
    .dash-in { color: #10b981; } .dash-out { color: #ef4444; }
    .dash-ltr { direction: ltr; unicode-bidi: plaintext; text-align: start; font-variant-numeric: tabular-nums; }
</style>

<div class="dash">
    @php
        $s = $this->stats;
        $user = auth()->user();
        $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $money = fn ($n) => number_format((float) $n, 2);
        $currency = $user->shop?->currency;

        $kpis = [
            ['المنتجات النشطة', $s['products_count'], 'cube', 't-blue'],
            ['نواقص (تحت حد التنبيه)', $s['low_stock_count'], 'exclamation-triangle', $s['low_stock_count'] > 0 ? 't-amber' : 't-green'],
            ['رصيد صفر أو أقل', $s['out_of_stock_count'], 'archive-box-x-mark', $s['out_of_stock_count'] > 0 ? 't-red' : 't-green'],
            ['الفئات', $s['categories_count'], 'tag', 't-violet'],
        ];
    @endphp

    {{-- Header --}}
    <div class="dash-head">
        <div>
            <flux:heading size="xl" level="1">{{ __('أهلاً') }} {{ $user->name }}</flux:heading>
            <div class="dash-meta">
                <flux:text>{{ $user->shop?->name }}</flux:text>
                <flux:badge size="sm" color="indigo">{{ $user->role->value }}</flux:badge>
            </div>
        </div>

        @can('viewAny', App\Models\Product::class)
            <flux:button :href="route('products.index')" wire:navigate variant="primary" icon="cube">
                {{ __('إدارة المنتجات') }}
            </flux:button>
        @endcan
    </div>

    {{-- KPIs --}}
    <div class="dash-kpis">
        @foreach ($kpis as [$label, $value, $icon, $tone])
            <flux:card wire:key="kpi-{{ $loop->index }}" class="dash-kpi">
                <div class="dash-ico {{ $tone }}"><flux:icon :name="$icon" /></div>
                <div>
                    <div class="dash-label">{{ __($label) }}</div>
                    <div class="dash-num">{{ $value }}</div>
                </div>
            </flux:card>
        @endforeach
    </div>

    {{-- Financials --}}
    @can('view-financials')
        <div class="dash-kpis">
            <flux:card class="dash-kpi">
                <div class="dash-ico t-green"><flux:icon name="banknotes" /></div>
                <div>
                    <div class="dash-label">{{ __('قيمة المخزون (بالتكلفة)') }}</div>
                    <div class="dash-num dash-ltr">{{ $money($s['financials']['inventory_cost']) }} <span class="dash-cur">{{ $currency }}</span></div>
                </div>
            </flux:card>

            <flux:card class="dash-kpi">
                <div class="dash-ico t-sky"><flux:icon name="chart-bar" /></div>
                <div>
                    <div class="dash-label">{{ __('قيمة المخزون (بسعر القطاعي)') }}</div>
                    <div class="dash-num dash-ltr">{{ $money($s['financials']['inventory_retail']) }} <span class="dash-cur">{{ $currency }}</span></div>
                </div>
            </flux:card>
        </div>
    @endcan

    <div class="dash-main">
        {{-- Low stock --}}
        <flux:card>
            <div class="dash-card-head">
                <flux:heading size="lg">{{ __('أقرب النواقص') }}</flux:heading>
                @if ($s['low_stock_count'] > 0)
                    <flux:badge color="amber" size="sm">{{ $s['low_stock_count'] }}</flux:badge>
                @endif
            </div>

            @forelse ($s['low_stock'] as $product)
                <div wire:key="low-{{ $product->id }}" class="dash-row">
                    <div>
                        <div style="font-weight:500">{{ $product->name }}</div>
                        <div class="dash-mono dash-ltr">
                            {{ $product->part_number ?? '—' }}@if ($product->brand) · {{ $product->brand->name }}@endif
                        </div>
                    </div>
                    <flux:badge color="red" size="sm">
                        <span class="dash-ltr">{{ $qty($product->stock_qty) }} / {{ $qty($product->min_stock_alert) }}</span>
                    </flux:badge>
                </div>
            @empty
                <div class="dash-empty">
                    <flux:icon name="check-circle" class="size-10 text-emerald-500" />
                    <flux:text>{{ __('مفيش نواقص حالياً، المخزون تمام.') }}</flux:text>
                </div>
            @endforelse
        </flux:card>

        {{-- Side --}}
        <div class="dash-side">
            @can('viewAny', App\Models\User::class)
                <flux:card>
                    <div class="dash-card-head">
                        <flux:heading size="lg">{{ __('الفريق') }}</flux:heading>
                        <flux:button size="sm" variant="ghost" :href="route('users.index')" wire:navigate>{{ __('إدارة') }}</flux:button>
                    </div>
                    @foreach (App\Enums\UserRole::cases() as $role)
                        <div class="dash-row">
                            <span class="dash-muted">{{ $role->value }}</span>
                            <flux:badge size="sm">{{ $s['team'][$role->value] ?? 0 }}</flux:badge>
                        </div>
                    @endforeach
                </flux:card>
            @endcan

            <flux:card>
                <div class="dash-card-head">
                    <flux:heading size="lg">{{ __('المنتجات حسب النوع') }}</flux:heading>
                </div>
                @forelse ($s['by_condition'] as $condition => $total)
                    <div class="dash-row">
                        <span class="dash-muted">{{ App\Enums\ProductCondition::tryFrom($condition)?->label() ?? $condition }}</span>
                        <flux:badge size="sm">{{ $total }}</flux:badge>
                    </div>
                @empty
                    <flux:text>—</flux:text>
                @endforelse
            </flux:card>
        </div>
    </div>

    {{-- Recent movements --}}
    <flux:card>
        <div class="dash-card-head">
            <flux:heading size="lg">{{ __('آخر حركات المخزون') }}</flux:heading>
        </div>

        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>{{ __('المنتج') }}</th>
                        <th>{{ __('النوع') }}</th>
                        <th>{{ __('الكمية') }}</th>
                        <th>{{ __('الرصيد بعد') }}</th>
                        <th>{{ __('بواسطة') }}</th>
                        <th>{{ __('الوقت') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($s['recent_movements'] as $m)
                        @php $in = (float) $m->quantity > 0; @endphp
                        <tr wire:key="mov-{{ $m->id }}">
                            <td style="font-weight:500">{{ $m->product?->name ?? '—' }}</td>
                            <td><flux:badge size="sm">{{ $m->type->label() }}</flux:badge></td>
                            <td class="dash-ltr {{ $in ? 'dash-in' : 'dash-out' }}">{{ $in ? '+' : '' }}{{ $qty($m->quantity) }}</td>
                            <td class="dash-ltr">{{ $qty($m->qty_after) }}</td>
                            <td class="dash-muted">{{ $m->user?->name ?? '—' }}</td>
                            <td class="dash-muted">{{ $m->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="dash-empty dash-muted">{{ __('لا توجد حركات بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>