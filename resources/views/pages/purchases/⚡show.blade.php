<?php

use App\Models\Purchase;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('فاتورة مشتريات')] class extends Component
{
    #[Locked]
    public int $purchaseId = 0;

    public function mount(int $id): void
    {
        // الـ ShopScope: فاتورة محل تاني = 404
        $purchase = Purchase::query()->findOrFail($id);
        $this->authorize('view', $purchase);

        $this->purchaseId = $purchase->id;
    }

    #[Computed]
    public function purchase(): Purchase
    {
        return Purchase::query()
            ->with(['supplier', 'receiver', 'items.product', 'payments'])
            ->findOrFail($this->purchaseId);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    @php
        $purchase = $this->purchase;
        $money = fn ($n) => number_format((float) $n, 2);
        $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
        $currency = auth()->user()->shop?->currency;
        $paidNow = $purchase->payments->reduce(fn ($c, $p) => Money::add($c, $p->amount, 2), '0.00');
        $due = Money::sub($purchase->total_amount, $paidNow, 2);
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('فاتورة مشتريات') }} <span dir="ltr">#{{ $purchase->invoice_number }}</span></flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge size="sm" color="green">{{ __('مستلمة') }}</flux:badge>
                <a href="{{ route('suppliers.index', ['supplier' => $purchase->supplier_id]) }}" wire:navigate
                    class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ $purchase->supplier?->name }}</a>
                @if ($purchase->supplier_invoice_ref)
                    <flux:text class="text-zinc-500" dir="ltr">{{ __('فاتورة المورد') }}: {{ $purchase->supplier_invoice_ref }}</flux:text>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2 print:hidden">
            <flux:button variant="filled" icon="printer" x-data x-on:click="window.print()">{{ __('طباعة') }}</flux:button>
            <flux:button variant="ghost" icon="arrow-right" :href="route('purchases.index')" wire:navigate>{{ __('كل المشتريات') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-3 text-sm sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-zinc-500">{{ __('تاريخ الاستلام') }}</div>
            <div class="mt-1 font-medium" dir="ltr">{{ $purchase->received_at?->format('Y-m-d H:i') }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-zinc-500">{{ __('استلمها') }}</div>
            <div class="mt-1 font-medium">{{ $purchase->receiver?->name ?? '—' }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-zinc-500">{{ __('ملاحظات') }}</div>
            <div class="mt-1">{{ $purchase->notes ?: '—' }}</div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[640px] text-start text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('المنتج') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الكمية') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('تكلفة الوحدة') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الإجمالي') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($purchase->items as $item)
                    <tr wire:key="pi-{{ $item->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $item->product_name }}</div>
                            <div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $item->product?->part_number ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ $qty($item->quantity) }}</td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ number_format((float) $item->unit_cost, 4) }}</td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-2 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
            <flux:text class="font-medium">{{ __('الدفعات عند الاستلام') }}</flux:text>
            @forelse ($purchase->payments as $p)
                <div class="flex justify-between" wire:key="pp-{{ $p->id }}">
                    <span class="text-zinc-500">{{ $p->method->label() }}</span>
                    <span class="font-mono" dir="ltr">{{ $money($p->amount) }} {{ $currency }}</span>
                </div>
            @empty
                <p class="text-zinc-500">{{ __('اتسجّلت آجل بالكامل.') }}</p>
            @endforelse
        </div>

        <div class="w-full space-y-2 rounded-xl border border-zinc-200 p-4 lg:ms-auto lg:max-w-sm dark:border-zinc-700">
            <div class="flex justify-between border-b border-zinc-200 pb-2 text-lg font-semibold dark:border-zinc-700">
                <span>{{ __('الإجمالي') }}</span>
                <span class="font-mono" dir="ltr">{{ $money($purchase->total_amount) }} {{ $currency }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-zinc-500">{{ __('المدفوع عند الاستلام') }}</span>
                <span class="font-mono" dir="ltr">{{ $money($paidNow) }}</span>
            </div>
            @if (bccomp($due, '0', 2) > 0)
                <div class="flex justify-between text-sm font-medium text-amber-600">
                    <span>{{ __('آجل عند الاستلام') }}</span>
                    <span class="font-mono" dir="ltr">{{ $money($due) }}</span>
                </div>
            @endif
        </div>
    </div>
</div>