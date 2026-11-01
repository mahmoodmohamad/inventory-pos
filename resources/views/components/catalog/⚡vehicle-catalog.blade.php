<?php

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function updatedSearch(): void
    {
        $this->expanded = [];
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
    }

    public function toggleMakeStatus(int $id): void
    {
        $make = VehicleMake::query()->findOrFail($id);
        $make->update(['is_active' => ! $make->is_active]);
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

        $this->showModelModal = false;
        $this->reset('editingModelId', 'selectedMakeId', 'modelName', 'modelNameAr');
    }

    public function toggleModelStatus(int $id): void
    {
        $model = VehicleModel::query()->findOrFail($id);
        $model->update(['is_active' => ! $model->is_active]);
    }

    // ---------- Delete ----------

    public function askDelete(string $type, int $id): void
    {
        abort_unless(in_array($type, ['make', 'model'], true), 422);

        $name = $type === 'make'
            ? VehicleMake::query()->findOrFail($id)->name
            : VehicleModel::query()->findOrFail($id)->name;

        $this->confirmAction = $type;
        $this->confirmId = $id;
        $this->confirmMessage = "Delete {$name}?";
    }

    public function cancelDelete(): void
    {
        $this->reset('confirmAction', 'confirmId', 'confirmMessage');
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