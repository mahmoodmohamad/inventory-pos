<?php

use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Purchases\PurchaseService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('استلام مشتريات')] class extends Component
{
    #[Url(as: 'supplier')]
    public string $supplierId = '';

    public string $search = '';

    /**
     * مفتاحها product_id.
     *
     * @var array<int, array{name: string, part_number: string, stock: string, avg: string, last: string, retail: string, qty: string, cost: string}>
     */
    public array $lines = [];

    public string $paymentMethod = 'cash';

    /** فاضي = آجل بالكامل */
    public string $paid = '';

    public string $supplierRef = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('create', \App\Models\Purchase::class);

        // مورد جاي من الـ URL لازم يكون نشط ومن نفس المحل
        if ($this->supplierId !== '' && ! $this->suppliers->contains('id', (int) $this->supplierId)) {
            $this->supplierId = '';
        }
    }

    // ---------- Lines ----------

    public function addProduct(int $productId): void
    {
        if (isset($this->lines[$productId])) {
            $current = $this->valid($this->lines[$productId]['qty'], 3, 9) ?? '0.000';
            $this->lines[$productId]['qty'] = bcadd($current, '1', 3);
            $this->search = '';

            return;
        }

        $product = Product::query()->where('is_active', true)->findOrFail($productId);
        $last = Money::norm($product->last_cost);
        $cost = bccomp($last, '0', 4) > 0 ? $last : Money::norm($product->avg_cost);

        $this->lines[$product->id] = [
            'name' => $product->name,
            'part_number' => (string) ($product->part_number ?? ''),
            'stock' => Money::norm($product->stock_qty),
            'avg' => Money::norm($product->avg_cost),
            'last' => $last,
            'retail' => Money::norm($product->retail_price),
            'qty' => '1',
            'cost' => bccomp($cost, '0', 4) > 0 ? rtrim(rtrim(bcadd($cost, '0', 4), '0'), '.') : '',
        ];

        $this->search = '';
    }

    /** Enter في خانة البحث: الباركود بالظبط الأول، وإلا لو نتيجة واحدة بس. */
    public function addFromSearch(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            return;
        }

        $exact = Product::query()->where('is_active', true)->where('barcode', $term)->first();
        $target = $exact ?? ($this->results->count() === 1 ? $this->results->first() : null);

        if ($target === null) {
            Flux::toast(variant: 'warning', text: __('اختار القطعة من النتايج.'));

            return;
        }

        $this->addProduct($target->id);
    }

    public function removeLine(int $productId): void
    {
        unset($this->lines[$productId]);
    }

    public function setPaymentMethod(string $method): void
    {
        if (PaymentMethod::tryFrom($method) !== null) {
            $this->paymentMethod = $method;
        }
    }

    public function payFull(): void
    {
        $this->paid = $this->total;
    }

    public function payNone(): void
    {
        $this->paid = '';
    }

    // ---------- Submit ----------

    public function submit(): void
    {
        $this->authorize('create', \App\Models\Purchase::class);

        if (! ctype_digit($this->supplierId) || $this->supplierId === '') {
            Flux::toast(variant: 'warning', text: __('اختار المورد.'));

            return;
        }

        if ($this->lines === []) {
            Flux::toast(variant: 'warning', text: __('ضيف بند واحد على الأقل.'));

            return;
        }

        $payload = [];

        foreach ($this->lines as $productId => $line) {
            $qty = $this->valid($line['qty'], 3, 9);
            $cost = $this->valid($line['cost'], 4, 8);

            if ($qty === null || $cost === null || bccomp($qty, '0', 3) <= 0) {
                Flux::toast(variant: 'danger', text: __('راجع الكمية والتكلفة في ":name".', ['name' => $line['name']]));

                return;
            }

            $payload[] = ['product_id' => $productId, 'quantity' => $qty, 'unit_cost' => $cost];
        }

        $paid = trim($this->paid) === '' ? '0' : trim($this->paid);
        $hasPayment = is_numeric($paid) && bccomp($paid, '0', 2) > 0;

        try {
            $purchase = app(PurchaseService::class)->receive(
                Auth::user(),
                (int) $this->supplierId,
                $payload,
                $hasPayment ? PaymentMethod::from($this->paymentMethod) : null,
                $paid,
                trim($this->supplierRef) !== '' ? trim($this->supplierRef) : null,
                trim($this->notes) !== '' ? trim($this->notes) : null,
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        } catch (ModelNotFoundException) {
            Flux::toast(variant: 'danger', text: __('المورد أو أحد المنتجات غير موجود أو موقوف.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('تم استلام المشتريات وتحديث المخزون.'));
        $this->redirectRoute('purchases.show', ['id' => $purchase->id], navigate: true);
    }

    // ---------- Computed ----------

    /** @return Collection<int, Supplier> */
    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Product> */
    #[Computed]
    public function results(): Collection
    {
        $term = trim($this->search);

        if ($term === '') {
            return new Collection;
        }

        return Product::query()->where('is_active', true)->search($term)->orderBy('name')->limit(8)->get();
    }

    #[Computed]
    public function total(): string
    {
        $total = '0.00';

        foreach ($this->lines as $line) {
            $total = Money::add($total, $this->lineTotal($line), 2);
        }

        return $total;
    }

    /** @return array{paid: string, due: string, invalid: bool, over: bool} */
    #[Computed]
    public function payment(): array
    {
        $input = trim($this->paid);
        $valid = $input === '' ? '0.00' : $this->valid($input, 2, 10);
        $paid = $valid ?? '0.00';
        $over = bccomp($paid, $this->total, 2) > 0;

        return [
            'paid' => $paid,
            'due' => $over ? '0.00' : Money::sub($this->total, $paid, 2),
            'invalid' => $valid === null,
            'over' => $over,
        ];
    }

    #[Computed]
    public function supplierBalance(): ?string
    {
        if (! ctype_digit($this->supplierId) || $this->supplierId === '') {
            return null;
        }

        return app(PurchaseService::class)->balance((int) $this->supplierId);
    }

    /** @return array<int, PaymentMethod> */
    #[Computed]
    public function methods(): array
    {
        return PaymentMethod::cases();
    }

    // ---------- Helpers (للعرض فقط، الخدمة بتعيد التحقق) ----------

    /** @return numeric-string|null */
    private function valid(string $value, int $scale, int $intDigits): ?string
    {
        $value = trim($value);

        if (preg_match('/\A\d{1,'.$intDigits.'}(?:\.\d{1,'.$scale.'})?\z/', $value) !== 1) {
            return null;
        }

        assert(is_numeric($value));

        return bcadd($value, '0', $scale);
    }

    /** @param array<string, string> $line */
    private function lineTotal(array $line): string
    {
        $qty = $this->valid($line['qty'], 3, 9);
        $cost = $this->valid($line['cost'], 4, 8);

        if ($qty === null || $cost === null) {
            return '0.00';
        }

        return Money::round(Money::mul($qty, $cost, 7), 2);
    }

    /**
     * معاينة المتوسط الجديد (نفس معادلة StockService، الرصيد السالب بيتحسب صفر).
     *
     * @param  array<string, string>  $line
     */
    private function previewAvg(array $line): ?string
    {
        $qty = $this->valid($line['qty'], 3, 9);
        $cost = $this->valid($line['cost'], 4, 8);

        if ($qty === null || $cost === null || bccomp($qty, '0', 3) <= 0) {
            return null;
        }

        $base = bccomp($line['stock'], '0', 3) > 0 ? $line['stock'] : '0';
        $den = bcadd($base, $qty, 3);
        $weighted = bcadd(bcmul($base, $line['avg'], 8), bcmul($qty, $cost, 8), 8);

        return Money::round(bcdiv($weighted, $den, 8), 4);
    }
    /** @param array<string, string> $line */
public function lineTotalFor(array $line): string
{
    return $this->lineTotal($line);
}

/** @param array<string, string> $line */
public function previewAvgFor(array $line): ?string
{
    return $this->previewAvg($line);
}
}; ?>

<div
    class="mx-auto max-w-6xl space-y-6"
    x-data
    @keydown.window.f2.prevent="$refs.search?.focus()"
    @keydown.window.f9.prevent="$wire.submit()"
>
    @php
        $money = fn ($n) => number_format((float) $n, 2);
        $qtyFmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
        $currency = auth()->user()->shop?->currency;
        $total = $this->total;
        $payment = $this->payment;
        $field = 'w-28 rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm dark:border-zinc-700';
        $hasLines = $lines !== [];
        $supplierBalance = $this->supplierBalance;
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('استلام مشتريات') }}</flux:heading>
            <flux:subheading>{{ __('الاستلام بيزوّد المخزون ويحسب متوسط التكلفة. الفاتورة بعد الحفظ مقفولة.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-right" :href="route('purchases.index')" wire:navigate>{{ __('كل المشتريات') }}</flux:button>
    </div>

    {{-- المورد --}}
    <div class="grid gap-4 rounded-xl border border-zinc-200 p-4 md:grid-cols-3 dark:border-zinc-700">
        <div class="space-y-2">
            <flux:select wire:model.live="supplierId" :label="__('المورد')">
                <flux:select.option value="">{{ __('اختر المورد') }}</flux:select.option>
                @foreach ($this->suppliers as $s)
                    <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($supplierBalance !== null)
                <p class="text-xs text-zinc-500">
                    {{ __('الرصيد الحالي للمورد') }}:
                    <span class="font-mono {{ bccomp($supplierBalance, '0', 2) > 0 ? 'text-amber-600' : '' }}" dir="ltr">{{ $money($supplierBalance) }} {{ $currency }}</span>
                </p>
            @endif
        </div>

        <flux:input wire:model="supplierRef" :label="__('رقم فاتورة المورد (اختياري)')" dir="ltr" />
        <flux:input wire:model="notes" :label="__('ملاحظات (اختياري)')" />
    </div>

    {{-- البحث --}}
    <div class="space-y-2">
        <flux:input
            x-ref="search"
            wire:model.live.debounce.250ms="search"
            wire:keydown.enter="addFromSearch"
            icon="magnifying-glass"
            clearable
            :placeholder="__('امسح الباركود أو اكتب رقم القطعة أو الاسم ثم Enter  (F2)')"
        />

        <div wire:loading.flex wire:target="search" class="text-xs text-zinc-500">{{ __('جاري البحث...') }}</div>

        @if ($this->results->isNotEmpty())
            <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($this->results as $p)
                    <button type="button" wire:key="res-{{ $p->id }}" wire:click="addProduct({{ $p->id }})"
                        class="flex w-full items-center justify-between gap-3 px-4 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <span>
                            <span class="font-medium">{{ $p->name }}</span>
                            <span class="block font-mono text-xs text-zinc-500" dir="ltr">{{ $p->part_number ?? '—' }}</span>
                        </span>
                        <span class="flex items-center gap-2">
                            <flux:badge size="sm">{{ $p->condition->label() }}</flux:badge>
                            <flux:badge size="sm" :color="(float) $p->stock_qty > 0 ? 'green' : 'red'">
                                <span dir="ltr">{{ $qtyFmt($p->stock_qty) }}</span>
                            </flux:badge>
                            @if (isset($lines[$p->id]))
                                <flux:badge size="sm" color="blue">{{ __('مضاف') }}</flux:badge>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- البنود --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[820px] text-start text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('المنتج') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الكمية') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('تكلفة الوحدة') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('المتوسط') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الإجمالي') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($lines as $pid => $line)
                    @php
                        $preview = $this->previewAvgFor($line);
                    @endphp
                    <tr wire:key="line-{{ $pid }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $line['name'] }}</div>
                            <div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $line['part_number'] ?: '—' }}</div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ __('الرصيد الحالي') }}: <span dir="ltr">{{ $qtyFmt($line['stock']) }}</span>
                                @if (bccomp($line['last'], '0', 4) > 0)
                                    · {{ __('آخر شراء') }}: <span dir="ltr">{{ $money($line['last']) }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" step="0.001" min="0" dir="ltr" class="{{ $field }}"
                                wire:model.live.debounce.300ms="lines.{{ $pid }}.qty">
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" step="0.0001" min="0" dir="ltr" class="{{ $field }}"
                                wire:model.live.debounce.300ms="lines.{{ $pid }}.cost">
                            @if (bccomp($line['retail'], '0', 2) > 0 && is_numeric($line['cost']) && bccomp($line['cost'], $line['retail'], 4) > 0)
                                <div class="mt-1 text-xs text-red-600">{{ __('أعلى من سعر القطاعي') }} (<span dir="ltr">{{ $money($line['retail']) }}</span>)</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($preview !== null)
                                <div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $money($line['avg']) }} ← <span class="font-semibold text-zinc-900 dark:text-white">{{ $money($preview) }}</span></div>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ $money($this->lineTotalFor($line)) }}</td>
                        <td class="px-4 py-3">
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $pid }})" :aria-label="__('حذف')" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-500">
                            <flux:icon.arrow-down-tray class="mx-auto mb-2 size-8 text-zinc-400" />
                            {{ __('لسه مفيش بنود. امسح باركود أو ابحث عن قطعة.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- الدفع والإجماليات --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text class="font-medium">{{ __('الدفع للمورد') }}</flux:text>

            <div class="flex flex-wrap gap-2">
                @foreach ($this->methods as $m)
                    <flux:button size="sm" wire:key="pm-{{ $m->value }}"
                        :variant="$paymentMethod === $m->value ? 'primary' : 'filled'"
                        wire:click="setPaymentMethod('{{ $m->value }}')">{{ $m->label() }}</flux:button>
                @endforeach
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input type="number" step="0.01" min="0" dir="ltr"
                        wire:model.live.debounce.300ms="paid"
                        :label="__('المبلغ المدفوع الآن')" placeholder="0.00" />
                </div>
                <flux:button variant="filled" wire:click="payFull">{{ __('كامل') }}</flux:button>
                <flux:button variant="ghost" wire:click="payNone">{{ __('آجل') }}</flux:button>
            </div>

            @if ($payment['invalid'])
                <flux:text class="text-sm text-red-600">{{ __('المبلغ المدفوع غير صحيح.') }}</flux:text>
            @elseif ($payment['over'])
                <flux:text class="text-sm text-red-600">{{ __('المدفوع أكبر من إجمالي المشتريات.') }}</flux:text>
            @endif
        </div>

        <div class="w-full space-y-2 rounded-xl border border-zinc-200 p-4 lg:ms-auto lg:max-w-sm dark:border-zinc-700">
            <div class="flex justify-between text-xs text-zinc-500">
                <span>{{ __('عدد البنود') }}: <span dir="ltr">{{ count($lines) }}</span></span>
            </div>

            <div class="flex justify-between border-b border-zinc-200 pb-2 text-lg font-semibold dark:border-zinc-700">
                <span>{{ __('الإجمالي') }}</span>
                <span class="font-mono" dir="ltr">{{ $money($total) }} {{ $currency }}</span>
            </div>

            <div class="flex justify-between text-sm">
                <span class="text-zinc-500">{{ __('المدفوع') }}</span>
                <span class="font-mono" dir="ltr">{{ $money($payment['paid']) }}</span>
            </div>

            @if (bccomp($payment['due'], '0', 2) > 0)
                <div class="flex justify-between text-sm font-medium text-amber-600">
                    <span>{{ __('يتسجل للمورد (آجل)') }}</span>
                    <span class="font-mono" dir="ltr">{{ $money($payment['due']) }}</span>
                </div>
            @endif

            <flux:button class="mt-2 w-full" variant="primary" icon="check" wire:click="submit"
                wire:loading.attr="disabled" wire:target="submit" :disabled="! $hasLines"
                wire:confirm="{{ __('هتستلم المشتريات وتزوّد المخزون. الفاتورة بعدها مقفولة. متأكد؟') }}">
                {{ __('استلام وحفظ') }} <span class="text-xs opacity-70">(F9)</span>
            </flux:button>
        </div>
    </div>
</div>