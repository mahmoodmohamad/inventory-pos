<?php

use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('الفواتير')] class extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'q', history: false)]
    public string $search = '';

    /** Y-m-d */
    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    public string $priceType = 'retail';

    public string $customerId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Sale::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'search', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('statusFilter', 'search', 'from', 'to');
        $this->resetPage();
    }

    public function createDraft(): void
    {
        $this->authorize('create', Sale::class);

        $this->validate([
            'priceType' => ['required', Rule::enum(PriceType::class)],
            'customerId' => ['nullable', 'integer'],
        ]);

        $sale = app(SaleService::class)->createDraft(
            Auth::user(),
            $this->customerId === '' ? null : (int) $this->customerId,
            PriceType::from($this->priceType),
        );

        $this->redirectRoute('sales.show', ['id' => $sale->id], navigate: true);
    }

    /** كل الفلاتر ما عدا الحالة (عشان الإجماليات تفضل على المكتملة بس). */
    private function filtered(): Builder
    {
        $term = trim($this->search);
        $from = $this->day($this->from);
        $to = $this->day($this->to);

        return Sale::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when($term !== '', function (Builder $q) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $q->where(function (Builder $w) use ($term, $like): void {
                    if (ctype_digit($term)) {
                        $w->orWhere('invoice_number', (int) $term);
                    }

                    $w->orWhereHas('customer', fn (Builder $c) => $c
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like));
                });
            });
    }

    private function day(string $value): ?Carbon
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return LengthAwarePaginator<int, Sale> */
    #[Computed]
    public function sales(): LengthAwarePaginator
    {
        return $this->filtered()
            ->with('customer')
            ->when(SaleStatus::tryFrom($this->statusFilter), fn ($q, SaleStatus $s) => $q->where('status', $s->value))
            ->latest('id')
            ->paginate(20);
    }

    /** إجماليات المبيعات المكتملة ضمن الفلاتر الحالية. */
    #[Computed]
    public function totals(): array
    {
        $row = $this->filtered()
            ->where('status', SaleStatus::Completed->value)
            ->toBase()
            ->selectRaw('coalesce(sum(total_amount), 0) as total, count(*) as c')
            ->first();

        $count = (int) ($row->c ?? 0);
        $total = number_format((float) ($row->total ?? 0), 2, '.', '');

        return [
            'total' => $total,
            'count' => $count,
            'avg' => $count > 0 ? bcdiv($total, (string) $count, 2) : '0.00',
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->statusFilter.trim($this->search).$this->from.$this->to !== '';
    }

    /** @return Collection<int, Customer> */
    #[Computed]
    public function customers(): Collection
    {
        return Customer::query()->where('is_active', true)->orderBy('name')->get();
    }
}; ?>

<div class="space-y-6">
    @php
        $money = fn ($n) => number_format((float) $n, 2);
        $currency = auth()->user()->shop?->currency;
        $totals = $this->totals;
    @endphp

    <div>
        <flux:heading size="xl">{{ __('الفواتير') }}</flux:heading>
        <flux:subheading>{{ __('مسودات الفواتير والفواتير المكتملة') }}</flux:subheading>
    </div>

    @can('create', App\Models\Sale::class)
        <form wire:submit="createDraft" class="grid items-end gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-4 dark:border-zinc-700">
            <flux:select wire:model="priceType" :label="__('نوع التسعير')">
                @foreach (App\Enums\PriceType::cases() as $t)
                    <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="customerId" :label="__('العميل (اختياري)')">
                <flux:select.option value="">{{ __('بدون عميل') }}</flux:select.option>
                @foreach ($this->customers as $customer)
                    <flux:select.option value="{{ $customer->id }}">{{ $customer->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex sm:col-span-2 sm:justify-end">
                <flux:button type="submit" variant="primary" icon="plus">{{ __('فاتورة جديدة') }}</flux:button>
            </div>
        </form>
    @endcan

    {{-- إجماليات المكتمل ضمن الفلاتر --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">{{ __('إجمالي المبيعات المكتملة') }}</div>
            <div class="mt-1 flex items-baseline gap-2 text-2xl font-semibold">
                <span class="font-mono" dir="ltr">{{ $money($totals['total']) }}</span>
                <span class="text-sm font-normal text-zinc-500">{{ $currency }}</span>
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">{{ __('عدد الفواتير المكتملة') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $totals['count'] }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">{{ __('متوسط الفاتورة') }}</div>
            <div class="mt-1 flex items-baseline gap-2 text-2xl font-semibold">
                <span class="font-mono" dir="ltr">{{ $money($totals['avg']) }}</span>
                <span class="text-sm font-normal text-zinc-500">{{ $currency }}</span>
            </div>
        </div>
    </div>

    {{-- الفلاتر --}}
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable type="search"
                :placeholder="__('رقم الفاتورة أو اسم/موبايل العميل')" />
        </div>
        <div class="w-44">
            <flux:input wire:model.live="from" type="date" :label="__('من تاريخ')" dir="ltr" />
        </div>
        <div class="w-44">
            <flux:input wire:model.live="to" type="date" :label="__('إلى تاريخ')" dir="ltr" />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="statusFilter" :label="__('الحالة')">
                <flux:select.option value="">{{ __('كل الحالات') }}</flux:select.option>
                @foreach (App\Enums\SaleStatus::cases() as $s)
                    <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($this->hasActiveFilters)
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 transition dark:border-zinc-700"
        wire:loading.class="opacity-50" wire:target="search,from,to,statusFilter,resetFilters,gotoPage,nextPage,previousPage">
        <table class="w-full min-w-[640px] text-start text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('رقم الفاتورة') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('العميل') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('التسعير') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الحالة') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الإجمالي') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('التاريخ') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->sales as $sale)
                    @php
                        $color = match ($sale->status) {
                            App\Enums\SaleStatus::Completed => 'green',
                            App\Enums\SaleStatus::Cancelled => 'red',
                            default => 'zinc',
                        };
                    @endphp
                    <tr wire:key="sale-{{ $sale->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono" dir="ltr">#{{ $sale->invoice_number }}</td>
                        <td class="px-4 py-3">{{ $sale->customer?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $sale->price_type->label() }}</td>
                        <td class="px-4 py-3"><flux:badge size="sm" :color="$color">{{ $sale->status->label() }}</flux:badge></td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ $money($sale->total_amount) }} {{ $currency }}</td>
                        <td class="px-4 py-3 text-zinc-500" dir="ltr">{{ $sale->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <flux:button size="sm" variant="ghost" :href="route('sales.show', ['id' => $sale->id])" wire:navigate>
                                {{ $sale->status === App\Enums\SaleStatus::Draft ? __('فتح') : __('عرض') }}
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr wire:key="sales-empty">
                        <td colspan="7" class="px-4 py-10 text-center text-zinc-500">
                            {{ $this->hasActiveFilters ? __('مفيش فواتير مطابقة.') : __('لا توجد فواتير بعد.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->sales->links() }}
</div>