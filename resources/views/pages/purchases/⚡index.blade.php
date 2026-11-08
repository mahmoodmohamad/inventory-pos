<?php

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('المشتريات')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: false)]
    public string $search = '';

    #[Url(as: 'supplier')]
    public string $supplierFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Purchase::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'supplierFilter');
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Purchase> */
    #[Computed]
    public function purchases(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Purchase::query()
            ->with('supplier')
            ->withSum('payments as paid_total', 'amount')
            ->withCount('items')
            ->when(ctype_digit($this->supplierFilter) && $this->supplierFilter !== '',
                fn (Builder $q) => $q->where('supplier_id', (int) $this->supplierFilter))
            ->when($term !== '', function (Builder $q) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->where('supplier_invoice_ref', 'like', $like)
                    ->when(ctype_digit($term), fn (Builder $x) => $x->orWhere('invoice_number', (int) $term)));
            })
            ->latest('id')
            ->paginate(20);
    }

    /** @return Collection<int, Supplier> */
    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::query()->orderBy('name')->get();
    }

    /** @return array{month_total: string, month_count: int, payable: string} */
    #[Computed]
    public function stats(): array
    {
        $month = Purchase::query()->where('received_at', '>=', now()->startOfMonth())
            ->toBase()->selectRaw('coalesce(sum(total_amount), 0) as t, count(*) as c')->first();

        $bought = (string) Purchase::query()->toBase()->sum('total_amount');
        $paid = (string) SupplierPayment::query()->toBase()->sum('amount');

        return [
            'month_total' => (string) ($month->t ?? '0'),
            'month_count' => (int) ($month->c ?? 0),
            'payable' => Money::sub($bought, $paid, 2),
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->supplierFilter !== '';
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6">
    @php
        $money = fn ($n) => number_format((float) $n, 2);
        $currency = auth()->user()->shop?->currency;
        $stats = $this->stats;
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('المشتريات') }}</flux:heading>
            <flux:subheading>{{ __('فواتير الاستلام من الموردين') }}</flux:subheading>
        </div>

        @can('create', App\Models\Purchase::class)
            <flux:button variant="primary" icon="plus" :href="route('purchases.create')" wire:navigate>{{ __('استلام مشتريات') }}</flux:button>
        @endcan
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">{{ __('مشتريات الشهر') }}</div>
            <div class="mt-1 flex items-baseline gap-2 text-2xl font-semibold">
                <span class="font-mono" dir="ltr">{{ $money($stats['month_total']) }}</span>
                <span class="text-sm font-normal text-zinc-500">{{ $currency }}</span>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">{{ __('عدد فواتير الشهر') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $stats['month_count'] }}</div>
        </div>
        <a href="{{ route('suppliers.index', ['f' => 'owing']) }}" wire:navigate
            class="rounded-xl border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
            <div class="text-sm text-zinc-500">{{ __('إجمالي المستحق للموردين') }}</div>
            <div class="mt-1 flex items-baseline gap-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">
                <span class="font-mono" dir="ltr">{{ $money($stats['payable']) }}</span>
                <span class="text-sm font-normal text-zinc-500">{{ $currency }}</span>
            </div>
        </a>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable type="search"
                :placeholder="__('رقم الفاتورة أو رقم فاتورة المورد')" />
        </div>
        <div class="w-56">
            <flux:select wire:model.live="supplierFilter">
                <flux:select.option value="">{{ __('كل الموردين') }}</flux:select.option>
                @foreach ($this->suppliers as $s)
                    <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($this->hasActiveFilters)
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 transition dark:border-zinc-700"
        wire:loading.class="opacity-50" wire:target="search,supplierFilter,resetFilters,gotoPage,nextPage,previousPage">
        <table class="w-full min-w-[760px] text-start text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('رقم') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('المورد') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('فاتورة المورد') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('البنود') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الإجمالي') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('دُفع عند الاستلام') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('التاريخ') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->purchases as $purchase)
                    @php $paid = (string) ($purchase->paid_total ?? '0'); @endphp
                    <tr wire:key="purchase-{{ $purchase->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono" dir="ltr">#{{ $purchase->invoice_number }}</td>
                        <td class="px-4 py-3 font-medium">{{ $purchase->supplier?->name ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-zinc-500" dir="ltr">{{ $purchase->supplier_invoice_ref ?: '—' }}</td>
                        <td class="px-4 py-3"><flux:badge size="sm">{{ $purchase->items_count }}</flux:badge></td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ $money($purchase->total_amount) }} {{ $currency }}</td>
                        <td class="px-4 py-3">
                            @if (bccomp(Money::norm($paid), '0', 2) > 0)
                                <span class="font-mono" dir="ltr">{{ $money($paid) }}</span>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('آجل') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-500" dir="ltr">{{ $purchase->received_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <flux:button size="sm" variant="ghost" :href="route('purchases.show', ['id' => $purchase->id])" wire:navigate>{{ __('عرض') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center">
                            <flux:icon.inbox class="mx-auto size-8 text-zinc-400" />
                            <p class="mt-2 text-zinc-500">{{ $this->hasActiveFilters ? __('مفيش فواتير مطابقة.') : __('لسه مفيش مشتريات.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->purchases->links() }}
</div>