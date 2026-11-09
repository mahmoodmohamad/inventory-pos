<?php

use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الفاتورة')] class extends Component
{
    #[Locked]
    public int $saleId = 0;

    public string $search = '';

    public string $customerSearch = '';

    /** cash | card | transfer | credit */
    public string $paymentMethod = 'cash';

    /** فاضي = المبلغ كله (أو صفر لو آجل). */
    public string $amountPaid = '';

    public string $notes = '';

    public string $voidReason = '';

    public function mount(int $id): void
    {
        // الـ ShopScope: فاتورة محل تاني = 404
        $sale = Sale::query()->findOrFail($id);
        $this->authorize('view', $sale);

        $this->saleId = $sale->id;
        $this->notes = (string) ($sale->notes ?? '');
    }

    #[Computed]
    public function sale(): Sale
    {
        return Sale::query()->with(['customer', 'items.product', 'payments'])->findOrFail($this->saleId);
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

    /** @return Collection<int, Customer> */
    #[Computed]
    public function customers(): Collection
    {
        $term = trim($this->customerSearch);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return Customer::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like))
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function methods(): array
    {
        return [
            'cash' => __('كاش'),
            'card' => __('فيزا'),
            'transfer' => __('تحويل'),
            'credit' => __('آجل'),
        ];
    }

    /** ملخص البنود: عدد، كميات، نواقص، خصومات البنود. */
    #[Computed]
    public function summary(): array
    {
        $items = $this->sale->items;

        return [
            'lines' => $items->count(),
            'qty' => $items->reduce(fn ($c, $i) => bcadd($c, (string) $i->quantity, 3), '0'),
            'itemsDiscount' => $items->reduce(fn ($c, $i) => bcadd($c, (string) $i->discount_amount, 2), '0.00'),
            'short' => $items->filter(fn ($i) => $i->product && (float) $i->product->stock_qty < (float) $i->quantity)->count(),
        ];
    }

    /** المدفوع / الباقي للعميل / المتبقي (دين) حسب اللي مكتوب دلوقتي (للمسودة فقط). */
    #[Computed]
    public function payment(): array
    {
        $total = (string) $this->sale->total_amount;
        $input = trim($this->amountPaid);
        $valid = $input === '' || (preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/', $input) === 1);

        if ($input === '') {
            $paid = $this->paymentMethod === 'credit' ? '0' : $total;
        } else {
            $paid = $valid ? bcadd($input, '0', 2) : '0';
        }

        $diff = bcsub($paid, $total, 2);

        return [
            'paid' => $paid,
            'change' => bccomp($diff, '0', 2) > 0 ? $diff : '0.00',
            'due' => bccomp($diff, '0', 2) < 0 ? bcmul($diff, '-1', 2) : '0.00',
            'invalid' => ! $valid,
        ];
    }

    /**
     * ملخص الدفع للفاتورة المكتملة/الملغاة (مصدر واحد للشاشة والإيصال).
     *
     * @return array{paid: string, methods: string, remaining: string}
     */
    #[Computed]
    public function settled(): array
    {
        $sale = $this->sale;
        $paid = $sale->payments->reduce(fn ($c, $p) => bcadd($c, (string) $p->amount, 2), '0.00');

        return [
            'paid' => $paid,
            'methods' => $sale->payments->map(fn ($p) => $p->method->label())->unique()->implode('، ') ?: __('آجل'),
            'remaining' => bcsub((string) $sale->total_amount, $paid, 2),
        ];
    }

    #[Computed]
    public function checkoutAvailable(): bool
    {
        return method_exists(SaleService::class, 'complete')
            && method_exists(\App\Policies\SalePolicy::class, 'complete');
    }

    #[Computed]
    public function voidAvailable(): bool
    {
        return method_exists(SaleService::class, 'void')
            && method_exists(\App\Policies\SalePolicy::class, 'void');
    }

    #[Computed]
    public function customerEditAvailable(): bool
    {
        return method_exists(SaleService::class, 'setCustomer')
            && method_exists(SaleService::class, 'setPriceType');
    }

    private function ensureSupported(string $serviceMethod): void
    {
        abort_unless(method_exists(SaleService::class, $serviceMethod), 404);
    }

    /* ---------------------------------------------------------------- البنود */

    public function addProduct(int $productId): void
    {
        $this->run(function () use ($productId): void {
            app(SaleService::class)->addItem(Auth::user(), $this->sale, $productId);
            $this->search = '';
        });
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

    public function setQty(int $itemId, string $value): void
    {
        $this->run(fn () => app(SaleService::class)->setQuantity(Auth::user(), $this->sale, $itemId, $value));
    }

    public function setPrice(int $itemId, string $value): void
    {
        $this->run(fn () => app(SaleService::class)->setPrice(Auth::user(), $this->sale, $itemId, $value));
    }

    public function setItemDiscount(int $itemId, string $value): void
    {
        $this->run(fn () => app(SaleService::class)->setItemDiscount(Auth::user(), $this->sale, $itemId, $value));
    }

    public function removeItem(int $itemId): void
    {
        $this->run(fn () => app(SaleService::class)->removeItem(Auth::user(), $this->sale, $itemId));
    }

    public function setDiscount(string $value): void
    {
        $this->run(fn () => app(SaleService::class)->setDiscount(Auth::user(), $this->sale, $value));
    }

    /* ---------------------------------------------------------------- العميل والتسعير */

    public function selectCustomer(int $customerId): void
    {
        $this->ensureSupported('setCustomer');

        $this->run(function () use ($customerId): void {
            app(SaleService::class)->setCustomer(Auth::user(), $this->sale, $customerId);
            $this->customerSearch = '';
        });
    }

    public function clearCustomer(): void
    {
        $this->ensureSupported('setCustomer');

        $this->run(fn () => app(SaleService::class)->setCustomer(Auth::user(), $this->sale, null));
    }

    /** تغيير الشريحة (قطاعي/جملة) بيعيد تسعير البنود اللي سعرها ماتعدلش يدوي. */
    public function setPriceType(string $value): void
    {
        $this->ensureSupported('setPriceType');

        $type = PriceType::tryFrom($value);

        if ($type === null || $type === $this->sale->price_type) {
            return;
        }

        $this->run(fn () => app(SaleService::class)->setPriceType(Auth::user(), $this->sale, $type));
    }

    public function setPaymentMethod(string $method): void
    {
        if (array_key_exists($method, $this->methods)) {
            $this->paymentMethod = $method;
            unset($this->payment);
        }
    }

    public function fillFullAmount(): void
    {
        $this->amountPaid = (string) $this->sale->total_amount;
    }

    /* ---------------------------------------------------------------- إكمال / إلغاء */

    public function complete(): void
    {
        $this->finalize();
    }

    public function completeAndPrint(): void
    {
        if ($this->finalize()) {
            $this->dispatch('print-receipt');
        }
    }

    private function finalize(): bool
    {
        $this->ensureSupported('complete');

        $sale = $this->sale;
        $this->authorize('complete', $sale);

        if ($sale->status !== SaleStatus::Draft) {
            return false;
        }

        if ($sale->items->isEmpty()) {
            Flux::toast(variant: 'warning', text: __('الفاتورة فاضية. ضيف بند واحد على الأقل.'));

            return false;
        }

        $payment = $this->payment;

        if ($payment['invalid']) {
            Flux::toast(variant: 'danger', text: __('المبلغ المدفوع غير صحيح.'));

            return false;
        }

        // الباقي (فكة) مسموح في الكاش بس.
        if ($this->paymentMethod !== 'cash' && bccomp($payment['change'], '0', 2) > 0) {
            Flux::toast(variant: 'danger', text: __('المبلغ المدفوع أكبر من إجمالي الفاتورة.'));

            return false;
        }

        // أي مبلغ متبقي = دين على العميل، لازم يكون معروف.
        if (bccomp($payment['due'], '0', 2) > 0 && $sale->customer_id === null) {
            Flux::toast(variant: 'warning', text: __('فيه مبلغ متبقي. اختار عميل عشان يتسجل عليه.'));

            return false;
        }

        try {
            app(SaleService::class)->complete(
                Auth::user(),
                $sale,
                $this->resolveMethod(),
                $payment['paid'],
                trim($this->notes) !== '' ? trim($this->notes) : null,
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            unset($this->sale, $this->summary, $this->payment, $this->settled);

            return false;
        }

        $this->amountPaid = '';
        unset($this->sale, $this->payment, $this->summary, $this->settled);
        Flux::toast(variant: 'success', text: __('تم إكمال الفاتورة وخصم المخزون.'));

        return true;
    }

    private function resolveMethod(): ?PaymentMethod
    {
        return match ($this->paymentMethod) {
            'cash' => PaymentMethod::Cash,
            'card' => PaymentMethod::Card,
            'transfer' => PaymentMethod::BankTransfer,
            default => null, // credit
        };
    }

    public function voidSale(): void
    {
        $this->ensureSupported('void');

        $this->authorize('void', $this->sale);

        if (trim($this->voidReason) === '') {
            Flux::toast(variant: 'warning', text: __('اكتب سبب الإلغاء.'));

            return;
        }

        $this->run(function (): void {
            app(SaleService::class)->void(Auth::user(), $this->sale, trim($this->voidReason));
            Flux::toast(variant: 'success', text: __('تم إلغاء الفاتورة ورجوع المخزون.'));
        });

        $this->voidReason = '';
        Flux::modal('void-sale')->close();
    }

    public function discard(): void
    {
        try {
            app(SaleService::class)->discardDraft(Auth::user(), $this->sale);
        } catch (\DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->redirectRoute('sales.index', navigate: true);
    }

    private function run(\Closure $action): void
    {
        try {
            $action();
        } catch (AuthorizationException) {
            Flux::toast(variant: 'danger', text: __('مفيش عندك صلاحية لتنفيذ العملية دي.'));
        } catch (\InvalidArgumentException|\DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        unset($this->sale, $this->summary, $this->payment, $this->settled);
    }
}; ?>

<div
    class="space-y-6"
    x-data
    @print-receipt.window="$nextTick(() => window.print())"
    @keydown.window.f2.prevent="$refs.search?.focus()"
    @keydown.window.f9.prevent="$wire.complete()"
>
    @php
        $sale = $this->sale;
        $isDraft = $sale->status === SaleStatus::Draft;
        $isCompleted = $sale->status === SaleStatus::Completed;
        $isVoided = $sale->status === SaleStatus::Cancelled;
        $money = fn ($n) => number_format((float) $n, 2);
        $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
        $shop = auth()->user()->shop;
        $currency = $shop?->currency;
        $canOverride = Gate::allows('override-price');
        $field = 'w-24 rounded-lg border border-zinc-300 bg-transparent px-2 py-1 text-sm dark:border-zinc-700';
        $summary = $this->summary;
        $payment = $this->payment;
        $methods = $this->methods;
        $settled = ($isCompleted || $isVoided) ? $this->settled : null;
        $editTargets = 'addProduct,addFromSearch,setQty,setPrice,setItemDiscount,removeItem,setDiscount,selectCustomer,clearCustomer,setPriceType';
    @endphp

    {{-- طباعة: نخفي كل الواجهة ونطبع الإيصال بس --}}
    <style>
    @media print {
        /* 1. Hide the UI (sidebar, headers, tables, buttons) */
        .print\:hidden, nav, header, aside {
            display: none !important;
        }

        /* 2. Reset body and main wrappers so they don't add margins */
        body, html {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: auto !important;
        }

        /* 3. Force the receipt to be visible and reset its positioning */
        .receipt {
            display: block !important;
            visibility: visible !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 72mm !important; /* 80mm paper minus 4mm margins */
            margin: 0 !important;
            padding: 0 2mm !important;
            font-family: ui-monospace, "Courier New", monospace !important;
            font-size: 11px !important;
            line-height: 1.3 !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* 4. Ensure tables inside receipt behave */
        .receipt table { width: 100%; border-collapse: collapse; }
        .receipt td, .receipt th { padding: 2px 0; vertical-align: top; }
        .receipt .num { direction: ltr; unicode-bidi: embed; white-space: nowrap; }

        /* 5. Setup the page for thermal printer */
        @page {
            size: 80mm auto;
            margin: 2mm;
        }
    }
</style>

    <div class="space-y-6 print:hidden">

        {{-- الرأس --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('فاتورة') }} <span dir="ltr">#{{ $sale->invoice_number }}</span></flux:heading>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$isCompleted ? 'green' : ($isVoided ? 'red' : 'zinc')">{{ $sale->status->label() }}</flux:badge>
                    <flux:badge size="sm" color="indigo">{{ $sale->price_type->label() }}</flux:badge>
                    <flux:text>{{ $sale->customer?->name ?? __('بدون عميل') }}</flux:text>
                    @if ($sale->customer?->phone)
                        <flux:text class="text-zinc-500" dir="ltr">{{ $sale->customer->phone }}</flux:text>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- مؤشر الحفظ: بيظهر طول ما فيه تعديل شغال --}}
                @if ($isDraft)
                    <span wire:loading.flex wire:target="{{ $editTargets }}" class="items-center gap-1 text-xs text-zinc-500">
                        <flux:icon.loading variant="micro" /> {{ __('جاري الحفظ...') }}
                    </span>
                @endif

                @if ($isCompleted)
                    <flux:button variant="filled" icon="printer" x-on:click="window.print()">{{ __('طباعة') }}</flux:button>
                @endif
                <flux:button variant="ghost" icon="arrow-right" :href="route('sales.index')" wire:navigate>{{ __('كل الفواتير') }}</flux:button>
            </div>
        </div>

        @if ($isVoided)
            <flux:callout variant="danger" icon="x-circle" :heading="__('الفاتورة دي ملغاة')" :text="$sale->cancellation_reason" />
        @endif

        @if ($isDraft)
            @if ($this->customerEditAvailable)
                {{-- العميل + الشريحة --}}
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">{{ __('العميل') }}</flux:text>

                        @if ($sale->customer)
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ $sale->customer->name }}</span>
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearCustomer"
                                    wire:confirm="{{ __('هتشيل العميل من الفاتورة. متأكد؟') }}"
                                    :aria-label="__('إزالة العميل')" />
                            </div>
                        @else
                            <flux:input wire:model.live.debounce.300ms="customerSearch" icon="user" size="sm" clearable
                                :placeholder="__('ابحث بالاسم أو الموبايل')" />

                            @if ($this->customers->isNotEmpty())
                                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                                    @foreach ($this->customers as $c)
                                        <button type="button" wire:key="cust-{{ $c->id }}" wire:click="selectCustomer({{ $c->id }})"
                                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                            <span>{{ $c->name }}</span>
                                            <span class="font-mono text-xs text-zinc-500" dir="ltr">{{ $c->phone }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">{{ __('شريحة السعر') }}</flux:text>
                        <div class="flex flex-wrap gap-2">
                            @foreach (PriceType::cases() as $type)
                                <flux:button size="sm" wire:key="pt-{{ $type->value }}"
                                    :variant="$sale->price_type === $type ? 'primary' : 'filled'"
                                    @if ($sale->price_type !== $type && $sale->items->isNotEmpty())
                                        wire:confirm="{{ __('تغيير الشريحة هيعيد تسعير البنود. متأكد؟') }}"
                                    @endif
                                    wire:click="setPriceType('{{ $type->value }}')">
                                    {{ $type->label() }}
                                </flux:button>
                            @endforeach
                        </div>
                        <flux:text class="text-xs text-zinc-500">{{ __('تغيير الشريحة بيعيد تسعير البنود اللي سعرها ماتعدلش يدويًا.') }}</flux:text>
                    </div>
                </div>
            @endif

            {{-- البحث / الباركود --}}
            <div class="space-y-2">
                <flux:input
                    x-ref="search"
                    wire:model.live.debounce.250ms="search"
                    wire:keydown.enter="addFromSearch"
                    icon="magnifying-glass"
                    :placeholder="__('امسح الباركود أو اكتب رقم القطعة أو الاسم ثم Enter  (F2)')"
                    autofocus
                    clearable
                />

                <div wire:loading.flex wire:target="search" class="text-xs text-zinc-500">{{ __('جاري البحث...') }}</div>

                @if ($this->results->isNotEmpty())
                    <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($this->results as $p)
                            @php
                                $tierPrice = $sale->price_type === PriceType::Wholesale ? $p->wholesale_price : $p->retail_price;
                            @endphp
                            <button type="button" wire:key="res-{{ $p->id }}" wire:click="addProduct({{ $p->id }})"
                                class="flex w-full items-center justify-between gap-3 px-4 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <span>
                                    <span class="font-medium">{{ $p->name }}</span>
                                    <span class="block font-mono text-xs text-zinc-500" dir="ltr">{{ $p->part_number ?? '—' }}</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <flux:badge size="sm">{{ $p->condition->label() }}</flux:badge>
                                    <flux:badge size="sm" :color="(float) $p->stock_qty > 0 ? 'green' : 'red'">
                                        <span dir="ltr">{{ $qty($p->stock_qty) }}</span>
                                    </flux:badge>
                                    <span class="font-mono" dir="ltr">{{ $money($tierPrice) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- جدول البنود --}}
        <div class="overflow-x-auto rounded-xl border border-zinc-200 transition dark:border-zinc-700"
            wire:loading.class="opacity-60" wire:target="{{ $editTargets }}">
            <table class="w-full min-w-[760px] text-start text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ __('المنتج') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('الكمية') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('السعر') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('خصم') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('الإجمالي') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($sale->items as $item)
                        @php
                            $short = $item->product && (float) $item->product->stock_qty < (float) $item->quantity;
                            $changed = bccomp((string) $item->unit_price, (string) $item->original_unit_price, 2) !== 0;
                        @endphp
                        <tr wire:key="item-{{ $item->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $item->product_name }}</div>
                                <div class="font-mono text-xs text-zinc-500" dir="ltr">{{ $item->part_number ?? '—' }}</div>
                                @if ($isDraft && $short)
                                    <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ __('الرصيد غير كافٍ (هيتباع على السالب)') }}</flux:badge>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if ($isDraft)
                                    <input type="number" step="0.001" min="0" dir="ltr" class="{{ $field }}"
                                        value="{{ $qty($item->quantity) }}"
                                        wire:change="setQty({{ $item->id }}, $event.target.value)">
                                @else
                                    <span class="font-mono" dir="ltr">{{ $qty($item->quantity) }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if ($isDraft && $canOverride)
                                    <input type="number" step="0.01" min="0" dir="ltr" class="{{ $field }}"
                                        value="{{ $item->unit_price }}"
                                        wire:change="setPrice({{ $item->id }}, $event.target.value)">
                                @else
                                    <span class="font-mono" dir="ltr">{{ $money($item->unit_price) }}</span>
                                @endif
                                @if ($changed)
                                    <div class="text-xs text-amber-600">{{ __('السعر الأصلي') }}: <span dir="ltr">{{ $money($item->original_unit_price) }}</span></div>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if ($isDraft)
                                    <input type="number" step="0.01" min="0" dir="ltr" class="{{ $field }}"
                                        value="{{ $item->discount_amount }}"
                                        wire:change="setItemDiscount({{ $item->id }}, $event.target.value)">
                                @else
                                    <span class="font-mono" dir="ltr">{{ $money($item->discount_amount) }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 font-mono" dir="ltr">{{ $money($item->line_total) }}</td>

                            <td class="px-4 py-3">
                                @if ($isDraft)
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeItem({{ $item->id }})" :aria-label="__('حذف')" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr wire:key="items-empty">
                            <td colspan="6" class="px-4 py-10 text-center text-zinc-500">{{ __('لسه مفيش بنود. امسح باركود أو ابحث عن قطعة.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- الدفع / الملاحظات --}}
            <div class="space-y-4">
                @if ($isDraft)
                    <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">{{ __('الدفع') }}</flux:text>

                        <div class="flex flex-wrap gap-2">
                            @foreach ($methods as $key => $label)
                                <flux:button size="sm" wire:key="pm-{{ $key }}"
                                    :variant="$paymentMethod === $key ? 'primary' : 'filled'"
                                    wire:click="setPaymentMethod('{{ $key }}')">
                                    {{ $label }}
                                </flux:button>
                            @endforeach
                        </div>

                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <flux:input type="number" step="0.01" min="0" dir="ltr"
                                    wire:model.live.debounce.300ms="amountPaid"
                                    :label="__('المبلغ المدفوع')"
                                    :placeholder="$paymentMethod === 'credit' ? '0.00' : $money($sale->total_amount)" />
                            </div>
                            <flux:button variant="filled" wire:click="fillFullAmount">{{ __('المبلغ كامل') }}</flux:button>
                        </div>

                        @if ($payment['invalid'])
                            <flux:text class="text-sm text-red-600">{{ __('المبلغ المدفوع غير صحيح.') }}</flux:text>
                        @endif

                        <flux:textarea wire:model.blur="notes" rows="2" :label="__('ملاحظات')" />
                    </div>
                @else
                    {{-- ملخص الدفع (مرة واحدة بس) --}}
                    <div class="space-y-2 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">{{ __('طريقة الدفع') }}</span>
                            <span>{{ $settled['methods'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500">{{ __('المدفوع') }}</span>
                            <span class="font-mono" dir="ltr">{{ $money($settled['paid']) }} {{ $currency }}</span>
                        </div>
                        @if (bccomp($settled['remaining'], '0', 2) > 0)
                            <div class="flex justify-between text-amber-600">
                                <span>{{ __('متبقي على العميل') }}</span>
                                <span class="font-mono" dir="ltr">{{ $money($settled['remaining']) }} {{ $currency }}</span>
                            </div>
                        @endif
                        @if ($sale->completed_at)
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ __('تاريخ الإكمال') }}</span>
                                <span dir="ltr">{{ $sale->completed_at->format('Y-m-d H:i') }}</span>
                            </div>
                        @endif
                        @if ($sale->notes)
                            <div class="border-t border-zinc-200 pt-2 text-zinc-600 dark:border-zinc-700">{{ $sale->notes }}</div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- الإجماليات --}}
            <div class="w-full space-y-2 rounded-xl border border-zinc-200 p-4 lg:ms-auto lg:max-w-sm dark:border-zinc-700">
                <div class="flex justify-between text-xs text-zinc-500">
                    <span>{{ __('عدد البنود') }}: <span dir="ltr">{{ $summary['lines'] }}</span></span>
                    <span>{{ __('إجمالي الكميات') }}: <span dir="ltr">{{ $qty($summary['qty']) }}</span></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-zinc-500">{{ __('المجموع') }}</span>
                    <span class="font-mono" dir="ltr">{{ $money($sale->subtotal) }} {{ $currency }}</span>
                </div>

                @if (bccomp($summary['itemsDiscount'], '0', 2) > 0)
                    <div class="flex justify-between text-sm text-zinc-500">
                        <span>{{ __('منها خصم البنود') }}</span>
                        <span class="font-mono" dir="ltr">{{ $money($summary['itemsDiscount']) }}</span>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-3">
                    <span class="text-zinc-500">{{ __('خصم الفاتورة') }}</span>
                    @if ($isDraft)
                        <input type="number" step="0.01" min="0" dir="ltr" class="{{ $field }}"
                            value="{{ $sale->discount_amount }}"
                            wire:change="setDiscount($event.target.value)">
                    @else
                        <span class="font-mono" dir="ltr">{{ $money($sale->discount_amount) }}</span>
                    @endif
                </div>

                <div class="flex justify-between border-t border-zinc-200 pt-2 text-lg font-semibold dark:border-zinc-700">
                    <span>{{ __('الإجمالي') }}</span>
                    <span class="font-mono" dir="ltr">{{ $money($sale->total_amount) }} {{ $currency }}</span>
                </div>

                @if ($isDraft && $sale->items->isNotEmpty())
                    <div class="space-y-1 border-t border-dashed border-zinc-200 pt-2 text-sm dark:border-zinc-700">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">{{ __('المدفوع') }}</span>
                            <span class="font-mono" dir="ltr">{{ $money($payment['paid']) }}</span>
                        </div>
                        @if (bccomp($payment['change'], '0', 2) > 0)
                            <div class="flex justify-between font-medium text-green-600">
                                <span>{{ __('الباقي للعميل') }}</span>
                                <span class="font-mono" dir="ltr">{{ $money($payment['change']) }}</span>
                            </div>
                        @endif
                        @if (bccomp($payment['due'], '0', 2) > 0)
                            <div class="flex justify-between font-medium text-amber-600">
                                <span>{{ __('متبقي (دين)') }}</span>
                                <span class="font-mono" dir="ltr">{{ $money($payment['due']) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- الإجراءات (للمسودة فقط) --}}
        @if ($isDraft)
            @if ($summary['short'] > 0)
                <flux:callout variant="warning" icon="exclamation-triangle"
                    :text="__('فيه :n بند رصيده غير كافٍ. الإكمال هيخلي المخزون سالب.', ['n' => $summary['short']])" />
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button variant="danger" icon="trash" wire:click="discard" wire:confirm="{{ __('هتحذف المسودة نهائيًا. متأكد؟') }}">
                    {{ __('حذف المسودة') }}
                </flux:button>

                @if ($this->checkoutAvailable)
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button variant="filled" icon="printer" wire:click="completeAndPrint"
                            wire:loading.attr="disabled" wire:target="complete,completeAndPrint"
                            :disabled="$sale->items->isEmpty()">
                            {{ __('إكمال وطباعة') }}
                        </flux:button>

                        <flux:button variant="primary" icon="check" wire:click="complete"
                            wire:loading.attr="disabled" wire:target="complete,completeAndPrint"
                            :disabled="$sale->items->isEmpty()"
                            wire:confirm="{{ $summary['short'] > 0 ? __('فيه بنود رصيدها غير كافٍ وهتباع على السالب. تكمل؟') : __('هتكمل الفاتورة وتخصم المخزون. متأكد؟') }}">
                            {{ __('إكمال الفاتورة') }} <span class="text-xs opacity-70">(F9)</span>
                        </flux:button>
                    </div>
                @endif
            </div>
        @elseif ($isCompleted && $this->voidAvailable)
            @can('void', $sale)
                <div class="flex justify-end">
                    <flux:modal.trigger name="void-sale">
                        <flux:button variant="danger" icon="x-circle">{{ __('إلغاء الفاتورة') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:modal name="void-sale" class="w-full max-w-md">
                    <form wire:submit="voidSale" class="space-y-5">
                        <flux:heading size="lg">{{ __('إلغاء الفاتورة') }}</flux:heading>
                        <flux:textarea wire:model="voidReason" rows="3" :label="__('سبب الإلغاء')" required />
                        <div class="flex justify-end gap-2">
                            <flux:modal.close><flux:button type="button" variant="ghost">{{ __('رجوع') }}</flux:button></flux:modal.close>
                            <flux:button type="submit" variant="danger">{{ __('تأكيد الإلغاء') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @endcan
        @endif
    </div>

    {{-- ============ إيصال الطباعة (80mm) — ظاهر وقت الطباعة بس ============ --}}
    @if ($isCompleted)
        <div class="receipt hidden print:block" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
            style="font-size:12px;line-height:1.5;color:#000">
            <div style="text-align:center;font-weight:700;font-size:14px">{{ $shop?->name }}</div>
            @if ($shop?->phone)<div style="text-align:center" dir="ltr">{{ $shop->phone }}</div>@endif
            <div style="text-align:center;margin-bottom:6px">{{ $shop?->address }}</div>

            <div style="border-top:1px dashed #000;padding-block:4px">
                <div>{{ __('فاتورة') }} #<span dir="ltr">{{ $sale->invoice_number }}</span></div>
                <div dir="ltr" style="text-align:start">{{ $sale->completed_at?->format('Y-m-d H:i') }}</div>
                <div>{{ __('العميل') }}: {{ $sale->customer?->name ?? __('بدون عميل') }}</div>
            </div>

            <table style="width:100%;border-top:1px dashed #000;border-bottom:1px dashed #000;border-collapse:collapse">
                @foreach ($sale->items as $item)
                    <tr wire:key="rc-{{ $item->id }}">
                        <td style="padding-block:2px">
                            {{ $item->product_name }}
                            <div dir="ltr" style="font-size:11px;text-align:start">
                                {{ $qty($item->quantity) }} × {{ $money($item->unit_price) }}
                                @if (bccomp((string) $item->discount_amount, '0', 2) > 0) (-{{ $money($item->discount_amount) }}) @endif
                            </div>
                        </td>
                        <td dir="ltr" style="text-align:end;vertical-align:top">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </table>

            <div style="padding-block:4px">
                @if (bccomp((string) $sale->discount_amount, '0', 2) > 0)
                    <div style="display:flex;justify-content:space-between"><span>{{ __('خصم الفاتورة') }}</span><span dir="ltr">{{ $money($sale->discount_amount) }}</span></div>
                @endif
                <div style="display:flex;justify-content:space-between;font-weight:700;font-size:14px">
                    <span>{{ __('الإجمالي') }}</span><span dir="ltr">{{ $money($sale->total_amount) }} {{ $currency }}</span>
                </div>
                <div style="display:flex;justify-content:space-between"><span>{{ __('المدفوع') }} ({{ $settled['methods'] }})</span><span dir="ltr">{{ $money($settled['paid']) }}</span></div>
                @if (bccomp($settled['remaining'], '0', 2) > 0)
                    <div style="display:flex;justify-content:space-between"><span>{{ __('متبقي') }}</span><span dir="ltr">{{ $money($settled['remaining']) }}</span></div>
                @endif
            </div>
            <div style="text-align:center;border-top:1px dashed #000;padding-top:6px">{{ __('شكرًا لتعاملكم معنا') }}</div>
        </div>
    @endif
</div>