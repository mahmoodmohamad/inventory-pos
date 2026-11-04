<?php

use App\Enums\ProductCondition;
use App\Exceptions\InsufficientStockException;

use App\Enums\ProductUnit;
use App\Livewire\Forms\ProductForm;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\VehicleMake;
use App\Queries\ProductQuery;
use App\Services\BarcodeGenerator;
use App\Services\ProductBulkService;
use App\Services\ProductSaveService;
use App\Services\StockService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('المنتجات')] class extends Component
{
    use WithPagination;

    public ProductForm $form;

    #[Url(as: 'q')] public string $search = '';
    #[Url(as: 'condition')] public string $conditionFilter = '';
    #[Url(as: 'category')] public string $categoryFilter = '';
    #[Url(as: 'brand')] public string $brandFilter = '';
    #[Url(as: 'stock')] public string $stockFilter = '';
    #[Url(as: 'status')] public string $statusFilter = 'all';
    #[Url(as: 'make')] public string $makeFilter = '';
    #[Url(as: 'model')] public string $modelFilter = '';
    #[Url(as: 'variant')] public string $variantFilter = '';
    #[Url(as: 'sort')] public string $sortBy = 'name';
    #[Url(as: 'dir')] public string $sortDir = 'asc';
    #[Url(as: 'per')] public int $perPage = 15;

    /** @var array<int, string> */
    public array $selected = [];

    public ?int $fitProductId = null;
    public string $fitMakeId = '';
    public string $fitModelId = '';
    public string $fitVariantId = '';

    public ?int $adjustProductId = null;
    public string $adjustQty = '';
    public string $adjustNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Product::class);
    }

    // ---------- Hooks ----------

    public function updated(string $property): void
    {
        $resets = ['search', 'conditionFilter', 'categoryFilter', 'brandFilter', 'stockFilter',
            'statusFilter', 'makeFilter', 'modelFilter', 'variantFilter', 'perPage'];

        if (in_array($property, $resets, true)) {
            $this->resetPage();
            $this->selected = [];
        }

        match ($property) {
            'makeFilter' => $this->reset('modelFilter', 'variantFilter'),
            'modelFilter' => $this->reset('variantFilter'),
            'fitMakeId' => $this->reset('fitModelId', 'fitVariantId'),
            'fitModelId' => $this->reset('fitVariantId'),
            default => null,
        };
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ProductQuery::SORTABLE, true)) {
            return;
        }

        $this->sortDir = ($this->sortBy === $column && $this->sortDir === 'asc') ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'conditionFilter', 'categoryFilter', 'brandFilter', 'stockFilter',
            'makeFilter', 'modelFilter', 'variantFilter');
        $this->statusFilter = 'all';
        $this->selected = [];
        $this->resetPage();
    }

    public function filterStock(string $value): void
    {
        $this->stockFilter = $this->stockFilter === $value ? '' : $value;
        $this->selected = [];
        $this->resetPage();
    }

    // ---------- Selection / bulk ----------

    public function togglePage(): void
    {
        $ids = $this->products->pluck('id')->map(fn ($id) => (string) $id)->all();
        $all = $ids !== [] && count(array_diff($ids, $this->selected)) === 0;

        $this->selected = $all
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function bulk(string $action): void
    {
        abort_unless(in_array($action, ProductBulkService::ACTIONS, true), 422);

        $ids = array_map('intval', $this->selected);

        if ($ids === []) {
            return;
        }

        $result = app(ProductBulkService::class)->apply(Auth::user(), $action, $ids);
        $this->selected = [];

        Flux::toast(
            variant: $result['skipped'] > 0 ? 'warning' : 'success',
            text: $result['skipped'] > 0
                ? __('تم تنفيذ :done وتخطي :skipped (رصيد غير صفري أو بدون صلاحية).', $result)
                : __('تم تنفيذ العملية على :done منتج.', $result),
        );
    }

    // ---------- Product form ----------

    public function openCreate(): void
    {
        $this->authorize('create', Product::class);
        $this->form->reset();
        $this->form->resetValidation();
        Flux::modal('product-form')->show();
    }

    public function openEdit(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $this->authorize('update', $product);

        $this->form->reset();
        $this->form->resetValidation();
        $this->form->fillFrom($product);
        $this->form->editingId = $product->id;
        $this->form->barcode = $product->barcode ?? '';
        $this->form->editingStock = (float) $product->stock_qty;
        $this->form->editingAvgCost = (float) $product->avg_cost;
        $this->form->new_stock_qty = rtrim(rtrim(number_format((float) $product->stock_qty, 3, '.', ''), '0'), '.') ?: '0';
        Flux::modal('product-form')->show();
    }

    /** كل نوع منتج منفصل بنفس الـ part number، فالنسخ بيجهّز نوع تاني. */
    public function openDuplicate(int $id): void
    {
        $this->authorize('create', Product::class);
        $source = Product::query()->findOrFail($id);

        $this->form->reset();
        $this->form->resetValidation();
        $this->form->fillFrom($source);
        $this->form->duplicateFromId = $source->id;
        $this->form->barcode = '';

        $next = collect(ProductCondition::cases())->first(fn (ProductCondition $c) => $c !== $source->condition);
        $this->form->condition = ($next ?? $source->condition)->value;

        Flux::modal('product-form')->show();
    }

    public function generateBarcode(): void
    {
        $this->form->barcode = app(BarcodeGenerator::class)->internalEan13();
    }

  public function save(): void
{
    $this->form->validate();

    $isCreate = $this->form->editingId === null;
    $existing = $isCreate ? null : Product::query()->findOrFail($this->form->editingId);

    $this->authorize($isCreate ? 'create' : 'update', $existing ?? Product::class);

    $source = ($isCreate && $this->form->duplicateFromId !== null)
        ? Product::query()->find($this->form->duplicateFromId)
        : null;

    app(ProductSaveService::class)->save(Auth::user(), $this->form, $existing, $source);

    Flux::modal('product-form')->close();
    $this->form->reset();
    Flux::toast(variant: 'success', text: __('تم الحفظ.'));
}
    public function toggleActive(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $this->authorize('update', $product);

        $product->update(['is_active' => ! $product->is_active]);
        Flux::toast(text: $product->is_active ? __('تم تفعيل المنتج.') : __('تم إيقاف المنتج.'));
    }

    public function delete(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $this->authorize('delete', $product);

        if ((float) $product->stock_qty !== 0.0) {
            Flux::toast(variant: 'danger', text: __('لا يمكن حذف منتج رصيده لا يساوي صفر. صفّر الرصيد بتسوية أولًا.'));

            return;
        }

        $product->delete();
        $this->selected = array_values(array_diff($this->selected, [(string) $id]));
        Flux::toast(text: __('تم الحذف.'));
    }

    // ---------- Stock adjustment ----------

    public function openAdjust(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $this->authorize('adjustStock', $product);

        $this->reset('adjustQty', 'adjustNotes');
        $this->resetValidation();
        $this->adjustProductId = $product->id;

        Flux::modal('stock-adjust')->show();
    }

    public function saveAdjust(): void
    {
        $product = Product::query()->findOrFail($this->adjustProductId);
        $this->authorize('adjustStock', $product);

        $this->validate([
            'adjustQty' => ['required', 'numeric', 'between:-999999999,999999999'],
            'adjustNotes' => ['required', 'string', 'max:255'],
        ]);

        if ((float) $this->adjustQty === 0.0) {
            $this->addError('adjustQty', __('الكمية لا يمكن أن تكون صفرًا.'));

            return;
        }

        try {
            app(StockService::class)->adjust($product, (float) $this->adjustQty, trim($this->adjustNotes));
        } catch (InsufficientStockException) {
            $this->addError('adjustQty', __('الرصيد لا يكفي لهذه التسوية.'));

            return;
        }

        Flux::modal('stock-adjust')->close();
        $this->reset('adjustProductId', 'adjustQty', 'adjustNotes');
        Flux::toast(variant: 'success', text: __('تمت التسوية.'));
    }

    // ---------- Fitments ----------

    public function openFitments(int $id): void
    {
        $product = Product::query()->findOrFail($id);
        $this->authorize('view', $product);

        $this->reset('fitMakeId', 'fitModelId', 'fitVariantId');
        $this->resetValidation();
        $this->fitProductId = $product->id;

        Flux::modal('product-fitments')->show();
    }

    public function addFitment(): void
    {
        $product = Product::query()->findOrFail($this->fitProductId);
        $this->authorize('update', $product);

        $this->validate([
            'fitVariantId' => ['required', 'integer', Rule::exists('vehicle_variants', 'id')],
        ], ['fitVariantId.required' => __('اختر الماركة والموديل والفئة أولًا.')]);

        $product->fitments()->firstOrCreate(['vehicle_variant_id' => (int) $this->fitVariantId]);

        $this->reset('fitVariantId');
        unset($this->fitments);
    }

    public function addAllVariants(): void
    {
        $product = Product::query()->findOrFail($this->fitProductId);
        $this->authorize('update', $product);

        $this->validate([
            'fitModelId' => ['required', 'integer', Rule::exists('vehicle_models', 'id')],
        ], ['fitModelId.required' => __('اختر الماركة والموديل أولًا.')]);

        $added = 0;

        foreach ($this->variantsOf($this->fitModelId) as $variant) {
            if ($product->fitments()->firstOrCreate(['vehicle_variant_id' => $variant->id])->wasRecentlyCreated) {
                $added++;
            }
        }

        $this->reset('fitVariantId');
        unset($this->fitments);

        Flux::toast(
            variant: $added > 0 ? 'success' : 'warning',
            text: $added > 0 ? __('تمت إضافة :n فئة.', ['n' => $added]) : __('مفيش فئات جديدة تتضاف لهذا الموديل.'),
        );
    }
/** @return array<int, ProductCondition> */
#[Computed]
public function conditions(): array
{
    return ProductCondition::cases();
}

/** @return array<int, ProductUnit> */
#[Computed]
public function units(): array
{
    return ProductUnit::cases();
}
    public function removeFitment(int $id): void
    {
        $fitment = ProductFitment::query()->with('product')->findOrFail($id);
        $this->authorize('update', $fitment->product);

        $fitment->delete();
        unset($this->fitments);
    }

    // ---------- Computed ----------

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('create', Product::class);
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->statusFilter !== 'all'
            || '' !== $this->conditionFilter.$this->categoryFilter.$this->brandFilter
                .$this->stockFilter.$this->makeFilter.$this->modelFilter.$this->variantFilter;
    }

    /** @return LengthAwarePaginator<int, Product> */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        $perPage = in_array($this->perPage, ProductQuery::PER_PAGE, true) ? $this->perPage : 15;

        return ProductQuery::build([
            'search' => $this->search, 'condition' => $this->conditionFilter,
            'category' => $this->categoryFilter, 'brand' => $this->brandFilter,
            'stock' => $this->stockFilter, 'status' => $this->statusFilter,
            'make' => $this->makeFilter, 'model' => $this->modelFilter, 'variant' => $this->variantFilter,
        ], $this->sortBy, $this->sortDir)->paginate($perPage);
    }

    /** @return array{total: int, low: int, out: int, inventory_cost: float|null} */
    #[Computed]
    public function stats(): array
    {
        $row = Product::query()->toBase()->selectRaw(
            'count(*) as total,'
            .' coalesce(sum(is_active = 1 and min_stock_alert > 0 and stock_qty <= min_stock_alert), 0) as low,'
            .' coalesce(sum(is_active = 1 and stock_qty <= 0), 0) as out_of_stock'
        )->first();

        $cost = Gate::allows('view-financials')
            ? (float) Product::query()->where('stock_qty', '>', 0)->sum(DB::raw('stock_qty * avg_cost'))
            : null;

        return ['total' => (int) $row->total, 'low' => (int) $row->low,
            'out' => (int) $row->out_of_stock, 'inventory_cost' => $cost];
    }

    /** @return Collection<int, Category> */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->with('parent')->orderBy('name')->get();
    }

    /** @return Collection<int, Brand> */
    #[Computed]
    public function brands(): Collection
    {
        return Brand::query()->orderBy('name')->get();
    }

    /** @return Collection<int, VehicleMake> */
    #[Computed]
    public function makes(): Collection
    {
        return VehicleMake::query()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function filterModels(): Collection
    {
        return $this->modelsOf($this->makeFilter);
    }

    #[Computed]
    public function filterVariants(): Collection
    {
        return $this->variantsOf($this->modelFilter);
    }

    #[Computed]
    public function fitModels(): Collection
    {
        return $this->modelsOf($this->fitMakeId);
    }

    #[Computed]
    public function fitVariants(): Collection
    {
        return $this->variantsOf($this->fitModelId);
    }

    #[Computed]
    public function fitProduct(): ?Product
    {
        return $this->fitProductId === null ? null : Product::query()->find($this->fitProductId);
    }

    /** @return Collection<int, ProductFitment> */
    #[Computed]
    public function fitments(): Collection
    {
        if ($this->fitProductId === null) {
            return new Collection;
        }

        return ProductFitment::query()
            ->where('product_id', $this->fitProductId)
            ->with('variant.vehicleModel.make')
            ->get()
            ->sortBy(fn (ProductFitment $f) => $f->variant->vehicleModel->make->name.' '.$f->variant->vehicleModel->name.' '.$f->variant->year_from)
            ->values();
    }

    #[Computed]
    public function adjustProduct(): ?Product
    {
        return $this->adjustProductId === null ? null : Product::query()->find($this->adjustProductId);
    }

    #[Computed]
    public function possibleDuplicate(): ?Product
    {
        $part = trim($this->form->part_number);

        if ($part === '') {
            return null;
        }

        return Product::query()
            ->where('part_number', $part)
            ->where('condition', $this->form->condition)
            ->when($this->form->editingId !== null, fn ($q) => $q->where('id', '!=', $this->form->editingId))
            ->first();
    }
    /** @return Collection<int, VehicleModel> */
private function modelsOf(string $makeId): Collection
{
    if ($makeId === '') {
        return new Collection;
    }

    return VehicleModel::query()
        ->where('vehicle_make_id', (int) $makeId)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();
}

/** @return Collection<int, VehicleVariant> */
private function variantsOf(string $modelId): Collection
{
    if ($modelId === '') {
        return new Collection;
    }

    return VehicleVariant::query()
        ->where('vehicle_model_id', (int) $modelId)
        ->where('is_active', true)
        ->orderBy('year_from')
        ->orderBy('name')
        ->get();
}
};
?>

<style>
    
    .pl { display: flex; flex-direction: column; gap: 1.25rem; }
    .pl-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
    .pl-stack { display: flex; flex-direction: column; gap: 1.25rem; }
    .pl-muted { color: #71717a; }
    .pl-ltr { direction: ltr; unicode-bidi: plaintext; text-align: start; }
    .pl-num { direction: ltr; unicode-bidi: plaintext; text-align: start; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pl-sub { font-family: ui-monospace, monospace; font-size: .75rem; color: #71717a; direction: ltr; unicode-bidi: plaintext; text-align: start; }

    .pl-stats { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); }
    .pl-stat { text-align: start; background: transparent; color: inherit; font: inherit; cursor: pointer;
        border: 1px solid rgb(113 113 122 / .3); border-radius: .75rem; padding: .8rem 1rem; }
    .pl-stat:hover { border-color: rgb(113 113 122 / .65); }
    .pl-stat.is-on { border-color: #6366f1; box-shadow: 0 0 0 1px #6366f1; }
    .pl-stat.is-static { cursor: default; }
    .pl-stat-label { font-size: .8125rem; color: #71717a; }
    .pl-stat-num { font-size: 1.5rem; font-weight: 600; font-variant-numeric: tabular-nums; margin-top: .15rem; }
    .pl-warn { color: #d97706; } .pl-bad { color: #ef4444; } .pl-ok { color: #10b981; }

    .pl-filters { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); align-items: end; }
    .pl-search { max-width: 42rem; }
    .pl-filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }

    .pl-wrap { overflow-x: auto; border: 1px solid rgb(113 113 122 / .3); border-radius: .75rem; transition: opacity .15s; }
    .pl-table { width: 100%; min-width: 62rem; border-collapse: collapse; font-size: .875rem; }
    .pl-table th { text-align: start; padding: .7rem 1rem; font-weight: 500; color: #71717a; white-space: nowrap; background: rgb(113 113 122 / .08); }
    .pl-table td { padding: .7rem 1rem; border-top: 1px solid rgb(113 113 122 / .2); vertical-align: middle; }
    .pl-table tr.is-off td { opacity: .55; }
    .pl-table tr.is-picked td { background: rgb(99 102 241 / .07); }
    .pl-sort { display: inline-flex; align-items: center; gap: .25rem; background: none; border: 0; color: inherit; font: inherit; cursor: pointer; padding: 0; }
    .pl-name { font-weight: 500; }
    .pl-tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .3rem; }
    .pl-check { width: 1rem; height: 1rem; cursor: pointer; accent-color: #6366f1; }
    .pl-actions { display: flex; align-items: center; gap: .25rem; }
    .pl-empty { display: flex; flex-direction: column; align-items: center; gap: .6rem; padding: 3rem 1rem; text-align: center; }
    .pl-dim { opacity: .55; }

    .pl-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
    .pl-per { width: 7rem; }

    .pl-bulk { position: sticky; bottom: 1rem; z-index: 20; display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;
        padding: .7rem 1rem; border-radius: .75rem; border: 1px solid rgb(113 113 122 / .4); background: #fff;
        box-shadow: 0 10px 30px rgb(0 0 0 / .15); }
    .dark .pl-bulk { background: #27272a; }
    .pl-bulk-count { font-weight: 600; margin-inline-end: auto; }

    .pl-modal-lg { width: 100%; max-width: 48rem; }
    .pl-modal-md { width: 100%; max-width: 28rem; }
    .pl-section { border: 1px solid rgb(113 113 122 / .3); border-radius: .75rem; padding: 1rem; display: flex; flex-direction: column; gap: 1rem; }
    .pl-section-title { font-size: .8125rem; font-weight: 600; color: #71717a; }
    .pl-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); }
    .pl-inline { display: flex; gap: .5rem; align-items: flex-end; }
    .pl-inline > :first-child { flex: 1; min-width: 0; }
    .pl-note { font-size: .8125rem; padding: .55rem .8rem; border-radius: .5rem; background: rgb(99 102 241 / .1); }
    .pl-note.is-warn { background: rgb(245 158 11 / .14); }
    .pl-form-foot { display: flex; justify-content: flex-end; gap: .5rem; }

    .pl-group { border: 1px solid rgb(113 113 122 / .3); border-radius: .75rem; overflow: hidden; }
    .pl-group-title { padding: .55rem 1rem; font-weight: 500; background: rgb(113 113 122 / .08); }
    .pl-group-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .5rem 1rem; border-top: 1px solid rgb(113 113 122 / .2); font-size: .875rem; }
    .pl-reasons { display: flex; flex-wrap: wrap; gap: .4rem; }

    </style>
<div
    class="pl"
    x-data
    x-on:keydown.window="if ($event.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search?.querySelector('input')?.focus(); }"
>
    @php
        $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $money = fn ($n) => number_format((float) $n, 2);
        $currency = auth()->user()->shop?->currency;
        $stats = $this->stats;
        $products = $this->products;
        $canManage = $this->canManage;
        $showCost = Gate::allows('view-financials');
        $pageIds = $products->pluck('id')->map(fn ($id) => (string) $id)->all();
        $allPicked = $pageIds !== [] && count(array_diff($pageIds, $selected)) === 0;
    @endphp

    {{-- Header --}}
    <div class="pl-head">
        <div>
            <flux:heading size="xl" level="1">{{ __('المنتجات') }}</flux:heading>
            <flux:subheading>{{ __('القطع والأسعار والمخزون والتوافق مع السيارات') }}</flux:subheading>
        </div>

        @if ($canManage)
            <flux:button wire:click="openCreate" variant="primary" icon="plus">
                {{ __('منتج جديد') }}
            </flux:button>
        @endif
    </div>

    {{-- Stat strip (clickable filters) --}}
    <div class="pl-stats">
        <button type="button" class="pl-stat {{ $stockFilter === '' ? 'is-on' : '' }}" wire:click="$set('stockFilter', '')">
            <div class="pl-stat-label">{{ __('كل المنتجات') }}</div>
            <div class="pl-stat-num">{{ $stats['total'] }}</div>
        </button>

        <button type="button" class="pl-stat {{ $stockFilter === 'low' ? 'is-on' : '' }}" wire:click="filterStock('low')">
            <div class="pl-stat-label">{{ __('نواقص') }}</div>
            <div class="pl-stat-num {{ $stats['low'] > 0 ? 'pl-warn' : '' }}">{{ $stats['low'] }}</div>
        </button>

        <button type="button" class="pl-stat {{ $stockFilter === 'out' ? 'is-on' : '' }}" wire:click="filterStock('out')">
            <div class="pl-stat-label">{{ __('رصيد صفر أو أقل') }}</div>
            <div class="pl-stat-num {{ $stats['out'] > 0 ? 'pl-bad' : '' }}">{{ $stats['out'] }}</div>
        </button>

        @if ($stats['inventory_cost'] !== null)
            <div class="pl-stat is-static">
                <div class="pl-stat-label">{{ __('قيمة المخزون (بالتكلفة)') }}</div>
                <div class="pl-stat-num pl-num">{{ $money($stats['inventory_cost']) }} <span class="pl-muted" style="font-size:.8125rem;font-weight:400">{{ $currency }}</span></div>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="pl-stack" style="gap:.75rem">
        <div class="pl-search" x-ref="search">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('ابحث بالباركود أو رقم القطعة أو الاسم   ( اضغط / )')"
                clearable
            />
        </div>

        <div class="pl-filters">
            <flux:select wire:model.live="conditionFilter" :label="__('النوع')">
                <flux:select.option value="">{{ __('الكل') }}</flux:select.option>
                @foreach ($this->conditions as $c)
                    <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="categoryFilter" :label="__('الفئة')">
                <flux:select.option value="">{{ __('الكل') }}</flux:select.option>
                @foreach ($this->categories as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->parent ? $cat->parent->name.' › ' : '' }}{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="brandFilter" :label="__('الماركة')">
                <flux:select.option value="">{{ __('الكل') }}</flux:select.option>
                @foreach ($this->brands as $brand)
                    <flux:select.option value="{{ $brand->id }}">{{ $brand->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="stockFilter" :label="__('المخزون')">
                <flux:select.option value="">{{ __('الكل') }}</flux:select.option>
                <flux:select.option value="in">{{ __('متوفر') }}</flux:select.option>
                <flux:select.option value="low">{{ __('نواقص') }}</flux:select.option>
                <flux:select.option value="out">{{ __('رصيد صفر أو أقل') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="statusFilter" :label="__('الحالة')">
                <flux:select.option value="all">{{ __('الكل') }}</flux:select.option>
                <flux:select.option value="active">{{ __('نشط') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('موقوف') }}</flux:select.option>
            </flux:select>
        </div>

        {{-- Vehicle compatibility filter: "إيه القطع اللي تركب على العربية دي؟" --}}
        <div class="pl-filters">
            <flux:select wire:model.live="makeFilter" :label="__('تركب على: الماركة')">
                <flux:select.option value="">{{ __('أي سيارة') }}</flux:select.option>
                @foreach ($this->makes as $make)
                    <flux:select.option value="{{ $make->id }}">{{ $make->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="modelFilter" :label="__('الموديل')" :disabled="$makeFilter === ''">
                <flux:select.option value="">{{ __('الكل') }}</flux:select.option>
                @foreach ($this->filterModels as $m)
                    <flux:select.option value="{{ $m->id }}">{{ $m->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="variantFilter" :label="__('الفئة / السنة')" :disabled="$modelFilter === ''">
                <flux:select.option value="">{{ __('الكل') }}</flux:select.option>
                @foreach ($this->filterVariants as $v)
                    <flux:select.option value="{{ $v->id }}">{{ $v->name }} ({{ $v->year_from }}–{{ $v->year_to ?? __('الآن') }})</flux:select.option>
                @endforeach
            </flux:select>

            <div class="pl-filter-bar">
                @if ($this->hasActiveFilters)
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="resetFilters">
                        {{ __('مسح الفلاتر') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div
        class="pl-wrap"
        wire:loading.class="pl-dim"
        wire:target="search,conditionFilter,categoryFilter,brandFilter,stockFilter,statusFilter,makeFilter,modelFilter,variantFilter,perPage,sort,gotoPage,nextPage,previousPage,resetFilters,filterStock"
    >
        <table class="pl-table">
            <thead>
                <tr>
                    @if ($canManage)
                        <th style="width:2.5rem">
                            <input type="checkbox" class="pl-check" wire:click="togglePage" @checked($allPicked) aria-label="{{ __('تحديد الصفحة') }}">
                        </th>
                    @endif
                    <th>
                        <button type="button" class="pl-sort" wire:click="sort('name')">
                            {{ __('المنتج') }}
                            @if ($sortBy === 'name') <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" /> @endif
                        </button>
                    </th>
                    <th>{{ __('النوع') }}</th>
                    <th>{{ __('الماركة / الفئة') }}</th>
                    <th>
                        <button type="button" class="pl-sort" wire:click="sort('retail_price')">
                            {{ __('قطاعي') }}
                            @if ($sortBy === 'retail_price') <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" /> @endif
                        </button>
                    </th>
                    <th>
                        <button type="button" class="pl-sort" wire:click="sort('wholesale_price')">
                            {{ __('جملة') }}
                            @if ($sortBy === 'wholesale_price') <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" /> @endif
                        </button>
                    </th>
                    @if ($showCost)
                        <th>{{ __('التكلفة / الهامش') }}</th>
                    @endif
                    <th>
                        <button type="button" class="pl-sort" wire:click="sort('stock_qty')">
                            {{ __('الرصيد') }}
                            @if ($sortBy === 'stock_qty') <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" /> @endif
                        </button>
                    </th>
                    <th style="width:1%">{{ __('إجراءات') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($products as $product)
                    @php
                        $stock = (float) $product->stock_qty;
                        $alert = (float) $product->min_stock_alert;
                        $isOut = $stock <= 0;
                        $isLow = ! $isOut && $alert > 0 && $stock <= $alert;
                        $picked = in_array((string) $product->id, $selected, true);

                        $retail = (float) $product->retail_price;
                        $avg = (float) $product->avg_cost;
                        $margin = ($retail > 0 && $avg > 0) ? (($retail - $avg) / $retail) * 100 : null;
                    @endphp

                    <tr wire:key="product-{{ $product->id }}" class="{{ $product->is_active ? '' : 'is-off' }} {{ $picked ? 'is-picked' : '' }}">
                        @if ($canManage)
                            <td>
                                <input type="checkbox" class="pl-check" value="{{ $product->id }}" wire:model.live="selected" aria-label="{{ __('تحديد') }}">
                            </td>
                        @endif

                        <td>
                            <div class="pl-name">{{ $product->name }}</div>
                            <div class="pl-sub">
                                {{ $product->part_number ?? '—' }}@if ($product->barcode) · {{ $product->barcode }}@endif
                            </div>
                            @if ($product->oem_number && $product->oem_number !== $product->part_number)
                                <div class="pl-sub">OEM {{ $product->oem_number }}</div>
                            @endif
                            <div class="pl-tags">
                                @if ($product->location)
                                    <flux:badge size="sm" icon="map-pin">{{ $product->location }}</flux:badge>
                                @endif
                                @if (! $product->is_active)
                                    <flux:badge size="sm" color="zinc">{{ __('موقوف') }}</flux:badge>
                                @endif
                            </div>
                        </td>

                        <td><flux:badge size="sm">{{ $product->condition->label() }}</flux:badge></td>

                        <td>
                            <div>{{ $product->brand?->name ?? '—' }}</div>
                            <div class="pl-muted" style="font-size:.75rem">{{ $product->category?->name ?? '—' }}</div>
                        </td>

                        <td class="pl-num">{{ $money($product->retail_price) }}</td>
                        <td class="pl-num">{{ $money($product->wholesale_price) }}</td>

                        @if ($showCost)
                            <td>
                                <div class="pl-num">{{ $avg > 0 ? $money($avg) : '—' }}</div>
                                @if ($margin !== null)
                                    <div class="pl-num {{ $margin < 10 ? 'pl-warn' : 'pl-muted' }}" style="font-size:.75rem">{{ number_format($margin, 1) }}%</div>
                                @endif
                            </td>
                        @endif

                        <td>
                            <flux:badge size="sm" :color="$isOut ? 'red' : ($isLow ? 'amber' : 'green')">
                                <span class="pl-num">{{ $qty($stock) ?: '0' }} {{ $product->unit->label() }}</span>
                            </flux:badge>
                        </td>

                        <td>
                            <div class="pl-actions">
                                <flux:button size="sm" variant="ghost" icon="truck" wire:click="openFitments({{ $product->id }})">
                                    @if ($product->fitments_count > 0)
                                        {{ $product->fitments_count }}
                                    @else
                                        <span class="pl-warn">{{ __('بدون توافق') }}</span>
                                    @endif
                                </flux:button>

                                @if ($canManage)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('المزيد')" />

                                        <flux:menu>
                                            @can('update', $product)
                                                <flux:menu.item icon="pencil" wire:click="openEdit({{ $product->id }})">{{ __('تعديل') }}</flux:menu.item>
                                            @endcan
                                            @can('create', App\Models\Product::class)
                                                <flux:menu.item icon="document-duplicate" wire:click="openDuplicate({{ $product->id }})">{{ __('نسخ كنوع تاني') }}</flux:menu.item>
                                            @endcan
                                            @can('adjustStock', $product)
                                                <flux:menu.item icon="adjustments-horizontal" wire:click="openAdjust({{ $product->id }})">{{ __('تسوية مخزون') }}</flux:menu.item>
                                            @endcan
                                            @can('update', $product)
                                                <flux:menu.item :icon="$product->is_active ? 'pause-circle' : 'play-circle'" wire:click="toggleActive({{ $product->id }})">
                                                    {{ $product->is_active ? __('إيقاف') : __('تفعيل') }}
                                                </flux:menu.item>
                                            @endcan
                                            @can('delete', $product)
                                                <flux:menu.separator />
                                                <flux:menu.item
                                                    icon="trash"
                                                    variant="danger"
                                                    wire:click="delete({{ $product->id }})"
                                                    wire:confirm="{{ __('هل أنت متأكد من حذف هذا المنتج؟') }}"
                                                >
                                                    {{ __('حذف') }}
                                                </flux:menu.item>
                                            @endcan
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="pl-empty">
                                <flux:icon name="cube" class="size-10 pl-muted" />
                                @if ($this->hasActiveFilters)
                                    <flux:heading>{{ __('مفيش منتجات مطابقة للفلاتر.') }}</flux:heading>
                                    <flux:button size="sm" variant="filled" wire:click="resetFilters">{{ __('مسح الفلاتر') }}</flux:button>
                                @else
                                    <flux:heading>{{ __('لسه مفيش منتجات.') }}</flux:heading>
                                    @if ($canManage)
                                        <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreate">{{ __('أضف أول منتج') }}</flux:button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Footer --}}
    <div class="pl-footer">
        <div>{{ $products->links() }}</div>

        <div class="pl-filter-bar">
            <flux:text class="pl-muted">{{ __('عدد النتائج') }}: {{ $products->total() }}</flux:text>
            <div class="pl-per">
                <flux:select wire:model.live="perPage" size="sm">
                    <flux:select.option value="15">15</flux:select.option>
                    <flux:select.option value="30">30</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    {{-- Bulk bar --}}
    @if ($canManage && count($selected) > 0)
        <div class="pl-bulk">
            <span class="pl-bulk-count">{{ __(':n محدد', ['n' => count($selected)]) }}</span>

            <flux:button size="sm" variant="filled" icon="play-circle" wire:click="bulk('activate')">{{ __('تفعيل') }}</flux:button>
            <flux:button size="sm" variant="filled" icon="pause-circle" wire:click="bulk('deactivate')">{{ __('إيقاف') }}</flux:button>
            <flux:button
                size="sm"
                variant="danger"
                icon="trash"
                wire:click="bulk('delete')"
                wire:confirm="{{ __('هيتم حذف المنتجات المحددة اللي رصيدها صفر فقط. متأكد؟') }}"
            >
                {{ __('حذف') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" wire:click="clearSelection">{{ __('إلغاء التحديد') }}</flux:button>
        </div>
    @endif

    {{-- ===================== Product form modal ===================== --}}
    <flux:modal name="product-form" class="pl-modal-lg">
        <form wire:submit="save" class="pl-stack">
            <flux:heading size="lg">
                @if ($form->editingId) {{ __('تعديل منتج') }}
                @elseif ($form->duplicateFromId) {{ __('نسخ منتج كنوع تاني') }}
                @else {{ __('منتج جديد') }}
                @endif
            </flux:heading>

            @if ($form->duplicateFromId)
                <div class="pl-note">
                    <div>{{ __('البيانات اتنسخت من منتج موجود. غيّر النوع والسعر والباركود حسب القطعة الجديدة.') }}</div>
                    <div style="margin-top:.5rem">
                        <flux:checkbox wire:model="form.copyFitments" :label="__('انسخ السيارات المتوافقة كمان')" />
                    </div>
                </div>
            @endif

            @if ($this->possibleDuplicate)
                <div class="pl-note is-warn">
                    {{ __('فيه منتج بنفس رقم القطعة والنوع:') }}
                    <strong>{{ $this->possibleDuplicate->name }}</strong>
                    — {{ __('تأكد إنه مش تكرار.') }}
                </div>
            @endif

            {{-- Identity --}}
            <div class="pl-section">
                <div class="pl-section-title">{{ __('بيانات القطعة') }}</div>

                <flux:input wire:model="form.name" :label="__('اسم المنتج')" required />

                <div class="pl-grid">
                    <flux:input wire:model.live.debounce.400ms="form.part_number" :label="__('رقم القطعة (Part No.)')" dir="ltr" />
                    <flux:input wire:model="form.oem_number" :label="__('رقم OEM')" dir="ltr" />

                    <div class="pl-inline">
                        <flux:input wire:model="form.barcode" :label="__('الباركود')" dir="ltr" />
                        <flux:button type="button" variant="filled" icon="sparkles" wire:click="generateBarcode" :tooltip="__('توليد باركود داخلي')" />
                    </div>
                </div>

                <div class="pl-grid">
                    <flux:select wire:model.live="form.condition" :label="__('نوع القطعة')">
                        @foreach ($this->conditions as $c)
                            <flux:select.option value="{{ $c->value }}">{{ $c->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.unit" :label="__('الوحدة')">
                        @foreach ($this->units as $u)
                            <flux:select.option value="{{ $u->value }}">{{ $u->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.category_id" :label="__('الفئة')">
                        <flux:select.option value="">{{ __('بدون') }}</flux:select.option>
                        @foreach ($this->categories as $cat)
                            <flux:select.option value="{{ $cat->id }}">{{ $cat->parent ? $cat->parent->name.' › ' : '' }}{{ $cat->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="pl-grid">
                    <flux:select wire:model="form.brand_id" :label="__('الماركة (الشركة المصنّعة)')">
                        <flux:select.option value="">{{ __('بدون') }}</flux:select.option>
                        @foreach ($this->brands as $brand)
                            <flux:select.option value="{{ $brand->id }}">{{ $brand->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.newBrand" :label="__('أو أضف ماركة جديدة')" />
                </div>
            </div>

            {{-- Pricing --}}
            <div class="pl-section">
                <div class="pl-section-title">{{ __('الأسعار والتنبيه') }}</div>
<div class="pl-grid">
    <flux:input wire:model.live.debounce.400ms="form.retail_price" type="number" step="0.01" min="0" :label="__('سعر القطاعي')" required />
    <flux:input wire:model.live.debounce.400ms="form.wholesale_price" type="number" step="0.01" min="0" :label="__('سعر الجملة')" required />
    <flux:input wire:model="form.min_stock_alert" type="number" step="0.001" min="0" :label="__('حد تنبيه النقص')" required />
</div>

                @php
                    $formCost = $form->editingId ? (float) $form->editingAvgCost : (float) $form->opening_cost;
                    $formRetail = (float) $form->retail_price;
                    $formWholesale = (float) $form->wholesale_price;
                @endphp

                @if ($formCost > 0 && $formRetail > 0)
                    <div class="pl-note">
                        {{ $form->editingId ? __('متوسط التكلفة الحالي') : __('تكلفة الرصيد الافتتاحي') }}:
                        <strong class="pl-num">{{ $money($formCost) }}</strong>
                        — {{ __('هامش القطاعي') }}
                        <strong class="pl-num {{ $formRetail < $formCost ? 'pl-bad' : '' }}">{{ number_format((($formRetail - $formCost) / $formRetail) * 100, 1) }}%</strong>
                        @if ($formWholesale > 0)
                            — {{ __('هامش الجملة') }}
                            <strong class="pl-num {{ $formWholesale < $formCost ? 'pl-bad' : '' }}">{{ number_format((($formWholesale - $formCost) / $formWholesale) * 100, 1) }}%</strong>
                        @endif
                        @if ($formRetail < $formCost || ($formWholesale > 0 && $formWholesale < $formCost))
                            <div class="pl-bad">{{ __('تنبيه: السعر أقل من التكلفة.') }}</div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Stock --}}
            @if (! $form->editingId)
                <div class="pl-section">
                    <div class="pl-section-title">{{ __('رصيد افتتاحي (اختياري)') }}</div>
                    <div class="pl-grid">
                       <flux:input wire:model.live.debounce.400ms="form.opening_qty" type="number" step="0.001" min="0" :label="__('الكمية')" />
<flux:input wire:model.live.debounce.400ms="form.opening_cost" type="number" step="0.01" min="0" :label="__('تكلفة الوحدة')" /></div>
                </div>
            @else
    @can('set-stock')
        @php
            $delta = is_numeric($form->new_stock_qty) ? (float) $form->new_stock_qty - (float) $form->editingStock : 0;
        @endphp

        <div class="ui-section">
            <div class="ui-section-title">{{ __('تعديل الكمية (للمالك فقط)') }}</div>

            <div class="ui-grid">
                <flux:input wire:model.live.debounce.300ms="form.new_stock_qty" type="number" step="0.001" min="0" :label="__('الكمية الفعلية')" />
                <flux:input wire:model="form.stock_note" :label="__('السبب')" :placeholder="__('جرد / تصحيح خطأ ...')" />
            </div>

            <div class="ui-note {{ $delta != 0 ? 'is-warn' : '' }}">
                {{ __('الرصيد الحالي') }}: <strong class="ui-num">{{ $qty($form->editingStock) ?: '0' }}</strong>
                @if ($delta != 0)
                    — {{ __('الفرق') }}: <strong class="ui-num" dir="ltr">{{ $delta > 0 ? '+' : '' }}{{ $qty($delta) }}</strong>
                    ({{ __('هيتسجل كحركة تسوية في الدفتر') }})
                @endif
            </div>
        </div>
    @else
        <div class="ui-note">
            {{ __('الرصيد الحالي') }}: <strong class="ui-num">{{ $qty($form->editingStock) ?: '0' }}</strong>
            — {{ __('لتغيير الرصيد استخدم "تسوية مخزون".') }}
        </div>
    @endcan
@endif

            {{-- Misc --}}
            <div class="pl-section">
                <div class="pl-section-title">{{ __('تفاصيل إضافية') }}</div>

                <div class="pl-grid">
                    <flux:input wire:model="form.location" :label="__('مكان التخزين (رف / مخزن)')" />
                    <div style="display:flex;align-items:flex-end;padding-bottom:.4rem">
                        <flux:checkbox wire:model="form.is_active" :label="__('نشط')" />
                    </div>
                </div>

                <flux:textarea wire:model="form.notes" :label="__('ملاحظات')" rows="2" />
            </div>

            <div class="pl-form-foot">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('إلغاء') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                    {{ $form->editingId ? __('حفظ التعديلات') : __('حفظ المنتج') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ===================== Stock adjustment modal ===================== --}}
    <flux:modal name="stock-adjust" class="pl-modal-md">
        @php
            $ap = $this->adjustProduct;
            $newQty = $ap ? (float) $ap->stock_qty + (float) $adjustQty : null;
        @endphp

        <form wire:submit="saveAdjust" class="pl-stack">
            <flux:heading size="lg">{{ __('تسوية مخزون') }}</flux:heading>

            @if ($ap)
                <div class="pl-note">
                    <div><strong>{{ $ap->name }}</strong></div>
                    <div>{{ __('الرصيد الحالي') }}: <strong class="pl-num">{{ $qty($ap->stock_qty) ?: '0' }} {{ $ap->unit->label() }}</strong></div>
                </div>
            @endif

            <flux:text>{{ __('اكتب الكمية بالموجب للزيادة وبالسالب للعجز أو التالف.') }}</flux:text>

            <flux:input wire:model.live.debounce.300ms="adjustQty" type="number" step="0.001" :label="__('الكمية (+ / -)')" required />

            @if ($ap && $adjustQty !== '' && is_numeric($adjustQty) && (float) $adjustQty !== 0.0)
                <div class="pl-note {{ $newQty < 0 ? 'is-warn' : '' }}">
                    {{ __('الرصيد بعد التسوية') }}: <strong class="pl-num">{{ $qty($newQty) ?: '0' }}</strong>
                    @if ($newQty < 0) — {{ __('الرصيد هيبقى سالب.') }} @endif
                </div>
            @endif

            <div class="pl-reasons">
                @foreach ([__('جرد'), __('تالف'), __('عجز'), __('هدية / عينة'), __('تصحيح خطأ إدخال')] as $reason)
                    <flux:button type="button" size="xs" variant="filled" wire:click="$set('adjustNotes', '{{ $reason }}')">{{ $reason }}</flux:button>
                @endforeach
            </div>

            <flux:input wire:model="adjustNotes" :label="__('السبب')" required />

            <div class="pl-form-foot">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('إلغاء') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('تسجيل التسوية') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ===================== Fitments modal ===================== --}}
    <flux:modal name="product-fitments" class="pl-modal-lg">
        @php
            $fp = $this->fitProduct;
            $grouped = $this->fitments->groupBy(fn ($f) => $f->variant->vehicleModel->make->name.' '.$f->variant->vehicleModel->name);
        @endphp

        <div class="pl-stack">
            <div>
                <flux:heading size="lg">{{ __('السيارات المتوافقة') }}</flux:heading>
                @if ($fp)
                    <flux:text>{{ $fp->name }} <span class="pl-sub">{{ $fp->part_number }}</span></flux:text>
                @endif
            </div>

            @if ($fp)
                @can('update', $fp)
                    <form wire:submit="addFitment" class="pl-section">
                        <div class="pl-grid">
                            <flux:select wire:model.live="fitMakeId" :label="__('الماركة')">
                                <flux:select.option value="">{{ __('اختر') }}</flux:select.option>
                                @foreach ($this->makes as $make)
                                    <flux:select.option value="{{ $make->id }}">{{ $make->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model.live="fitModelId" :label="__('الموديل')" :disabled="$fitMakeId === ''">
                                <flux:select.option value="">{{ $fitMakeId === '' ? __('اختر الماركة أولًا') : __('اختر') }}</flux:select.option>
                                @foreach ($this->fitModels as $model)
                                    <flux:select.option value="{{ $model->id }}">{{ $model->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="fitVariantId" :label="__('الفئة / السنة')" :disabled="$this->fitVariants->isEmpty()">
                                <flux:select.option value="">
                                    @if ($fitModelId === '') {{ __('اختر الموديل أولًا') }}
                                    @elseif ($this->fitVariants->isEmpty()) {{ __('لا توجد فئات لهذا الموديل') }}
                                    @else {{ __('اختر') }}
                                    @endif
                                </flux:select.option>
                                @foreach ($this->fitVariants as $variant)
                                    <flux:select.option value="{{ $variant->id }}">
                                        {{ $variant->name }} ({{ $variant->year_from }}–{{ $variant->year_to ?? __('الآن') }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        @error('fitVariantId') <flux:text class="pl-bad">{{ $message }}</flux:text> @enderror
                        @error('fitModelId') <flux:text class="pl-bad">{{ $message }}</flux:text> @enderror

                        <div class="pl-form-foot">
                            <flux:button type="button" size="sm" variant="filled" icon="squares-plus" wire:click="addAllVariants" :disabled="$this->fitVariants->isEmpty()">
                                {{ __('أضف كل فئات الموديل') }}
                            </flux:button>
                            <flux:button type="submit" size="sm" variant="primary" icon="plus" :disabled="$this->fitVariants->isEmpty()">
                                {{ __('إضافة') }}
                            </flux:button>
                        </div>
                    </form>
                @endcan
            @endif

            @forelse ($grouped as $label => $items)
                <div class="pl-group" wire:key="fit-group-{{ md5($label) }}">
                    <div class="pl-group-title">{{ $label }}</div>
                    @foreach ($items as $fitment)
                        <div class="pl-group-row" wire:key="fitment-{{ $fitment->id }}">
                            <span>
                                {{ $fitment->variant->name }}
                                <span class="pl-muted">({{ $fitment->variant->year_from }}–{{ $fitment->variant->year_to ?? __('الآن') }})</span>
                                @if ($fitment->variant->engine_code)
                                    <span class="pl-sub">{{ $fitment->variant->engine_code }}</span>
                                @endif
                            </span>

                            @if ($fp)
                                @can('update', $fp)
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeFitment({{ $fitment->id }})" :aria-label="__('إزالة')" />
                                @endcan
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="pl-note is-warn">{{ __('لم يتم ربط هذا المنتج بأي سيارة بعد. المنتجات بدون توافق مش هتظهر في البحث بالسيارة.') }}</div>
            @endforelse
        </div>
    </flux:modal>
</div>