<?php

use App\Enums\PaymentMethod;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Purchases\PurchaseService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('الموردين')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: false)]
    public string $search = '';

    /** all | owing | settled | inactive */
    #[Url(as: 'f')]
    public string $filter = 'all';

    #[Url(as: 'supplier')]
    public string $selectedId = '';

    // نموذج المورد
    public ?int $editingId = null;
    public string $name = '';
    public string $phone = '';
    public string $alternate_phone = '';
    public string $email = '';
    public string $address = '';
    public string $notes = '';
    public bool $is_active = true;

    // نموذج السداد
    public string $payAmount = '';
    public string $payMethod = 'cash';
    public string $payNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Supplier::class);

        if (! in_array($this->filter, ['all', 'owing', 'settled', 'inactive'], true)) {
            $this->filter = 'all';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $value): void
    {
        if (in_array($value, ['all', 'owing', 'settled', 'inactive'], true)) {
            $this->filter = $value;
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
        $this->resetPage();
    }

    public function selectSupplier(int $id): void
    {
        $supplier = Supplier::query()->findOrFail($id);
        $this->authorize('view', $supplier);

        $this->selectedId = (string) $supplier->id;
        unset($this->selected, $this->statement);
    }

    public function clearSupplier(): void
    {
        $this->selectedId = '';
        unset($this->selected, $this->statement);
    }

    // ---------- Supplier CRUD ----------

    public function openCreate(): void
    {
        $this->authorize('create', Supplier::class);

        $this->reset('editingId', 'name', 'phone', 'alternate_phone', 'email', 'address', 'notes');
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('supplier-form')->show();
    }

    public function openEdit(int $id): void
    {
        $supplier = Supplier::query()->findOrFail($id);
        $this->authorize('update', $supplier);

        $this->resetValidation();
        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->phone = $supplier->phone ?? '';
        $this->alternate_phone = $supplier->alternate_phone ?? '';
        $this->email = $supplier->email ?? '';
        $this->address = $supplier->address ?? '';
        $this->notes = $supplier->notes ?? '';
        $this->is_active = $supplier->is_active;
        Flux::modal('supplier-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $data = [
            'name' => trim($this->name),
            'phone' => filled($this->phone) ? trim($this->phone) : null,
            'alternate_phone' => filled($this->alternate_phone) ? trim($this->alternate_phone) : null,
            'email' => filled($this->email) ? trim($this->email) : null,
            'address' => filled($this->address) ? trim($this->address) : null,
            'notes' => filled($this->notes) ? trim($this->notes) : null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId === null) {
            $this->authorize('create', Supplier::class);
            $supplier = Supplier::query()->create($data);
            $this->selectedId = (string) $supplier->id;
        } else {
            $supplier = Supplier::query()->findOrFail($this->editingId);
            $this->authorize('update', $supplier);
            $supplier->update($data);
        }

        Flux::modal('supplier-form')->close();
        $this->reset('editingId');
        unset($this->suppliers, $this->selected, $this->stats);
        Flux::toast(variant: 'success', text: __('تم الحفظ.'));
    }

    public function toggleActive(int $id): void
    {
        $supplier = Supplier::query()->findOrFail($id);
        $this->authorize('update', $supplier);

        $supplier->update(['is_active' => ! $supplier->is_active]);
        unset($this->suppliers, $this->selected, $this->stats);
        Flux::toast(text: $supplier->is_active ? __('تم تفعيل المورد.') : __('تم إيقاف المورد.'));
    }

    public function delete(int $id): void
    {
        $supplier = Supplier::query()->findOrFail($id);
        $this->authorize('delete', $supplier);

        if (Money::cmp(app(PurchaseService::class)->balance($supplier->id), '0', 2) !== 0) {
            Flux::toast(variant: 'danger', text: __('لا يمكن حذف مورد له رصيد. سدّد المتبقي أولًا.'));

            return;
        }

        $supplier->delete();
        $this->clearSupplier();
        unset($this->suppliers, $this->stats);
        Flux::toast(text: __('تم الحذف.'));
    }

    // ---------- Payment ----------

    public function openPay(int $id): void
    {
        $supplier = Supplier::query()->findOrFail($id);
        $this->authorize('update', $supplier);

        $this->reset('payAmount', 'payNotes');
        $this->payMethod = 'cash';
        $this->resetValidation();
        $this->selectedId = (string) $supplier->id;
        unset($this->selected);
        Flux::modal('supplier-pay')->show();
    }

    public function fillPayFull(): void
    {
        $selected = $this->selected;

        if ($selected !== null) {
            $this->payAmount = $this->balanceOf($selected);
        }
    }

    public function savePay(): void
    {
        $selected = $this->selected;
        abort_if($selected === null, 404);

        $this->validate([
            'payAmount' => ['required', 'regex:/\A\d{1,10}(?:\.\d{1,2})?\z/'],
            'payMethod' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'payNotes' => ['nullable', 'string', 'max:255'],
        ], [
            'payAmount.regex' => __('المبلغ بحد أقصى منزلتين عشريتين.'),
        ]);

        try {
            app(PurchaseService::class)->pay(
                Auth::user(),
                $selected->id,
                trim($this->payAmount),
                PaymentMethod::from($this->payMethod),
                filled($this->payNotes) ? trim($this->payNotes) : null,
            );
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->addError('payAmount', $e->getMessage());

            return;
        }

        Flux::modal('supplier-pay')->close();
        $this->reset('payAmount', 'payNotes');
        unset($this->suppliers, $this->selected, $this->statement, $this->stats);
        Flux::toast(variant: 'success', text: __('تم تسجيل الدفعة.'));
    }

    // ---------- Data ----------

    /** @return LengthAwarePaginator<int, Supplier> */
    #[Computed]
    public function suppliers(): LengthAwarePaginator
    {
        $query = $this->withTotals(Supplier::query());
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $b) => $b
                ->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('alternate_phone', 'like', $like)
                ->orWhere('email', 'like', $like));
        }

        $balance = '(coalesce(bought_total, 0) - coalesce(paid_total, 0))';

        match ($this->filter) {
            'owing' => $query->havingRaw("$balance > 0"),
            'settled' => $query->where('is_active', true)->havingRaw("$balance = 0"),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        return $query->orderByDesc('is_active')->orderBy('name')->orderBy('id')->paginate(12);
    }

    /** @return array{payable: string, owing: int, total: int} */
    #[Computed]
    public function stats(): array
    {
        $all = $this->withTotals(Supplier::query())->get();
        $payable = '0.00';
        $owing = 0;

        foreach ($all as $supplier) {
            $balance = $this->balanceOf($supplier);

            if (Money::cmp($balance, '0', 2) === 1) {
                $owing++;
                $payable = Money::add($payable, $balance, 2);
            }
        }

        return ['payable' => $payable, 'owing' => $owing, 'total' => $all->count()];
    }

    #[Computed]
    public function selected(): ?Supplier
    {
        if ($this->selectedId === '' || ! ctype_digit($this->selectedId)) {
            return null;
        }

        // id قديم أو من محل تاني يرجع للـ placeholder بدل 404
        $supplier = $this->withTotals(Supplier::query())->find((int) $this->selectedId);

        if ($supplier === null) {
            return null;
        }

        $this->authorize('view', $supplier);

        return $supplier;
    }

    /**
     * كشف الحساب: آخر 40 حركة (مشتريات + دفعات) والرصيد بعد كل حركة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function statement(): Collection
    {
        $supplier = $this->selected;

        if ($supplier === null) {
            return collect();
        }

        $purchases = Purchase::query()->where('supplier_id', $supplier->id)
            ->orderByDesc('received_at')->orderByDesc('id')->limit(40)->get()
            ->map(fn (Purchase $p) => [
                'kind' => 'purchase', 'order' => 0, 'id' => $p->id, 'at' => $p->received_at,
                'amount' => (string) $p->total_amount, 'number' => $p->invoice_number,
                'note' => $p->supplier_invoice_ref, 'method' => null,
            ]);

        $payments = SupplierPayment::query()->where('supplier_id', $supplier->id)
            ->orderByDesc('paid_at')->orderByDesc('id')->limit(40)->get()
            ->map(fn (SupplierPayment $p) => [
                'kind' => 'payment', 'order' => 1, 'id' => $p->id, 'at' => $p->paid_at,
                'amount' => (string) $p->amount, 'number' => null,
                'note' => $p->notes, 'method' => $p->method->label(),
            ]);

        $running = $this->balanceOf($supplier);

        return $purchases->concat($payments)
            ->sort(fn (array $a, array $b) => [$b['at']->getTimestamp(), $b['order'], $b['id']]
                <=> [$a['at']->getTimestamp(), $a['order'], $a['id']])
            ->take(40)
            ->values()
            ->map(function (array $row) use (&$running): array {
                $row['balance'] = $running;
                $running = $row['kind'] === 'purchase'
                    ? Money::sub($running, $row['amount'], 2)
                    : Money::add($running, $row['amount'], 2);

                return $row;
            });
    }

    /** @return array<int, PaymentMethod> */
    #[Computed]
    public function methods(): array
    {
        return PaymentMethod::cases();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->filter !== 'all';
    }

    // ---------- Helpers ----------

    private function balanceOf(Supplier $supplier): string
    {
        return Money::sub((string) ($supplier->bought_total ?? '0'), (string) ($supplier->paid_total ?? '0'), 2);
    }

    /**
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    private function withTotals(Builder $query): Builder
    {
        return $query
            ->withSum('purchases as bought_total', 'total_amount')
            ->withSum('payments as paid_total', 'amount')
            ->withCount('purchases as purchases_count');
    }
}; ?>

<div
    class="mx-auto max-w-7xl space-y-6"
    x-data
    x-on:keydown.window="
        const typing = ['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName);
        if ($event.key === '/' && !typing) { $event.preventDefault(); $refs.search?.querySelector('input')?.focus(); }
        if ($event.key === 'Escape' && !typing && $wire.selectedId) { $wire.clearSupplier(); }
    "
>
    @php
        $money = fn ($n) => number_format((float) $n, 2);
        $currency = auth()->user()->shop?->currency;
        $stats = $this->stats;
        $selected = $this->selected;
        $balanceOf = fn (App\Models\Supplier $s) => Money::sub((string) ($s->bought_total ?? '0'), (string) ($s->paid_total ?? '0'), 2);
        $filters = [
            'all' => __('الكل'),
            'owing' => __('له رصيد عندنا'),
            'settled' => __('مسدد'),
            'inactive' => __('موقوف'),
        ];
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('الموردين') }}</flux:heading>
            <flux:subheading>{{ __('حسابات الموردين والمتبقي لكل مورد') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('create', App\Models\Purchase::class)
                <flux:button variant="filled" icon="arrow-down-tray" :href="route('purchases.create')" wire:navigate>
                    {{ __('استلام مشتريات') }}
                </flux:button>
            @endcan
            @can('create', App\Models\Supplier::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('مورد جديد') }}</flux:button>
            @endcan
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid gap-3 sm:grid-cols-3 {{ $selected ? 'hidden lg:grid' : '' }}">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">{{ __('إجمالي المستحق للموردين') }}</div>
            <div class="mt-1 flex items-baseline gap-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">
                <span class="font-mono" dir="ltr">{{ $money($stats['payable']) }}</span>
                <span class="text-sm font-normal text-zinc-500">{{ $currency }}</span>
            </div>
        </div>
        <button type="button" wire:click="setFilter('owing')"
            class="rounded-xl border border-zinc-200 p-4 text-start transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
            <div class="text-sm text-zinc-500">{{ __('موردين لهم رصيد') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $stats['owing'] }}</div>
        </button>
        <button type="button" wire:click="setFilter('all')"
            class="rounded-xl border border-zinc-200 p-4 text-start transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
            <div class="text-sm text-zinc-500">{{ __('كل الموردين') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $stats['total'] }}</div>
        </button>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-[380px_minmax(0,1fr)]">

        {{-- ============ List ============ --}}
        <section class="space-y-3 lg:sticky lg:top-4 {{ $selected ? 'hidden lg:block' : '' }}" aria-label="{{ __('قائمة الموردين') }}">
            <div x-ref="search">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable type="search"
                    :placeholder="__('ابحث بالاسم أو الهاتف   ( اضغط / )')" />
            </div>

            <div class="flex gap-1.5 overflow-x-auto pb-1" role="tablist" aria-label="{{ __('تصفية') }}">
                @foreach ($filters as $key => $label)
                    <button type="button" role="tab" wire:click="setFilter('{{ $key }}')"
                        aria-selected="{{ $filter === $key ? 'true' : 'false' }}"
                        class="shrink-0 rounded-full border px-3 py-1 text-sm font-medium transition
                            {{ $filter === $key
                                ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                                : 'border-zinc-300 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="flex items-center justify-between text-sm text-zinc-500" aria-live="polite">
                <span>{{ __(':n مورد', ['n' => $this->suppliers->total()]) }}</span>
                @if ($this->hasActiveFilters)
                    <button type="button" class="text-blue-600 hover:underline dark:text-blue-400" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</button>
                @endif
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 transition dark:border-zinc-700"
                wire:loading.class="opacity-50" wire:target="search,filter,setFilter,resetFilters,gotoPage,nextPage,previousPage">
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->suppliers as $supplier)
                        @php
                            $bal = $balanceOf($supplier);
                            $owes = bccomp($bal, '0', 2) > 0;
                            $isSel = (string) $supplier->id === $selectedId;
                        @endphp
                        <li wire:key="supplier-{{ $supplier->id }}">
                            <button type="button" wire:click="selectSupplier({{ $supplier->id }})"
                                @if ($isSel) aria-current="true" @endif
                                class="flex w-full items-center gap-3 border-s-4 px-3 py-3 text-start transition hover:bg-zinc-50 dark:hover:bg-zinc-800
                                    {{ $isSel ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/30' : 'border-transparent' }}">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 font-semibold text-zinc-500 dark:bg-zinc-800">
                                    {{ mb_substr(trim($supplier->name), 0, 1) ?: '?' }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2 font-medium">
                                        <span class="truncate">{{ $supplier->name }}</span>
                                        @unless ($supplier->is_active)
                                            <flux:badge size="sm" color="zinc">{{ __('موقوف') }}</flux:badge>
                                        @endunless
                                    </span>
                                    <span class="block truncate text-xs text-zinc-500" dir="ltr">{{ $supplier->phone ?: ($supplier->email ?: '—') }}</span>
                                </span>
                                <span class="shrink-0 text-end leading-tight">
                                    @if ($owes)
                                        <b class="block font-mono text-sm text-amber-600 dark:text-amber-400" dir="ltr">{{ $money($bal) }}</b>
                                        <small class="text-[11px] text-zinc-500">{{ __('له') }}</small>
                                    @else
                                        <b class="block font-mono text-sm font-normal text-zinc-400" dir="ltr">0.00</b>
                                        <small class="text-[11px] text-zinc-500">{{ __('مسدد') }}</small>
                                    @endif
                                </span>
                            </button>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center">
                            <flux:icon.truck class="mx-auto size-8 text-zinc-400" />
                            <p class="mt-2 font-medium">{{ $this->hasActiveFilters ? __('مفيش موردين مطابقين') : __('لسه مفيش موردين') }}</p>
                            @if ($this->hasActiveFilters)
                                <flux:button class="mt-3" size="sm" variant="filled" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
                            @else
                                <flux:button class="mt-3" size="sm" variant="primary" icon="plus" wire:click="openCreate">{{ __('أضف أول مورد') }}</flux:button>
                            @endif
                        </li>
                    @endforelse
                </ul>
            </div>

            @if ($this->suppliers->hasPages())
                <div>{{ $this->suppliers->links(data: ['scrollTo' => false]) }}</div>
            @endif
        </section>

        {{-- ============ Detail ============ --}}
        <section aria-label="{{ __('تفاصيل المورد') }}" class="min-w-0 {{ $selected ? '' : 'hidden lg:block' }}">
            @if ($selected)
                @php
                    $balance = $balanceOf($selected);
                    $owes = bccomp($balance, '0', 2) > 0;
                    $bought = Money::norm($selected->bought_total ?? '0');
                    $paidTotal = Money::norm($selected->paid_total ?? '0');
                    $paidPct = bccomp($bought, '0', 2) > 0 ? (int) min(100, round((float) $paidTotal / (float) $bought * 100)) : 0;
                @endphp

                <div class="space-y-4" wire:key="supplier-detail-{{ $selected->id }}">
                    <button type="button" wire:click="clearSupplier"
                        class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900 lg:hidden dark:hover:text-white">
                        <flux:icon.arrow-right variant="micro" /> {{ __('العودة للقائمة') }}
                    </button>

                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                        {{-- Profile --}}
                        <div class="flex flex-wrap items-start justify-between gap-4 p-5">
                            <div class="flex min-w-0 items-start gap-4">
                                <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-zinc-100 text-xl font-bold dark:bg-zinc-800">
                                    {{ mb_substr(trim($selected->name), 0, 1) ?: '?' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading size="lg">{{ $selected->name }}</flux:heading>
                                        <flux:badge size="sm" :color="$selected->is_active ? 'green' : 'zinc'">
                                            {{ $selected->is_active ? __('نشط') : __('موقوف') }}
                                        </flux:badge>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500">
                                        @foreach (array_filter([$selected->phone, $selected->alternate_phone]) as $num)
                                            <a href="tel:{{ $num }}" class="inline-flex items-center gap-1 hover:text-blue-600" dir="ltr">
                                                <flux:icon.phone variant="micro" /> {{ $num }}
                                            </a>
                                        @endforeach
                                        @if ($selected->email)
                                            <a href="mailto:{{ $selected->email }}" class="inline-flex items-center gap-1 hover:text-blue-600" dir="ltr">
                                                <flux:icon.envelope variant="micro" /> {{ $selected->email }}
                                            </a>
                                        @endif
                                        @if ($selected->address)
                                            <span class="inline-flex items-center gap-1"><flux:icon.map-pin variant="micro" /> {{ $selected->address }}</span>
                                        @endif
                                    </div>
                                    @if ($selected->notes)
                                        <p class="mt-2 text-sm text-zinc-500">{{ $selected->notes }}</p>
                                    @endif
                                </div>
                            </div>

                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('المزيد')" />
                                <flux:menu>
                                    @can('update', $selected)
                                        <flux:menu.item icon="pencil" wire:click="openEdit({{ $selected->id }})">{{ __('تعديل') }}</flux:menu.item>
                                        <flux:menu.item :icon="$selected->is_active ? 'pause-circle' : 'play-circle'" wire:click="toggleActive({{ $selected->id }})">
                                            {{ $selected->is_active ? __('إيقاف') : __('تفعيل') }}
                                        </flux:menu.item>
                                    @endcan
                                    @can('delete', $selected)
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $selected->id }})"
                                            wire:confirm="{{ __('هل أنت متأكد من حذف هذا المورد؟') }}">{{ __('حذف') }}</flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        {{-- Balance --}}
                        <div class="flex flex-wrap items-end justify-between gap-4 border-t border-zinc-200 px-5 py-4 dark:border-zinc-700
                            {{ $owes ? 'bg-amber-50 dark:bg-amber-950/20' : '' }}">
                            <div>
                                <div class="text-sm text-zinc-500">{{ $owes ? __('المطلوب سداده للمورد') : __('الحساب مسدد') }}</div>
                                <div class="mt-1 flex items-baseline gap-2 text-4xl font-bold leading-tight {{ $owes ? 'text-amber-600 dark:text-amber-400' : '' }}">
                                    <span class="font-mono" dir="ltr">{{ $money($balance) }}</span>
                                    <span class="text-sm font-normal text-zinc-500">{{ $currency }}</span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($owes)
                                    @can('update', $selected)
                                        <flux:button variant="primary" icon="banknotes" wire:click="openPay({{ $selected->id }})">{{ __('سداد دفعة') }}</flux:button>
                                    @endcan
                                @endif
                                @if ($selected->is_active)
                                    @can('create', App\Models\Purchase::class)
                                        <flux:button variant="filled" icon="arrow-down-tray"
                                            :href="route('purchases.create', ['supplier' => $selected->id])" wire:navigate>
                                            {{ __('استلام مشتريات') }}
                                        </flux:button>
                                    @endcan
                                @endif
                            </div>

                            @if (bccomp($bought, '0', 2) > 0)
                                <div class="w-full max-w-xs">
                                    <div class="mb-1 flex justify-between text-xs text-zinc-500">
                                        <span>{{ __('نسبة السداد') }}</span><span class="font-mono" dir="ltr">{{ $paidPct }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar"
                                        aria-valuenow="{{ $paidPct }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $paidPct }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Totals --}}
                        <dl class="grid grid-cols-3 divide-x divide-zinc-200 border-t border-zinc-200 rtl:divide-x-reverse dark:divide-zinc-700 dark:border-zinc-700">
                            <div class="px-4 py-3"><dt class="text-xs text-zinc-500">{{ __('إجمالي المشتريات') }}</dt><dd class="mt-0.5 font-mono text-sm font-semibold" dir="ltr">{{ $money($bought) }}</dd></div>
                            <div class="px-4 py-3"><dt class="text-xs text-zinc-500">{{ __('إجمالي المدفوع') }}</dt><dd class="mt-0.5 font-mono text-sm font-semibold" dir="ltr">{{ $money($paidTotal) }}</dd></div>
                            <div class="px-4 py-3"><dt class="text-xs text-zinc-500">{{ __('عدد الفواتير') }}</dt><dd class="mt-0.5 font-mono text-sm font-semibold" dir="ltr">{{ $selected->purchases_count }}</dd></div>
                        </dl>
                    </div>

                    {{-- Statement --}}
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                            <flux:heading size="sm">{{ __('كشف الحساب') }}</flux:heading>
                            <span class="text-xs text-zinc-500">{{ __('آخر 40 حركة') }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[40rem] text-start text-sm">
                                <thead class="text-xs text-zinc-500">
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                        <th class="px-5 py-2 text-start font-medium">{{ __('التاريخ') }}</th>
                                        <th class="px-5 py-2 text-start font-medium">{{ __('البيان') }}</th>
                                        <th class="px-5 py-2 text-end font-medium">{{ __('مشتريات') }}</th>
                                        <th class="px-5 py-2 text-end font-medium">{{ __('مدفوع') }}</th>
                                        <th class="px-5 py-2 text-end font-medium">{{ __('الرصيد بعد الحركة') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @forelse ($this->statement as $row)
                                        @php $isPurchase = $row['kind'] === 'purchase'; @endphp
                                        <tr wire:key="st-{{ $row['kind'] }}-{{ $row['id'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                            <td class="px-5 py-3 align-top font-mono" dir="ltr">
                                                <div>{{ $row['at']->format('Y-m-d') }}</div>
                                                <div class="text-xs text-zinc-500">{{ $row['at']->format('H:i') }}</div>
                                            </td>
                                            <td class="px-5 py-3 align-top">
                                                @if ($isPurchase)
                                                    <a href="{{ route('purchases.show', ['id' => $row['id']]) }}" wire:navigate
                                                        class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                                        {{ __('فاتورة مشتريات #:n', ['n' => $row['number']]) }}
                                                    </a>
                                                @else
                                                    <div class="font-medium">{{ __('دفعة') }} <span class="font-normal text-zinc-500">· {{ $row['method'] }}</span></div>
                                                @endif
                                                @if ($row['note'])
                                                    <div class="mt-0.5 text-xs text-zinc-500">{{ $row['note'] }}</div>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-end align-top font-mono {{ $isPurchase ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}" dir="ltr">{{ $isPurchase ? $money($row['amount']) : '—' }}</td>
                                            <td class="px-5 py-3 text-end align-top font-mono {{ ! $isPurchase ? 'font-medium text-emerald-600 dark:text-emerald-400' : 'text-zinc-300 dark:text-zinc-600' }}" dir="ltr">{{ ! $isPurchase ? $money($row['amount']) : '—' }}</td>
                                            <td class="px-5 py-3 text-end align-top font-mono text-zinc-500" dir="ltr">{{ $money($row['balance']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-10 text-center text-zinc-500">{{ __('مفيش حركات مسجلة لهذا المورد.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex min-h-[24rem] flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                    <div class="mb-3 flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon.truck class="size-8 text-zinc-400" />
                    </div>
                    <flux:heading size="md">{{ __('اختر موردًا لعرض حسابه') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('هتشوف المتبقي وكشف الحساب والفواتير والدفعات هنا.') }}</flux:text>
                    <p class="mt-4 text-xs text-zinc-500">{{ __('اضغط / للبحث، وEsc للرجوع للقائمة') }}</p>
                </div>
            @endif
        </section>
    </div>

    {{-- ===================== Supplier form modal ===================== --}}
    <flux:modal name="supplier-form" class="w-full max-w-xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('تعديل مورد') : __('مورد جديد') }}</flux:heading>

            <flux:input wire:model="name" :label="__('اسم المورد')" required autofocus />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="phone" :label="__('التليفون')" dir="ltr" />
                <flux:input wire:model="alternate_phone" :label="__('تليفون آخر')" dir="ltr" />
            </div>

            <flux:input wire:model="email" type="email" :label="__('البريد الإلكتروني')" dir="ltr" />
            <flux:input wire:model="address" :label="__('العنوان')" />
            <flux:textarea wire:model="notes" rows="2" :label="__('ملاحظات')" />
            <flux:checkbox wire:model="is_active" :label="__('نشط')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('إلغاء') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('حفظ') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ===================== Payment modal ===================== --}}
    <flux:modal name="supplier-pay" class="w-full max-w-md">
        @php $payBalance = $selected ? $balanceOf($selected) : '0.00'; @endphp

        <form wire:submit="savePay" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('سداد دفعة للمورد') }}</flux:heading>
                @if ($selected)
                    <flux:text class="mt-1">{{ $selected->name }}</flux:text>
                @endif
            </div>

            <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm dark:bg-amber-950/20">
                {{ __('المتبقي للمورد') }}:
                <strong class="font-mono" dir="ltr">{{ $money($payBalance) }} {{ $currency }}</strong>
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input wire:model="payAmount" type="number" step="0.01" min="0" dir="ltr" :label="__('المبلغ')" required />
                </div>
                <flux:button type="button" variant="filled" wire:click="fillPayFull">{{ __('المتبقي كامل') }}</flux:button>
            </div>
            @error('payAmount') <flux:text class="text-sm text-red-600">{{ $message }}</flux:text> @enderror

            <flux:select wire:model="payMethod" :label="__('طريقة الدفع')">
                @foreach ($this->methods as $m)
                    <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="payNotes" :label="__('ملاحظات (اختياري)')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button" variant="ghost">{{ __('إلغاء') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="savePay">{{ __('تسجيل الدفعة') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>