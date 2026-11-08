<?php

use App\Enums\LedgerDirection;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('حسابات العملاء')] class extends Component
{
    use WithPagination;

    // ---------- State (all shareable / refresh-safe via the URL) ----------
    #[Url(as: 'q', history: false)]
    public string $search = '';

    /** all | owing | credit | settled */
    #[Url(as: 'balance')]
    public string $filter = 'all';

    /** name | balance */
    #[Url(as: 'sort')]
    public string $sort = 'name';

    #[Url(as: 'customer')]
    public string $selectedCustomerId = '';

    /** all | debit | credit */
    #[Url(as: 'type')]
    public string $entryType = 'all';

    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);

        // Sanitise values that arrive from the URL.
        if (! in_array($this->filter, ['all', 'owing', 'credit', 'settled'], true)) {
            $this->filter = 'all';
        }
        if (! in_array($this->sort, ['name', 'balance'], true)) {
            $this->sort = 'name';
        }
        if (! in_array($this->entryType, ['all', 'debit', 'credit'], true)) {
            $this->entryType = 'all';
        }
    }

    // ---------- Hooks ----------
    public function updatedSearch(): void
    {
        $this->resetPage('customersPage');
    }

    public function updatedFilter(): void
    {
        $this->resetPage('customersPage');
    }

    public function updatedSort(): void
    {
        $this->resetPage('customersPage');
    }

    public function updatedEntryType(): void
    {
        $this->resetPage('activityPage');
    }

    // ---------- Actions ----------
    public function setFilter(string $value): void
    {
        if (in_array($value, ['all', 'owing', 'credit', 'settled'], true)) {
            $this->filter = $value;
            $this->resetPage('customersPage');
        }
    }

    public function setEntryType(string $value): void
    {
        if (in_array($value, ['all', 'debit', 'credit'], true)) {
            $this->entryType = $value;
            $this->resetPage('activityPage');
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage('customersPage');
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
        $this->sort = 'name';
        $this->resetPage('customersPage');
    }

    public function showAccount(int $customerId): void
    {
        $customer = Customer::query()->findOrFail($customerId);
        $this->authorize('view', $customer);

        $this->selectedCustomerId = (string) $customer->id;
        $this->entryType = 'all';
        $this->resetPage('activityPage');
        unset($this->selectedCustomer, $this->activity);
    }

    public function clearAccount(): void
    {
        $this->selectedCustomerId = '';
        $this->resetPage('activityPage');
        unset($this->selectedCustomer, $this->activity);
    }

    // ---------- Data ----------

    /** @return LengthAwarePaginator<int, Customer> */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $query = $this->withAccountTotals(Customer::query());
        $search = trim($this->search);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('alternate_phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $balance = '(coalesce(debit_total, 0) - coalesce(credit_total, 0))';

        match ($this->filter) {
            'owing' => $query->havingRaw("$balance > 0"),
            'credit' => $query->havingRaw("$balance < 0"),
            'settled' => $query->havingRaw("$balance = 0"),
            default => null,
        };

        if ($this->sort === 'balance') {
            $query->orderByRaw("$balance desc");
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12, pageName: 'customersPage');
    }

    /**
     * Shop-wide totals in a single grouped query (balance per customer, then summarised).
     *
     * @return array{receivable: string, credit: string, owing: int, creditors: int}
     */
    #[Computed]
    public function stats(): array
    {
        $perCustomer = CustomerLedger::query()
            ->toBase()
            ->selectRaw(
                'customer_id, sum(case when direction = ? then amount else -amount end) as bal',
                [LedgerDirection::Debit->value],
            )
            ->groupBy('customer_id');

        $row = DB::query()
            ->fromSub($perCustomer, 'balances')
            ->selectRaw('
                coalesce(sum(case when bal > 0 then bal else 0 end), 0) as receivable,
                coalesce(sum(case when bal < 0 then -bal else 0 end), 0) as credit,
                coalesce(sum(case when bal > 0 then 1 else 0 end), 0) as owing,
                coalesce(sum(case when bal < 0 then 1 else 0 end), 0) as creditors
            ')
            ->first();

        return [
            'receivable' => number_format((float) ($row->receivable ?? 0), 2, '.', ''),
            'credit' => number_format((float) ($row->credit ?? 0), 2, '.', ''),
            'owing' => (int) ($row->owing ?? 0),
            'creditors' => (int) ($row->creditors ?? 0),
        ];
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        if ($this->selectedCustomerId === '' || ! ctype_digit($this->selectedCustomerId)) {
            return null;
        }

        // A stale / foreign id in the URL degrades to the empty state instead of a 404 page.
        $customer = $this->withAccountTotals(Customer::query())
            ->find((int) $this->selectedCustomerId);

        if ($customer === null) {
            return null;
        }

        $this->authorize('view', $customer);

        return $customer;
    }

    /**
     * Ledger page. When unfiltered, every row also gets a running balance ("الرصيد بعد الحركة")
     * computed from the customer's current balance minus everything newer than the page.
     *
     * @return LengthAwarePaginator<int, CustomerLedger>|null
     */
    #[Computed]
    public function activity(): ?LengthAwarePaginator
    {
        $customer = $this->selectedCustomer;

        if ($customer === null) {
            return null;
        }

        $page = $customer->ledgerEntries()
            ->with(['sale', 'payment'])
            ->when($this->entryType === 'debit', fn ($q) => $q->where('direction', LedgerDirection::Debit->value))
            ->when($this->entryType === 'credit', fn ($q) => $q->where('direction', LedgerDirection::Credit->value))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(15, pageName: 'activityPage');

        if ($this->entryType !== 'all' || $page->isEmpty()) {
            return $page;
        }

        $first = $page->first();

        $newer = $customer->ledgerEntries()
            ->where(fn ($q) => $q
                ->where('occurred_at', '>', $first->occurred_at)
                ->orWhere(fn ($same) => $same
                    ->where('occurred_at', $first->occurred_at)
                    ->where('id', '>', $first->id)))
            ->toBase()
            ->selectRaw(
                'coalesce(sum(case when direction = ? then amount else -amount end), 0) as total',
                [LedgerDirection::Debit->value],
            )
            ->first();

        $running = bcsub($this->balanceOf($customer), (string) ($newer->total ?? '0'), 2);

        $page->getCollection()->each(function (CustomerLedger $entry) use (&$running): void {
            $entry->setAttribute('running_balance', $running);

            $running = $entry->direction === LedgerDirection::Debit
                ? bcsub($running, (string) $entry->amount, 2)
                : bcadd($running, (string) $entry->amount, 2);
        });

        return $page;
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->filter !== 'all' || $this->sort !== 'name';
    }

    // ---------- Helpers ----------
    private function balanceOf(Customer $customer): string
    {
        return Money::sub(
            (string) ($customer->debit_total ?? '0.00'),
            (string) ($customer->credit_total ?? '0.00'),
            2,
        );
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function withAccountTotals(Builder $query): Builder
    {
        return $query
            ->withSum([
                'ledgerEntries as debit_total' => fn (Builder $ledger) => $ledger
                    ->where('direction', LedgerDirection::Debit->value),
            ], 'amount')
            ->withSum([
                'ledgerEntries as credit_total' => fn (Builder $ledger) => $ledger
                    ->where('direction', LedgerDirection::Credit->value),
            ], 'amount')
            ->withCount([
                'sales as completed_sales_count' => fn (Builder $sales) => $sales
                    ->where('status', SaleStatus::Completed->value),
            ]);
    }
}; ?>

@php
    $shop = auth()->user()->shop;
    $currency = $shop?->currency;
    $selected = $this->selectedCustomer;
    $stats = $this->stats;

    $money = fn ($value) => number_format((float) ($value ?? 0), 2, '.', ',');
    $abs = fn ($value) => ltrim((string) $value, '-');
    $balanceOf = fn (Customer $c) => Money::sub((string) ($c->debit_total ?? '0.00'), (string) ($c->credit_total ?? '0.00'), 2);

    // Egyptian local number -> wa.me international format
    $waNumber = function (?string $phone): ?string {
        $d = (string) preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '0')) {
            $d = '20'.substr($d, 1);
        }

        return strlen($d) >= 10 ? $d : null;
    };

    $filters = [
        'all' => __('الكل'),
        'owing' => __('عليهم رصيد'),
        'credit' => __('لهم رصيد'),
        'settled' => __('مسدد'),
    ];
@endphp

{{-- Scoped CSS: بيضمن إن الـ layout يشتغل حتى لو Tailwind مش ماسح الملف ده --}}
<style>
    .ca { --ca-line: rgb(113 113 122 / .28); --ca-soft: rgb(113 113 122 / .09); --ca-surface: #fff;
        --ca-muted: #71717a; --ca-amber: #b45309; --ca-green: #047857; --ca-blue: #2563eb;
        --ca-ink: #18181b; --ca-ink-rev: #fff;
        display: flex; flex-direction: column; gap: 1.5rem; max-width: 80rem; margin-inline: auto; }
    .dark .ca { --ca-surface: #18181b; --ca-amber: #f59e0b; --ca-green: #34d399; --ca-blue: #60a5fa;
        --ca-ink: #fff; --ca-ink-rev: #18181b; }

    .ca *:focus-visible { outline: 2px solid var(--ca-blue); outline-offset: 2px; }
    .ca-muted { color: var(--ca-muted); }
    .ca-loading { opacity: .55; }
    .ca-num { font-family: ui-monospace, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; white-space: nowrap; }
    .ca-owe { color: var(--ca-amber); }
    .ca-cr { color: var(--ca-green); }
    .ca-ico { width: .95rem; height: .95rem; flex-shrink: 0; }

    .ca-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    .ca-btn { display: none; align-items: center; gap: .4rem; padding: .45rem .9rem; border-radius: .5rem; border: 1px solid var(--ca-line);
        background: var(--ca-soft); color: inherit; font: inherit; font-size: .875rem; cursor: pointer; text-decoration: none; }
    .ca-btn.is-show { display: inline-flex; }

    /* ---- Summary ---- */
    .ca-stats { display: grid; gap: .75rem; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0; }
    .ca-stat { display: block; text-align: start; padding: .9rem 1rem; border: 1px solid var(--ca-line); border-radius: .75rem;
        background: var(--ca-surface); color: inherit; font: inherit; }
    button.ca-stat { cursor: pointer; transition: border-color .15s; }
    button.ca-stat:hover { border-color: var(--ca-muted); }
    .ca-stat dt { margin: 0; font-size: .8125rem; color: var(--ca-muted); }
    .ca-stat dd { margin: .25rem 0 0; display: flex; align-items: baseline; gap: .4rem; font-size: 1.5rem; font-weight: 600; }
    .ca-stat small { font-size: .75rem; font-weight: 400; color: var(--ca-muted); font-family: inherit; }

    /* ---- Layout ---- */
    .ca-layout { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
    .ca-list { display: flex; flex-direction: column; gap: .75rem; min-width: 0; }
    .ca-detail { min-width: 0; }
    /* mobile: list OR detail */
    .ca.has-sel .ca-list, .ca.has-sel .ca-stats { display: none; }
    .ca:not(.has-sel) .ca-detail { display: none; }
    @media (min-width: 1024px) {
        .ca-layout { grid-template-columns: 380px minmax(0, 1fr); }
        .ca-list { position: sticky; top: 1rem; }
        .ca.has-sel .ca-list { display: flex; }
        .ca.has-sel .ca-stats { display: grid; }
        .ca:not(.has-sel) .ca-detail { display: block; }
        .ca-btn.is-show { display: none; }
    }
    @media (max-width: 639px) { .ca-stats { grid-template-columns: minmax(0, 1fr); } }

    /* ---- Filters ---- */
    .ca-toolbar { display: flex; align-items: center; gap: .5rem; }
    .ca-chips { display: flex; flex: 1; min-width: 0; gap: .35rem; overflow-x: auto; padding-bottom: 2px; }
    .ca-chip { flex-shrink: 0; padding: .3rem .8rem; border-radius: 999px; border: 1px solid var(--ca-line); background: transparent;
        color: inherit; font: inherit; font-size: .8125rem; font-weight: 500; cursor: pointer; white-space: nowrap; transition: background .15s; }
    .ca-chip:hover { background: var(--ca-soft); }
    .ca-chip[aria-selected="true"] { background: var(--ca-ink); border-color: var(--ca-ink); color: var(--ca-ink-rev); }
    .ca-sort { width: 9.5rem; flex-shrink: 0; }
    .ca-meta { display: flex; align-items: center; justify-content: space-between; font-size: .8125rem; color: var(--ca-muted); }
    .ca-link { background: none; border: 0; padding: 0; color: var(--ca-blue); font: inherit; cursor: pointer; }
    .ca-link:hover { text-decoration: underline; }

    /* ---- Customer rows ---- */
    .ca-box { border: 1px solid var(--ca-line); border-radius: .75rem; background: var(--ca-surface); overflow: hidden; transition: opacity .15s; }
    .ca-rows { list-style: none; margin: 0; padding: 0; }
    .ca-rows > li + li { border-top: 1px solid var(--ca-line); }
    .ca-row { display: flex; align-items: center; gap: .75rem; width: 100%; padding: .75rem .9rem; border: 0; border-inline-start: 3px solid transparent;
        background: transparent; color: inherit; font: inherit; text-align: start; cursor: pointer; transition: background .15s; }
    .ca-row:hover { background: var(--ca-soft); }
    .ca-row[aria-current="true"] { background: rgb(37 99 235 / .08); border-inline-start-color: var(--ca-blue); }
    .ca-avatar { display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; flex-shrink: 0; border-radius: 50%;
        background: var(--ca-soft); color: var(--ca-muted); font-weight: 600; font-size: .9rem; }
    .ca-row[aria-current="true"] .ca-avatar { background: var(--ca-blue); color: #fff; }
    .ca-row-main { flex: 1; min-width: 0; }
    .ca-row-name { display: flex; align-items: center; gap: .5rem; font-weight: 500; }
    .ca-row-name > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ca-row-sub { display: block; font-size: .75rem; color: var(--ca-muted); direction: ltr; text-align: start; overflow: hidden; text-overflow: ellipsis; }
    .ca-row-bal { flex-shrink: 0; text-align: end; line-height: 1.3; }
    .ca-row-bal b { display: block; font-size: .875rem; font-weight: 600; }
    .ca-row-bal small { display: block; font-size: .6875rem; color: var(--ca-muted); }
    .ca-empty { padding: 2.5rem 1.5rem; text-align: center; }
    .ca-empty p { margin: .25rem 0 0; }

    /* ---- Detail ---- */
    .ca-stack { display: flex; flex-direction: column; gap: 1rem; }
    .ca-profile { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.25rem; }
    .ca-who { display: flex; align-items: flex-start; gap: 1rem; min-width: 0; }
    .ca-avatar-lg { width: 3.5rem; height: 3.5rem; border-radius: 1rem; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center;
        justify-content: center; background: var(--ca-soft); flex-shrink: 0; }
    .ca-title { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .ca-contacts { display: flex; flex-wrap: wrap; gap: .4rem 1rem; margin-top: .5rem; font-size: .875rem; color: var(--ca-muted); }
    .ca-contact { display: inline-flex; align-items: center; gap: .35rem; direction: ltr; }
    .ca-contact a { display: inline-flex; align-items: center; gap: .35rem; color: inherit; text-decoration: none; }
    .ca-contact a:hover { color: var(--ca-blue); text-decoration: underline; }
    .ca-copy { display: inline-flex; padding: 2px; border: 0; border-radius: .25rem; background: none; color: var(--ca-muted); cursor: pointer; }
    .ca-copy:hover { color: var(--ca-ink); }

    .ca-balance { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.25rem;
        border-top: 1px solid var(--ca-line); }
    .ca-balance.is-owing { background: rgb(245 158 11 / .09); }
    .ca-balance.is-credit { background: rgb(16 185 129 / .09); }
    .ca-balance-label { font-size: .875rem; color: var(--ca-muted); }
    .ca-balance-value { display: flex; align-items: baseline; gap: .5rem; margin-top: .25rem; font-size: 2.25rem; font-weight: 700; line-height: 1.1; }
    .ca-balance-value small { font-size: .875rem; font-weight: 400; color: var(--ca-muted); font-family: inherit; }
    .ca-pay { width: 16rem; max-width: 100%; }
    .ca-pay-top { display: flex; justify-content: space-between; margin-bottom: .3rem; font-size: .75rem; color: var(--ca-muted); }
    .ca-bar { height: .5rem; border-radius: 999px; background: rgb(113 113 122 / .25); overflow: hidden; }
    .ca-bar > div { height: 100%; border-radius: 999px; background: #10b981; transition: width .5s; }

    .ca-totals { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0; border-top: 1px solid var(--ca-line); }
    .ca-totals > div { padding: .75rem 1rem; }
    .ca-totals > div + div { border-inline-start: 1px solid var(--ca-line); }
    .ca-totals dt { margin: 0; font-size: .75rem; color: var(--ca-muted); }
    .ca-totals dd { margin: .15rem 0 0; font-size: .9rem; font-weight: 600; }

    /* ---- Statement ---- */
    .ca-st-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1.25rem; border-bottom: 1px solid var(--ca-line); }
    .ca-seg { display: inline-flex; padding: 2px; border: 1px solid var(--ca-line); border-radius: .5rem; }
    .ca-seg button { padding: .25rem .8rem; border: 0; border-radius: .375rem; background: transparent; color: inherit; font: inherit; font-size: .8125rem; font-weight: 500; cursor: pointer; }
    .ca-seg button:hover { background: var(--ca-soft); }
    .ca-seg button[aria-selected="true"] { background: var(--ca-ink); color: var(--ca-ink-rev); }
    .ca-tablewrap { overflow-x: auto; transition: opacity .15s; }
    .ca-table { width: 100%; min-width: 40rem; border-collapse: collapse; font-size: .875rem; }
    .ca-table th { padding: .6rem 1.25rem; text-align: start; font-size: .75rem; font-weight: 500; color: var(--ca-muted); border-bottom: 1px solid var(--ca-line); white-space: nowrap; }
    .ca-table td { padding: .75rem 1.25rem; vertical-align: top; border-top: 1px solid var(--ca-line); }
    .ca-table tbody tr:first-child td { border-top: 0; }
    .ca-table tbody tr:hover { background: var(--ca-soft); }
    .ca-table .is-end { text-align: end; }
    .ca-dim { color: rgb(113 113 122 / .4); }
    .ca-note { margin-top: .25rem; max-width: 20rem; font-size: .75rem; color: var(--ca-muted); }
    .ca-doclink { display: inline-flex; align-items: center; gap: .25rem; margin-top: .125rem; font-size: .75rem; color: var(--ca-blue); text-decoration: none; }
    .ca-doclink:hover { text-decoration: underline; }
    .ca-pager { padding: .75rem 1rem; border-top: 1px solid var(--ca-line); }

    .ca-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 26rem; padding: 2rem; text-align: center;
        border: 1px dashed var(--ca-line); border-radius: .75rem; background: var(--ca-soft); }
    .ca-placeholder .ca-circle { display: flex; align-items: center; justify-content: center; width: 4rem; height: 4rem; border-radius: 50%; background: var(--ca-soft); margin-bottom: 1rem; }
    @media (prefers-reduced-motion: reduce) { .ca * { transition: none !important; } }
</style>

<div
    class="ca {{ $selected ? 'has-sel' : '' }}"
    x-data
    x-on:keydown.window="
        const typing = ['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName);
        if ($event.key === '/' && !typing) { $event.preventDefault(); $refs.search?.querySelector('input')?.focus(); }
        if ($event.key === 'Escape' && !typing && $wire.selectedCustomerId) { $wire.clearAccount(); }
    "
>
    {{-- ============ Header ============ --}}
    <div class="ca-head">
        <div>
            <flux:heading size="xl" level="1">{{ __('حسابات العملاء') }}</flux:heading>
            <flux:text class="mt-1">{{ __('تابع المستحقات وكشف حساب كل عميل') }}</flux:text>
        </div>

        <button type="button" class="ca-btn {{ $selected ? 'is-show' : '' }}" wire:click="clearAccount">
            <flux:icon.arrow-right variant="micro" class="ca-ico" />
            {{ __('العودة للقائمة') }}
        </button>
    </div>

    {{-- ============ Shop-wide summary ============ --}}
    <dl class="ca-stats">
        <div class="ca-stat">
            <dt>{{ __('إجمالي المستحق على العملاء') }}</dt>
            <dd><span class="ca-num ca-owe">{{ $money($stats['receivable']) }}</span><small>{{ $currency }}</small></dd>
        </div>

        <button type="button" class="ca-stat" wire:click="setFilter('owing')">
            <dt>{{ __('عملاء عليهم رصيد') }}</dt>
            <dd><span class="ca-num">{{ $stats['owing'] }}</span></dd>
        </button>

        <button type="button" class="ca-stat" wire:click="setFilter('credit')">
            <dt>{{ __('أرصدة لصالح العملاء') }}</dt>
            <dd><span class="ca-num ca-cr">{{ $money($stats['credit']) }}</span><small>{{ $currency }}</small></dd>
        </button>
    </dl>

    <div class="ca-layout">

        {{-- ============ Customer list ============ --}}
        <section class="ca-list" aria-label="{{ __('قائمة العملاء') }}">
            <div x-ref="search">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('ابحث بالاسم أو الهاتف   ( اضغط / )')"
                    clearable
                    type="search"
                />
            </div>

            <div class="ca-toolbar">
                <div class="ca-chips" role="tablist" aria-label="{{ __('تصفية بالرصيد') }}">
                    @foreach ($filters as $key => $label)
                        <button type="button" role="tab" class="ca-chip"
                            aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                            wire:click="setFilter('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                </div>

                <div class="ca-sort">
                    <flux:select wire:model.live="sort" size="sm" aria-label="{{ __('الترتيب') }}">
                        <flux:select.option value="name">{{ __('الاسم') }}</flux:select.option>
                        <flux:select.option value="balance">{{ __('الأعلى رصيدًا') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <div class="ca-meta" aria-live="polite">
                <span>{{ __(':n عميل', ['n' => $this->customers->total()]) }}</span>
                @if ($this->hasActiveFilters)
                    <button type="button" class="ca-link" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</button>
                @endif
            </div>

            <div class="ca-box" wire:loading.class="ca-loading" wire:target="search,filter,sort,setFilter,resetFilters,gotoPage,nextPage,previousPage">
                <ul class="ca-rows">
                    @forelse ($this->customers as $customer)
                        @php
                            $bal = $balanceOf($customer);
                            $isOwing = bccomp($bal, '0', 2) > 0;
                            $isCredit = bccomp($bal, '0', 2) < 0;
                            $isSelected = (string) $customer->id === $selectedCustomerId;
                            $initial = mb_substr(trim($customer->name), 0, 1) ?: '?';
                        @endphp
                        <li wire:key="customer-{{ $customer->id }}">
                            <button type="button" class="ca-row" wire:click="showAccount({{ $customer->id }})"
                                @if ($isSelected) aria-current="true" @endif>
                                <span class="ca-avatar">{{ $initial }}</span>

                                <span class="ca-row-main">
                                    <span class="ca-row-name">
                                        <span>{{ $customer->name }}</span>
                                        @unless ($customer->is_active)
                                            <flux:badge size="sm" color="zinc">{{ __('موقوف') }}</flux:badge>
                                        @endunless
                                    </span>
                                    <span class="ca-row-sub">{{ $customer->phone ?: ($customer->email ?: '—') }}</span>
                                </span>

                                <span class="ca-row-bal">
                                    @if ($isOwing)
                                        <b class="ca-num ca-owe">{{ $money($bal) }}</b><small>{{ __('عليه') }}</small>
                                    @elseif ($isCredit)
                                        <b class="ca-num ca-cr">{{ $money($abs($bal)) }}</b><small>{{ __('له') }}</small>
                                    @else
                                        <b class="ca-num ca-muted" style="font-weight:400">0.00</b><small>{{ __('مسدد') }}</small>
                                    @endif
                                </span>
                            </button>
                        </li>
                    @empty
                        <li class="ca-empty">
                            <flux:icon.user-group class="size-8 ca-muted" style="margin-inline:auto" />
                            <p style="font-weight:500">{{ __('مفيش عملاء مطابقين') }}</p>
                            <p class="ca-muted" style="font-size:.8125rem">{{ __('جرّب اسمًا أو رقمًا مختلفًا، أو غيّر الفلتر.') }}</p>
                            @if ($this->hasActiveFilters)
                                <div style="margin-top:.75rem"><flux:button size="sm" variant="filled" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button></div>
                            @endif
                        </li>
                    @endforelse
                </ul>
            </div>

            @if ($this->customers->hasPages())
                <div>{{ $this->customers->links(data: ['scrollTo' => false]) }}</div>
            @endif
        </section>

        {{-- ============ Account detail ============ --}}
        <section class="ca-detail" aria-label="{{ __('تفاصيل الحساب') }}">
            @if ($selected)
                @php
                    $balance = $balanceOf($selected);
                    $isOwing = bccomp($balance, '0', 2) > 0;
                    $isCredit = bccomp($balance, '0', 2) < 0;
                    $activity = $this->activity;
                    $initial = mb_substr(trim($selected->name), 0, 1) ?: '?';

                    $debit = (float) ($selected->debit_total ?? 0);
                    $credit = (float) ($selected->credit_total ?? 0);
                    $paidPct = $debit > 0 ? (int) min(100, round($credit / $debit * 100)) : 0;

                    $wa = $waNumber($selected->phone) ?? $waNumber($selected->alternate_phone);
                    $reminder = 'السلام عليكم '.$selected->name.'، تذكير ودّي بأن المتبقي على حسابكم لدى '.($shop?->name ?? '').' هو '.$money($balance).' '.$currency.'. شكرًا لتعاملكم معنا.';
                    $numbers = array_filter([[$selected->phone, 'phone'], [$selected->alternate_phone, 'device-phone-mobile']], fn ($p) => filled($p[0]));
                @endphp

                <div class="ca-stack" wire:key="account-{{ $selected->id }}">

                    {{-- Profile + balance --}}
                    <div class="ca-box">
                        <div class="ca-profile">
                            <div class="ca-who">
                                <div class="ca-avatar-lg">{{ $initial }}</div>
                                <div style="min-width:0">
                                    <div class="ca-title">
                                        <flux:heading size="lg">{{ $selected->name }}</flux:heading>
                                        <flux:badge :color="$selected->is_active ? 'green' : 'zinc'" size="sm">
                                            {{ $selected->is_active ? __('نشط') : __('موقوف') }}
                                        </flux:badge>
                                    </div>

                                    <div class="ca-contacts">
                                        @foreach ($numbers as [$num, $icon])
                                            <span class="ca-contact" x-data="{ copied: false }">
                                                <a href="tel:{{ $num }}">
                                                    <flux:icon :name="$icon" variant="micro" class="ca-ico" />{{ $num }}
                                                </a>
                                                <button type="button" class="ca-copy" aria-label="{{ __('نسخ الرقم') }}"
                                                    x-on:click="navigator.clipboard.writeText(@js($num)); copied = true; setTimeout(() => copied = false, 1500)">
                                                    <flux:icon.check x-show="copied" x-cloak variant="micro" class="ca-ico ca-cr" />
                                                    <flux:icon.document-duplicate x-show="!copied" variant="micro" class="ca-ico" />
                                                </button>
                                            </span>
                                        @endforeach

                                        @if ($selected->email)
                                            <span class="ca-contact"><a href="mailto:{{ $selected->email }}"><flux:icon.envelope variant="micro" class="ca-ico" />{{ $selected->email }}</a></span>
                                        @endif

                                        @if ($selected->address)
                                            <span class="ca-contact" style="direction:inherit"><flux:icon.map-pin variant="micro" class="ca-ico" />{{ $selected->address }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($isOwing && $wa)
                                <flux:button size="sm" variant="filled" icon="chat-bubble-left-right"
                                    href="https://wa.me/{{ $wa }}?text={{ rawurlencode($reminder) }}" target="_blank" rel="noopener">
                                    {{ __('تذكير على واتساب') }}
                                </flux:button>
                            @endif
                        </div>

                        <div class="ca-balance {{ $isOwing ? 'is-owing' : ($isCredit ? 'is-credit' : '') }}">
                            <div>
                                <div class="ca-balance-label">
                                    {{ $isOwing ? __('المطلوب من العميل') : ($isCredit ? __('رصيد لصالح العميل') : __('الحساب مسدد')) }}
                                </div>
                                <div class="ca-balance-value">
                                    <span class="ca-num {{ $isOwing ? 'ca-owe' : ($isCredit ? 'ca-cr' : '') }}">{{ $money($abs($balance)) }}</span>
                                    <small>{{ $currency }}</small>
                                </div>
                            </div>

                            @if ($debit > 0)
                                <div class="ca-pay">
                                    <div class="ca-pay-top"><span>{{ __('نسبة السداد') }}</span><span class="ca-num">{{ $paidPct }}%</span></div>
                                    <div class="ca-bar" role="progressbar" aria-valuenow="{{ $paidPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('نسبة السداد') }}">
                                        <div style="width: {{ $paidPct }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <dl class="ca-totals">
                            <div><dt>{{ __('إجمالي المدين') }}</dt><dd class="ca-num">{{ $money($selected->debit_total) }}</dd></div>
                            <div><dt>{{ __('إجمالي الدائن') }}</dt><dd class="ca-num">{{ $money($selected->credit_total) }}</dd></div>
                            <div><dt>{{ __('فواتير مكتملة') }}</dt><dd class="ca-num">{{ $selected->completed_sales_count }}</dd></div>
                        </dl>
                    </div>

                    {{-- Statement --}}
                    <div class="ca-box">
                        <div class="ca-st-head">
                            <flux:heading size="sm">{{ __('كشف الحساب') }}</flux:heading>

                            <div class="ca-seg" role="tablist" aria-label="{{ __('نوع الحركة') }}">
                                @foreach (['all' => __('الكل'), 'debit' => __('مدين'), 'credit' => __('دائن')] as $key => $label)
                                    <button type="button" role="tab" aria-selected="{{ $entryType === $key ? 'true' : 'false' }}"
                                        wire:click="setEntryType('{{ $key }}')">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="ca-tablewrap" wire:loading.class="ca-loading" wire:target="setEntryType,gotoPage,nextPage,previousPage">
                            <table class="ca-table">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('التاريخ') }}</th>
                                        <th scope="col">{{ __('البيان') }}</th>
                                        <th scope="col" class="is-end">{{ __('مدين') }}</th>
                                        <th scope="col" class="is-end">{{ __('دائن') }}</th>
                                        @if ($entryType === 'all')
                                            <th scope="col" class="is-end">{{ __('الرصيد بعد الحركة') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($activity as $entry)
                                        @php $isDebit = $entry->direction === LedgerDirection::Debit; @endphp
                                        <tr wire:key="entry-{{ $entry->id }}">
                                            <td class="ca-num" style="direction:ltr">
                                                <div>{{ $entry->occurred_at?->format('Y-m-d') }}</div>
                                                <div class="ca-muted" style="font-size:.75rem">{{ $entry->occurred_at?->format('H:i') }}</div>
                                            </td>
                                            <td>
                                                <div style="font-weight:500">{{ $entry->source->label() }}</div>
                                                @if ($entry->sale)
                                                    <a class="ca-doclink" href="{{ route('sales.show', ['id' => $entry->sale->id]) }}" wire:navigate>
                                                        {{ __('فاتورة #:number', ['number' => $entry->sale->invoice_number]) }}
                                                        <flux:icon.arrow-up-right variant="micro" class="ca-ico" style="width:.75rem;height:.75rem" />
                                                    </a>
                                                @elseif ($entry->payment)
                                                    <div class="ca-note">{{ $entry->payment->method->label() }}</div>
                                                @endif
                                                @if ($entry->notes)
                                                    <div class="ca-note">{{ $entry->notes }}</div>
                                                @endif
                                            </td>
                                            <td class="is-end ca-num {{ $isDebit ? 'ca-owe' : 'ca-dim' }}" style="{{ $isDebit ? 'font-weight:500' : '' }}">{{ $isDebit ? $money($entry->amount) : '—' }}</td>
                                            <td class="is-end ca-num {{ ! $isDebit ? 'ca-cr' : 'ca-dim' }}" style="{{ ! $isDebit ? 'font-weight:500' : '' }}">{{ ! $isDebit ? $money($entry->amount) : '—' }}</td>
                                            @if ($entryType === 'all')
                                                @php $rb = (string) $entry->running_balance; @endphp
                                                <td class="is-end ca-num ca-muted">
                                                    {{ $money($abs($rb)) }}
                                                    <small style="font-family:inherit;font-size:.6875rem">{{ bccomp($rb, '0', 2) > 0 ? __('عليه') : (bccomp($rb, '0', 2) < 0 ? __('له') : '') }}</small>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $entryType === 'all' ? 5 : 4 }}" class="ca-empty">
                                                <flux:icon.document-text class="size-8 ca-muted" style="margin-inline:auto" />
                                                <p style="font-weight:500">
                                                    {{ $entryType === 'all' ? __('مفيش حركات مالية مسجلة لهذا العميل') : __('مفيش حركات من هذا النوع') }}
                                                </p>
                                                @if ($entryType !== 'all')
                                                    <div style="margin-top:.75rem"><flux:button size="sm" variant="filled" wire:click="setEntryType('all')">{{ __('عرض كل الحركات') }}</flux:button></div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($activity && $activity->hasPages())
                            <div class="ca-pager">{{ $activity->links(data: ['scrollTo' => false]) }}</div>
                        @endif
                    </div>
                </div>
            @else
                <div class="ca-placeholder">
                    <div class="ca-circle"><flux:icon.user-circle class="size-8 ca-muted" /></div>
                    <flux:heading size="md">{{ __('اختر عميلًا لعرض حسابه') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('هتشوف الرصيد وكشف الحساب والفواتير والدفعات هنا.') }}</flux:text>
                    <p class="ca-muted" style="margin-top:1rem;font-size:.75rem">{{ __('اضغط / للبحث، وEsc للرجوع للقائمة') }}</p>
                </div>
            @endif
        </section>
    </div>
</div>