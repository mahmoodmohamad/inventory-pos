<?php

use App\Models\Category;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('الفئات')] class extends Component {
    public string $name = '';
    public ?int $parentId = null;
    public ?int $editingId = null;
    public bool $showForm = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    #[Computed]
    public function categories()
    {
        return Category::with('parent')->orderBy('name')->get();
    }

    #[Computed]
    public function parents()
    {
        return Category::whereNull('parent_id')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Category::class);
        $this->reset('name', 'parentId', 'editingId');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $cat = Category::findOrFail($id);
        $this->authorize('update', $cat);
        $this->editingId = $cat->id;
        $this->name = $cat->name;
        $this->parentId = $cat->parent_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'parentId' => ['nullable', 'integer'],
        ]);

        if ($this->editingId) {
            $cat = Category::findOrFail($this->editingId);
            $this->authorize('update', $cat);
            $cat->update(['name' => $data['name'], 'parent_id' => $data['parentId']]);
        } else {
            $this->authorize('create', Category::class);
            Category::create(['name' => $data['name'], 'parent_id' => $data['parentId']]);
        }

        $this->reset('name', 'parentId', 'editingId', 'showForm');
        unset($this->categories, $this->parents);
        Flux::toast(text: __('تم الحفظ.'));
    }

    public function delete(int $id): void
    {
        $cat = Category::findOrFail($id);
        $this->authorize('delete', $cat);

        if ($cat->children()->exists()) {
            Flux::toast(variant: 'danger', text: __('لا يمكن حذف فئة لها فئات فرعية.'));
            return;
        }
        $cat->delete();
        unset($this->categories);
        Flux::toast(text: __('تم الحذف.'));
    }

    public function cancel(): void
    {
        $this->reset('name', 'parentId', 'editingId', 'showForm');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('الفئات') }}</flux:heading>
        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('فئة جديدة') }}</flux:button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:input wire:model="name" :label="__('اسم الفئة')" required />
            <flux:select wire:model="parentId" :label="__('الفئة الأب (اختياري)')">
                <flux:select.option value="">{{ __('بدون') }}</flux:select.option>
                @foreach ($this->parents as $p)
                    @if ($p->id !== $editingId)
                        <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>
            <div class="flex gap-2">
    <flux:button type="submit" variant="primary">
        {{ $editingId ? __('تحديث') : __('حفظ') }}
    </flux:button>

    <flux:button type="button" variant="ghost" wire:click="cancel">
        {{ __('إلغاء') }}
    </flux:button>
</div>
        </form>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-right text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('الاسم') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('الفئة الأب') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td class="px-4 py-3">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-zinc-500">
                            {{ $category->parent?->name ?? __('—') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="edit({{ $category->id }})"
                                >
                                    {{ __('تعديل') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash"
                                    wire:click="delete({{ $category->id }})"
                                    wire:confirm="{{ __('هل أنت متأكد من حذف هذه الفئة؟') }}"
                                >
                                    {{ __('حذف') }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-zinc-500">
                            {{ __('لا توجد فئات بعد.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>