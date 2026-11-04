<?php

use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('الفواتير')] class extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $priceType = 'retail';

    public string $customerId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Sale::class);
    }

    public function updatedStatusFilter(): void
    {
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

    /** @return LengthAwarePaginator<int, Sale> */
    #[Computed]
    public function sales(): LengthAwarePaginator
    {
        return Sale::query()
            ->with('customer')
            ->when(SaleStatus::tryFrom($this->statusFilter), fn ($q, SaleStatus $s) => $q->where('status', $s->value))
            ->latest('id')
            ->paginate(20);
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

    <div class="w-48">
        <flux:select wire:model.live="statusFilter">
            <flux:select.option value="">{{ __('كل الحالات') }}</flux:select.option>
            @foreach (App\Enums\SaleStatus::cases() as $s)
                <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
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
                    <tr wire:key="sale-{{ $sale->id }}">
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
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-zinc-500">{{ __('لا توجد فواتير بعد.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->sales->links() }}
</div>