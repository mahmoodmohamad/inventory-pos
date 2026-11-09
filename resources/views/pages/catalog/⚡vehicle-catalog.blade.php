<?php

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vehicle catalog')] class extends Component
{
    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showMakeModal = false;

    public bool $showModelModal = false;

    public ?int $editingMakeId = null;

    public ?int $editingModelId = null;

    public ?int $selectedMakeId = null;

    public string $makeName = '';

    public bool $makeIsActive = true;

    public string $modelName = '';

    public string $modelNameAr = '';

    public bool $modelIsActive = true;

    /** @var array<int, int> */
    public array $expanded = [];

    public ?string $confirmAction = null;

    public ?int $confirmId = null;

    public string $confirmMessage = '';

    /** Runs on every request (mount + each Livewire update). */
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-catalog'), 403);
    }

    public function updatedSearch(): void
    {
        $this->expanded = [];
    }

    public function toggleExpand(int $makeId): void
    {
        $this->expanded = in_array($makeId, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$makeId]))
            : [...$this->expanded, $makeId];
    }

    public function expandAll(): void
    {
        $this->expanded = $this->catalog->pluck('make.id')->all();
    }

    public function collapseAll(): void
    {
        $this->expanded = [];
    }

    // ---------- Make ----------

    public function openMakeModal(): void
    {
        $this->resetValidation();
        $this->reset('editingMakeId', 'makeName');
        $this->makeIsActive = true;
        $this->showMakeModal = true;
    }

    public function editMake(int $id): void
    {
        $make = VehicleMake::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingMakeId = $make->id;
        $this->makeName = $make->name;
        $this->makeIsActive = $make->is_active;
        $this->showMakeModal = true;
    }

    public function saveMake(): void
    {
        $this->validate([
            'makeName' => ['required', 'string', 'max:100',
                Rule::unique('vehicle_makes', 'name')->ignore($this->editingMakeId)],
        ]);

        $name = trim($this->makeName);

        if ($this->editingMakeId === null) {
            VehicleMake::create([
                'name' => $name,
                'slug' => $this->uniqueMakeSlug($name),
                'is_active' => $this->makeIsActive,
            ]);
        } else {
            VehicleMake::query()->findOrFail($this->editingMakeId)
                ->update(['name' => $name, 'is_active' => $this->makeIsActive]);
        }

        $this->showMakeModal = false;
        $this->reset('editingMakeId', 'makeName');
        unset($this->catalog);
    }

    public function toggleMakeStatus(int $id): void
    {
        $make = VehicleMake::query()->findOrFail($id);
        $make->update(['is_active' => ! $make->is_active]);
        unset($this->catalog);
    }

    // ---------- Model ----------

    public function openModelModal(int $makeId): void
    {
        VehicleMake::query()->findOrFail($makeId);

        $this->resetValidation();
        $this->reset('editingModelId', 'modelName', 'modelNameAr');
        $this->modelIsActive = true;
        $this->selectedMakeId = $makeId;
        $this->showModelModal = true;
    }

    public function editModel(int $id): void
    {
        $model = VehicleModel::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingModelId = $model->id;
        $this->selectedMakeId = $model->vehicle_make_id;
        $this->modelName = $model->name;
        $this->modelNameAr = $model->name_ar ?? '';
        $this->modelIsActive = $model->is_active;
        $this->showModelModal = true;
    }

    public function saveModel(): void
    {
        $this->validate([
            'selectedMakeId' => ['required', 'integer', Rule::exists('vehicle_makes', 'id')],
            'modelName' => ['required', 'string', 'max:100'],
            'modelNameAr' => ['nullable', 'string', 'max:100'],
        ]);

        $name = trim($this->modelName);
        $slug = Str::slug($name) ?: 'model';

        $duplicate = VehicleModel::query()
            ->where('vehicle_make_id', $this->selectedMakeId)
            ->where('slug', $slug)
            ->when($this->editingModelId, fn ($q) => $q->where('id', '!=', $this->editingModelId))
            ->exists();

        if ($duplicate) {
            $this->addError('modelName', 'This model already exists for this make.');

            return;
        }

        $data = [
            'vehicle_make_id' => $this->selectedMakeId,
            'name' => $name,
            'name_ar' => trim($this->modelNameAr) !== '' ? trim($this->modelNameAr) : null,
            'slug' => $slug,
            'is_active' => $this->modelIsActive,
        ];

        if ($this->editingModelId === null) {
            VehicleModel::create($data);
        } else {
            VehicleModel::query()->findOrFail($this->editingModelId)->update($data);
        }

        // make sure the make is open so the new model is visible
        if (! in_array($this->selectedMakeId, $this->expanded, true)) {
            $this->expanded[] = $this->selectedMakeId;
        }

        $this->showModelModal = false;
        $this->reset('editingModelId', 'selectedMakeId', 'modelName', 'modelNameAr');
        unset($this->catalog, $this->variantCounts, $this->modelsTotal);
    }

    public function toggleModelStatus(int $id): void
    {
        $model = VehicleModel::query()->findOrFail($id);
        $model->update(['is_active' => ! $model->is_active]);
        unset($this->catalog);
    }

    // ---------- Delete ----------

    public function askDelete(string $type, int $id): void
    {
        abort_unless(in_array($type, ['make', 'model'], true), 422);

        $name = $type === 'make'
            ? VehicleMake::query()->findOrFail($id)->name
            : VehicleModel::query()->findOrFail($id)->name;

        $this->resetErrorBag('confirm');
        $this->confirmAction = $type;
        $this->confirmId = $id;
        $this->confirmMessage = "Delete {$name}?";
    }

    public function cancelDelete(): void
    {
        $this->reset('confirmAction', 'confirmId', 'confirmMessage');
        $this->resetErrorBag('confirm');
    }

    public function confirmDelete(): void
    {
        if ($this->confirmId === null || $this->confirmAction === null) {
            return;
        }

        DB::transaction(function (): void {
            if ($this->confirmAction === 'make') {
                $make = VehicleMake::query()->findOrFail($this->confirmId);

                if ($make->models()->exists()) {
                    $this->addError('confirm', 'Cannot delete a make that still has models.');

                    return;
                }

                $make->delete();
            } else {
                $model = VehicleModel::query()->findOrFail($this->confirmId);

                if ($model->variants()->exists()) {
                    $this->addError('confirm', 'Cannot delete a model that still has variants.');

                    return;
                }

                $model->delete();
            }
        });

        if (! $this->getErrorBag()->has('confirm')) {
            $this->cancelDelete();
            unset($this->catalog, $this->variantCounts, $this->modelsTotal);
        }
    }

    // ---------- Data ----------

    private function uniqueMakeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'make';
        $slug = $base;
        $i = 2;

        while (VehicleMake::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @return Collection<int, array{make: VehicleMake, models: Collection<int, VehicleModel>, visible: bool}>
     */
    #[Computed]
    public function catalog(): Collection
    {
        $term = mb_strtolower(trim($this->search));

        return VehicleMake::query()
            ->with(['models' => fn ($q) => $q->orderBy('name')])
            ->when($this->statusFilter !== 'all',
                fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('name')
            ->get()
            ->map(function (VehicleMake $make) use ($term): array {
                $makeMatches = $term === '' || str_contains(mb_strtolower($make->name), $term);

                $models = $make->models->filter(fn (VehicleModel $m) => $makeMatches
                    || str_contains(mb_strtolower($m->name), $term)
                    || str_contains(mb_strtolower($m->name_ar ?? ''), $term))->values();

                return [
                    'make' => $make,
                    'models' => $models,
                    'visible' => $makeMatches || $models->isNotEmpty(),
                ];
            })
            ->filter(fn (array $row) => $row['visible'])
            ->values();
    }

    /** @return Collection<int, VehicleMake> */
    #[Computed]
    public function makes(): Collection
    {
        return VehicleMake::query()->orderBy('name')->get();
    }

    /** @return array<int, int> */
    #[Computed]
    public function variantCounts(): array
    {
        return VehicleVariant::query()
            ->selectRaw('vehicle_model_id, count(*) as total')
            ->groupBy('vehicle_model_id')
            ->pluck('total', 'vehicle_model_id')
            ->all();
    }

    #[Computed]
    public function modelsTotal(): int
    {
        return VehicleModel::query()->count();
    }
};
?>

<div class="mx-auto max-w-5xl space-y-6">
    @php
        $catalog = $this->catalog;
        $counts = $this->variantCounts;
        $searching = trim($search) !== '';
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Vehicle catalog</flux:heading>
            <flux:subheading>Shared makes and models, visible to all shops</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button variant="filled" icon="squares-2x2" :href="route('catalog.vehicle-variants')" wire:navigate>
                Variants
            </flux:button>
            <flux:button variant="primary" icon="plus" wire:click="openMakeModal">Add make</flux:button>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">Makes</div>
            <div class="mt-1 text-2xl font-semibold">{{ $catalog->count() }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="text-sm text-zinc-500">Models (all makes)</div>
            <div class="mt-1 text-2xl font-semibold">{{ $this->modelsTotal }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable type="search"
                placeholder="Search makes or models" />
        </div>
        <div class="w-40">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="all">All status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </div>
        <flux:button size="sm" variant="ghost" icon="chevron-double-down" wire:click="expandAll">Expand all</flux:button>
        <flux:button size="sm" variant="ghost" icon="chevron-double-up" wire:click="collapseAll">Collapse all</flux:button>
    </div>

    {{-- Delete confirmation --}}
    @if ($confirmAction !== null)
        <div class="space-y-3 rounded-xl border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
            <flux:text class="font-medium">{{ $confirmMessage }}</flux:text>
            @error('confirm')
                <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
            @enderror
            <div class="flex gap-2">
                <flux:button size="sm" variant="danger" wire:click="confirmDelete">Delete</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancelDelete">Cancel</flux:button>
            </div>
        </div>
    @endif

    {{-- Makes --}}
    <div class="space-y-3" wire:loading.class="opacity-60" wire:target="search,statusFilter">
        @forelse ($catalog as $row)
            @php
                $make = $row['make'];
                $open = $searching || in_array($make->id, $expanded, true);
            @endphp

            <section wire:key="make-{{ $make->id }}"
                class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 {{ $make->is_active ? '' : 'opacity-70' }}">

                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <button type="button" wire:click="toggleExpand({{ $make->id }})"
                        aria-expanded="{{ $open ? 'true' : 'false' }}"
                        class="flex min-w-0 flex-1 items-center gap-3 text-start">
                        <flux:icon :name="$open ? 'chevron-down' : 'chevron-right'" variant="micro" />
                        <span class="truncate text-lg font-semibold">{{ $make->name }}</span>
                        <flux:badge size="sm" :color="$make->is_active ? 'green' : 'zinc'">
                            {{ $make->is_active ? 'Active' : 'Inactive' }}
                        </flux:badge>
                        <flux:badge size="sm">{{ $row['models']->count() }} models</flux:badge>
                    </button>

                    <div class="flex items-center gap-1">
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="openModelModal({{ $make->id }})">
                            Model
                        </flux:button>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="More" />
                            <flux:menu>
                                <flux:menu.item icon="pencil" wire:click="editMake({{ $make->id }})">Edit</flux:menu.item>
                                <flux:menu.item :icon="$make->is_active ? 'pause-circle' : 'play-circle'"
                                    wire:click="toggleMakeStatus({{ $make->id }})">
                                    {{ $make->is_active ? 'Deactivate' : 'Activate' }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger"
                                    wire:click="askDelete('make', {{ $make->id }})">Delete</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>

                @if ($open)
                    <ul class="divide-y divide-zinc-200 border-t border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @forelse ($row['models'] as $model)
                            <li wire:key="model-{{ $model->id }}"
                                class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 ps-10 {{ $model->is_active ? '' : 'opacity-60' }}">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ $model->name }}</span>
                                    @if ($model->name_ar)
                                        <span class="text-sm text-zinc-500" dir="rtl">{{ $model->name_ar }}</span>
                                    @endif
                                    <flux:badge size="sm">{{ $counts[$model->id] ?? 0 }} variants</flux:badge>
                                    @unless ($model->is_active)
                                        <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                                    @endunless
                                </div>

                                <div class="flex items-center gap-1">
                                    <flux:button size="xs" variant="ghost" icon="pencil"
                                        wire:click="editModel({{ $model->id }})" aria-label="Edit model" />
                                    <flux:button size="xs" variant="ghost"
                                        :icon="$model->is_active ? 'pause-circle' : 'play-circle'"
                                        wire:click="toggleModelStatus({{ $model->id }})"
                                        :aria-label="$model->is_active ? 'Deactivate model' : 'Activate model'" />
                                    <flux:button size="xs" variant="ghost" icon="trash"
                                        wire:click="askDelete('model', {{ $model->id }})" aria-label="Delete model" />
                                </div>
                            </li>
                        @empty
                            <li class="px-4 py-4 ps-10 text-sm text-zinc-500">
                                {{ $searching ? 'No models match your search.' : 'No models yet.' }}
                            </li>
                        @endforelse
                    </ul>
                @endif
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
                <flux:icon.truck class="mx-auto size-8 text-zinc-400" />
                <p class="mt-2 font-medium">No makes found</p>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $searching || $statusFilter !== 'all' ? 'Try a different search or filter.' : 'Add the first make to get started.' }}
                </p>
            </div>
        @endforelse
    </div>

    {{-- Make modal --}}
    <flux:modal wire:model.self="showMakeModal" class="w-full max-w-md">
        <form wire:submit="saveMake" class="space-y-5">
            <flux:heading size="lg">{{ $editingMakeId ? 'Edit make' : 'Add make' }}</flux:heading>

            <flux:input wire:model="makeName" label="Name" required autofocus />
            <flux:checkbox wire:model="makeIsActive" label="Active" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showMakeModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Model modal --}}
    <flux:modal wire:model.self="showModelModal" class="w-full max-w-md">
        <form wire:submit="saveModel" class="space-y-5">
            <flux:heading size="lg">{{ $editingModelId ? 'Edit model' : 'Add model' }}</flux:heading>

            <flux:select wire:model="selectedMakeId" label="Make">
                @foreach ($catalog as $row)
                    <flux:select.option value="{{ $row['make']->id }}">{{ $row['make']->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="modelName" label="Name" required autofocus />
            <flux:input wire:model="modelNameAr" label="Arabic name" dir="rtl" />
            <flux:checkbox wire:model="modelIsActive" label="Active" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModelModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>