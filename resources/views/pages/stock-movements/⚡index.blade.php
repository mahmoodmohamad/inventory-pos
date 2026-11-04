<?php

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('حركات المخزون')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $type = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockMovement::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, StockMovement>
     */
    #[Computed]
    public function movements(): LengthAwarePaginator
    {
        $type = StockMovementType::tryFrom($this->type);

        return StockMovement::query()
            ->with(['product', 'user'])
            ->when($type !== null, fn ($q) => $q->where('type', $type->value))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas(
                'product',
                fn ($p) => $p->search($this->search),
            ))
            ->latest('id')
            ->paginate(20);
    }

    /** @return array<int, StockMovementType> */
    #[Computed]
    public function types(): array
    {
        return StockMovementType::cases();
    }
}; ?>

<div class="space-y-6">
    @php
        $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
    @endphp

    <div>
        <flux:heading size="xl">{{ __('حركات المخزون') }}</flux:heading>
        <flux:subheading>{{ __('دفتر الحركة الكامل (لا يمكن تعديله أو حذفه)') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('ابحث بالمنتج أو رقم القطعة أو الباركود')" />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="type">
                <flux:select.option value="">{{ __('كل الأنواع') }}</flux:select.option>
                @foreach ($this->types as $t)
                    <flux:select.option value="{{ $t->value }}">{{ $t->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[900px] text-start text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الوقت') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('المنتج') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('النوع') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الكمية') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الرصيد بعد') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('تكلفة الوحدة') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('المتوسط بعد') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('بواسطة') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('ملاحظات') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->movements as $m)
                    @php $in = (float) $m->quantity > 0; @endphp
                    <tr wire:key="mv-{{ $m->id }}">
                        <td class="px-4 py-3 text-zinc-500" dir="ltr">{{ $m->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $m->product?->name ?? '—' }}</div>
                            <div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $m->product?->part_number }}</div>
                        </td>
                        <td class="px-4 py-3"><flux:badge size="sm">{{ $m->type->label() }}</flux:badge></td>
                        <td class="px-4 py-3 font-mono {{ $in ? 'text-emerald-500' : 'text-red-500' }}" dir="ltr">
                            {{ $in ? '+' : '' }}{{ $qty($m->quantity) }}
                        </td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ $qty($m->qty_after) }}</td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ number_format((float) $m->unit_cost, 2) }}</td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ number_format((float) $m->avg_cost_after, 2) }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $m->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $m->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-zinc-500">{{ __('لا توجد حركات.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->movements->links() }}
</div>