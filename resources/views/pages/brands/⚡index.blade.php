<?php

use App\Models\Brand;
use App\Support\Tenancy\TenantContext;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('الماركات')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Brand::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('brands', 'name')
                    ->where('shop_id', app(TenantContext::class)->idOrFail())
                    ->ignore($this->editingId),
            ],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Brand::class);

        $this->reset('editingId', 'name');
        $this->resetValidation();
        Flux::modal('brand-form')->show();
    }

    public function openEdit(int $id): void
    {
        $brand = Brand::query()->findOrFail($id);
        $this->authorize('update', $brand);

        $this->resetValidation();
        $this->editingId = $brand->id;
        $this->name = $brand->name;
        Flux::modal('brand-form')->show();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Brand::class);
            Brand::query()->create(['name' => trim($this->name)]);
        } else {
            $brand = Brand::query()->findOrFail($this->editingId);
            $this->authorize('update', $brand);
            $brand->update(['name' => trim($this->name)]);
        }

        Flux::modal('brand-form')->close();
        $this->reset('editingId', 'name');
        Flux::toast(variant: 'success', text: __('تم الحفظ.'));
    }

    public function delete(int $id): void
    {
        $brand = Brand::query()->findOrFail($id);
        $this->authorize('delete', $brand);

        // الـ composite FK بيرفض الحذف حتى لو المنتج soft-deleted
        if ($brand->products()->withTrashed()->exists()) {
            Flux::toast(variant: 'danger', text: __('لا يمكن حذف ماركة عليها منتجات.'));

            return;
        }

        $brand->delete();
        Flux::toast(text: __('تم الحذف.'));
    }

    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    #[Computed]
    public function brands(): LengthAwarePaginator
    {
        $like = addcslashes(trim($this->search), '%_\\');

        return Brand::query()
            ->withCount('products')
            ->when($like !== '', fn ($q) => $q->where('name', 'like', '%'.$like.'%'))
            ->orderBy('name')
            ->paginate(20);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('الماركات') }}</flux:heading>
            <flux:subheading>{{ __('الشركات المصنّعة للقطع (Bosch, Denso, ...)') }}</flux:subheading>
        </div>

        @can('create', App\Models\Brand::class)
            <flux:button wire:click="openCreate" variant="primary" icon="plus">
                {{ __('ماركة جديدة') }}
            </flux:button>
        @endcan
    </div>

    <div class="max-w-md">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('ابحث عن ماركة')" />
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[480px] text-start text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 text-start font-medium">{{ __('الماركة') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('عدد المنتجات') }}</th>
                    <th class="px-4 py-3 text-start font-medium">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->brands as $brand)
                    <tr wire:key="brand-{{ $brand->id }}">
                        <td class="px-4 py-3 font-medium">{{ $brand->name }}</td>
                        <td class="px-4 py-3"><flux:badge size="sm">{{ $brand->products_count }}</flux:badge></td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                @can('update', $brand)
                                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="openEdit({{ $brand->id }})">
                                        {{ __('تعديل') }}
                                    </flux:button>
                                @endcan
                                @can('delete', $brand)
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        icon="trash"
                                        wire:click="delete({{ $brand->id }})"
                                        wire:confirm="{{ __('هل أنت متأكد من حذف هذه الماركة؟') }}"
                                    />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-10 text-center text-zinc-500">{{ __('لا توجد ماركات.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->brands->links() }}

    <flux:modal name="brand-form" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('تعديل ماركة') : __('ماركة جديدة') }}</flux:heading>

            <flux:input wire:model="name" :label="__('اسم الماركة')" required autofocus />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('إلغاء') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('حفظ') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>