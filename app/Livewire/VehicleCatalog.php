<?php

namespace App\Livewire;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Vehicle Catalog Management')]
class VehicleCatalog extends Component
{
    public string $search = '';

    public string $statusFilter = 'all';

    public string $filter = 'all';

    public bool $showMakeModal = false;

    public bool $showModelModal = false;

    public ?int $editingMakeId = null;

    public ?int $editingModelId = null;

    public ?int $selectedMakeId = null;

    public string $makeName = '';

    public string $modelName = '';

    public string $modelNameAr = '';

    public bool $makeActive = true;

    public bool $modelActive = true;

    public array $expanded = [];

    public ?string $confirmAction = null;

    public ?int $confirmId = null;

    public string $confirmMessage = '';

    protected function rules(): array
    {
        return [
            'makeName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('vehicle_makes', 'name')
                    ->ignore($this->editingMakeId),
            ],
            'modelName' => [
                'required',
                'string',
                'max:100',
            ],
            'modelNameAr' => [
                'nullable',
                'string',
                'max:100',
            ],
            'selectedMakeId' => [
                'required',
                'integer',
                'exists:vehicle_makes,id',
            ],
        ];
    }

    public function updatedSearch(): void
    {
        $this->expanded = [];
    }

    public function updatedFilter(): void
    {
        if (! in_array($this->filter, ['all', 'active', 'inactive'], true)) {
            $this->filter = 'all';
        }
    }

    public function openMakeModal(?int $id = null): void
    {
        $this->resetValidation();

        $this->editingMakeId = $id;

        if ($id) {
            $make = VehicleMake::findOrFail($id);

            $this->makeName = $make->name;
            $this->makeActive = (bool) $make->is_active;
        } else {
            $this->makeName = '';
            $this->makeActive = true;
        }

        $this->showMakeModal = true;
    }

    public function saveMake(): void
    {
        $this->validate([
            'makeName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('vehicle_makes', 'name')
                    ->ignore($this->editingMakeId),
            ],
        ]);

        DB::transaction(function () {
            $make = $this->editingMakeId
                ? VehicleMake::findOrFail($this->editingMakeId)
                : new VehicleMake;

            $make->name = trim($this->makeName);
            $make->is_active = $this->makeActive;
            $make->save();
        });

        $this->showMakeModal = false;

        $this->reset('editingMakeId', 'makeName');

        $this->notify('Vehicle make saved successfully.');
    }

    public function openModelModal(int $makeId, ?int $modelId = null): void
    {
        $make = VehicleMake::findOrFail($makeId);

        $this->resetValidation();

        $this->selectedMakeId = $make->id;
        $this->editingModelId = $modelId;

        if ($modelId) {
            $model = VehicleModel::query()
                ->where('vehicle_make_id', $makeId)
                ->findOrFail($modelId);

            $this->modelName = $model->name;
            $this->modelNameAr = $model->name_ar ?? '';
            $this->modelActive = (bool) $model->is_active;
        } else {
            $this->modelName = '';
            $this->modelNameAr = '';
            $this->modelActive = true;
        }

        $this->showModelModal = true;
    }

    public function saveModel(): void
    {
        $this->validate([
            'selectedMakeId' => [
                'required',
                'exists:vehicle_makes,id',
            ],
            'modelName' => [
                'required',
                'string',
                'max:100',
            ],
            'modelNameAr' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $exists = VehicleModel::query()
            ->where('vehicle_make_id', $this->selectedMakeId)
            ->where('name', trim($this->modelName))
            ->when(
                $this->editingModelId,
                fn (Builder $query) => $query->where(
                    'id',
                    '!=',
                    $this->editingModelId
                )
            )
            ->exists();

        if ($exists) {
            $this->addError(
                'modelName',
                'This model already exists for this make.'
            );

            return;
        }

        DB::transaction(function () {
            $model = $this->editingModelId
                ? VehicleModel::query()
                    ->where('vehicle_make_id', $this->selectedMakeId)
                    ->findOrFail($this->editingModelId)
                : new VehicleModel;

            $model->vehicle_make_id = $this->selectedMakeId;
            $model->name = trim($this->modelName);
            $model->name_ar = trim($this->modelNameAr) ?: null;
            $model->is_active = $this->modelActive;

            $model->save();
        });

        $this->showModelModal = false;

        $this->reset(
            'editingModelId',
            'selectedMakeId',
            'modelName',
            'modelNameAr'
        );

        $this->notify('Vehicle model saved successfully.');
    }

    public function toggleMake(int $id): void
    {
        $make = VehicleMake::findOrFail($id);

        $make->is_active = ! $make->is_active;
        $make->save();

        $this->notify('Make status updated.');
    }

    public function toggleModel(int $id): void
    {
        $model = VehicleModel::findOrFail($id);

        $model->is_active = ! $model->is_active;
        $model->save();

        $this->notify('Model status updated.');
    }

    public function askDeleteMake(int $id): void
    {
        $make = VehicleMake::findOrFail($id);

        $this->confirmAction = 'make';
        $this->confirmId = $id;
        $this->confirmMessage = "Delete {$make->name}?";
    }

    public function askDeleteModel(int $id): void
    {
        $model = VehicleModel::findOrFail($id);

        $this->confirmAction = 'model';
        $this->confirmId = $id;
        $this->confirmMessage = "Delete {$model->name}?";
    }

    public function cancelDelete(): void
    {
        $this->reset(
            'confirmAction',
            'confirmId',
            'confirmMessage'
        );
    }

    public function confirmDelete(): void
    {
        if (! $this->confirmId || ! $this->confirmAction) {
            return;
        }

        DB::transaction(function () {
            if ($this->confirmAction === 'make') {
                $make = VehicleMake::findOrFail($this->confirmId);

                if ($make->models()->exists()) {
                    throw new \RuntimeException(
                        'Cannot delete a make containing models.'
                    );
                }

                $make->delete();
            } elseif ($this->confirmAction === 'model') {
                $model = VehicleModel::findOrFail($this->confirmId);

                if (VehicleVariant::where(
                    'vehicle_model_id',
                    $model->id
                )->exists()) {
                    throw new \RuntimeException(
                        'Cannot delete a model containing variants.'
                    );
                }

                $model->delete();
            }
        });

        $this->cancelDelete();

        $this->notify('Record deleted successfully.');
    }

    public function toggleExpanded(int $id): void
    {
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(
                array_diff($this->expanded, [$id])
            );
        } else {
            $this->expanded[] = $id;
        }
    }

    public function expandAll(): void
    {
        $this->expanded = $this->catalog->pluck('make.id')->all();
    }

    public function collapseAll(): void
    {
        $this->expanded = [];
    }

    public function notify(string $message): void
    {
        $this->dispatch('catalog-notification', message: $message);
    }

    public function getCatalogProperty()
    {
        return VehicleMake::query()
            ->with(['models' => function ($query) {
                $query->withCount('variants')
                    ->orderBy('name');
            }])
            ->when(
                $this->filter !== 'all',
                fn ($query) => $query->where(
                    'is_active',
                    $this->filter === 'active'
                )
            )
            ->orderBy('name')
            ->get()
            ->map(function ($make) {
                $term = mb_strtolower(trim($this->search));

                $makeMatches = $term === ''
                    || str_contains(
                        mb_strtolower($make->name),
                        $term
                    );

                $models = $make->models->filter(function ($model) use (
                    $term,
                    $makeMatches
                ) {
                    return $makeMatches
                        || str_contains(
                            mb_strtolower($model->name),
                            $term
                        )
                        || str_contains(
                            mb_strtolower($model->name_ar ?? ''),
                            $term
                        );
                })->values();

                return [
                    'make' => $make,
                    'models' => $models,
                    'visible' => $makeMatches || $models->isNotEmpty(),
                ];
            })
            ->filter(fn ($row) => $row['visible'])
            ->values();
    }

    public function render()
    {
        $variantCounts = VehicleModel::withCount('variants')
            ->get()
            ->pluck('variants_count', 'id')
            ->toArray();

        return view('components.catalog.⚡vehicle-catalog', [
            'catalog' => $this->catalog,
            'makesTotal' => VehicleMake::count(),
            'modelsTotal' => VehicleModel::count(),
            'variantCounts' => $variantCounts,
            'variantsTotal' => VehicleVariant::count(),
            'activeMakes' => VehicleMake::where('is_active', true)->count(),
            'inactiveMakes' => VehicleMake::where('is_active', false)->count(),
        ]);
    }
}
