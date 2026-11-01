<?php

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Vehicle variants')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $modelFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $vehicle_model_id = '';

    public string $name = '';

    public string $name_ar = '';

    public string $slug = '';

    public string $year_from = '';

    public string $year_to = '';

    public string $engine_code = '';

    public string $engine_capacity_cc = '';

    public string $fuel_type = '';

    public string $transmission = '';

    public bool $is_active = true;

    /**
     * @return array<string, array<int, mixed>>
     */
	 
	 public function mount(): void
{
    abort_unless(Gate::allows('manage-catalog'), 403);
}
    protected function rules(): array
    {
        return [
            'vehicle_model_id' => ['required', 'integer', Rule::exists('vehicle_models', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('vehicle_variants', 'slug')
                    ->where('vehicle_model_id', $this->vehicle_model_id)
                    ->ignore($this->editingId),
            ],
            'year_from' => ['required', 'integer', 'between:1900,2100'],
            'year_to' => ['nullable', 'integer', 'between:1900,2100', 'gte:year_from'],
            'engine_code' => ['nullable', 'string', 'max:64'],
            'engine_capacity_cc' => ['nullable', 'integer', 'between:1,20000'],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'transmission' => ['nullable', Rule::enum(Transmission::class)],
            'is_active' => ['boolean'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedModelFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $variant = VehicleVariant::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $variant->id;
        $this->vehicle_model_id = (string) $variant->vehicle_model_id;
        $this->name = $variant->name;
        $this->name_ar = $variant->name_ar ?? '';
        $this->slug = $variant->slug;
        $this->year_from = (string) $variant->year_from;
        $this->year_to = $variant->year_to === null ? '' : (string) $variant->year_to;
        $this->engine_code = $variant->engine_code ?? '';
        $this->engine_capacity_cc = $variant->engine_capacity_cc === null ? '' : (string) $variant->engine_capacity_cc;
        $this->fuel_type = $variant->fuel_type?->value ?? '';
        $this->transmission = $variant->transmission?->value ?? '';
        $this->is_active = $variant->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'vehicle_model_id' => (int) $this->vehicle_model_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar !== '' ? $this->name_ar : null,
            'slug' => $this->slug,
            'year_from' => (int) $this->year_from,
            'year_to' => $this->year_to !== '' ? (int) $this->year_to : null,
            'engine_code' => $this->engine_code !== '' ? $this->engine_code : null,
            'engine_capacity_cc' => $this->engine_capacity_cc !== '' ? (int) $this->engine_capacity_cc : null,
            'fuel_type' => $this->fuel_type !== '' ? $this->fuel_type : null,
            'transmission' => $this->transmission !== '' ? $this->transmission : null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId === null) {
            VehicleVariant::create($data);
        } else {
            VehicleVariant::query()->findOrFail($this->editingId)->update($data);
        }

        $this->cancel();
    }

    public function delete(int $id): void
    {
        VehicleVariant::query()->findOrFail($id)->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'vehicle_model_id', 'name', 'name_ar', 'slug', 'year_from',
            'year_to', 'engine_code', 'engine_capacity_cc', 'fuel_type', 'transmission',
        ]);
        $this->is_active = true;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, VehicleVariant>
     */
    #[Computed]
    public function variants(): LengthAwarePaginator
    {
        return VehicleVariant::query()
            ->with('vehicleModel')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('name_ar', 'like', $term)
                        ->orWhere('engine_code', 'like', $term);
                });
            })
            ->when($this->modelFilter !== '', fn ($query) => $query->where('vehicle_model_id', (int) $this->modelFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('vehicle_model_id')
            ->orderBy('year_from')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return Collection<int, VehicleModel>
     */
    #[Computed]
    public function models(): Collection
    {
        return VehicleModel::query()->orderBy('name')->get();
    }

    /**
     * @return array<int, FuelType>
     */
    #[Computed]
    public function fuelTypes(): array
    {
        return FuelType::cases();
    }

    /**
     * @return array<int, Transmission>
     */
    #[Computed]
    public function transmissions(): array
    {
        return Transmission::cases();
    }
};
?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-medium">Vehicle variants</h1>
            <p class="text-sm text-zinc-500">Shared catalog, visible to all shops</p>
        </div>
        <button type="button" wire:click="create"
            class="rounded-lg border border-zinc-300 px-3 py-2 text-sm hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
            Add variant
        </button>
    </div>

    <div class="flex flex-wrap gap-2">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or engine code"
            class="min-w-48 flex-1 rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
        <select wire:model.live="modelFilter"
            class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
            <option value="">All models</option>
            @foreach ($this->models as $model)
                <option value="{{ $model->id }}">{{ $model->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter"
            class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
            <option value="">All status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <h2 class="font-medium">{{ $editingId ? 'Edit variant' : 'Add variant' }}</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-sm text-zinc-500">Model</label>
                    <select wire:model="vehicle_model_id"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                        <option value="">Select a model</option>
                        @foreach ($this->models as $model)
                            <option value="{{ $model->id }}">{{ $model->name }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_model_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Name</label>
                    <input type="text" wire:model="name" placeholder="1.6 Comfort"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Arabic name</label>
                    <input type="text" wire:model="name_ar" dir="rtl"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                    @error('name_ar') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Slug</label>
                    <input type="text" wire:model="slug" placeholder="1-6-comfort-2019"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                    @error('slug') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Year from</label>
                    <input type="text" wire:model="year_from" inputmode="numeric"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                    @error('year_from') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Year to</label>
                    <input type="text" wire:model="year_to" inputmode="numeric"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                    @error('year_to') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Engine code</label>
                    <input type="text" wire:model="engine_code"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                    @error('engine_code') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Engine size (cc)</label>
                    <input type="text" wire:model="engine_capacity_cc" inputmode="numeric"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                    @error('engine_capacity_cc') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Fuel</label>
                    <select wire:model="fuel_type"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                        <option value="">Not set</option>
                        @foreach ($this->fuelTypes as $fuel)
                            <option value="{{ $fuel->value }}">{{ ucfirst($fuel->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-zinc-500">Gearbox</label>
                    <select wire:model="transmission"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                        <option value="">Not set</option>
                        @foreach ($this->transmissions as $gearbox)
                            <option value="{{ $gearbox->value }}">{{ strtoupper($gearbox->value) === 'CVT' || strtoupper($gearbox->value) === 'DCT' ? strtoupper($gearbox->value) : ucfirst($gearbox->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="is_active"> Active
            </label>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cancel"
                    class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700">Cancel</button>
                <button type="submit"
                    class="rounded-lg border border-zinc-300 px-3 py-2 text-sm hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">Save variant</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="text-zinc-500">
                <tr>
                    <th class="px-3 py-2 font-normal">Model</th>
                    <th class="px-3 py-2 font-normal">Variant</th>
                    <th class="px-3 py-2 font-normal">Years</th>
                    <th class="px-3 py-2 font-normal">Engine</th>
                    <th class="px-3 py-2 font-normal">Gearbox</th>
                    <th class="px-3 py-2 font-normal">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->variants as $variant)
                    <tr wire:key="variant-{{ $variant->id }}" class="border-t border-zinc-200 dark:border-zinc-700">
                        <td class="px-3 py-2">{{ $variant->vehicleModel->name }}</td>
                        <td class="px-3 py-2">{{ $variant->name }}@if ($variant->name_ar) <span class="text-zinc-400">· {{ $variant->name_ar }}</span>@endif</td>
                        <td class="px-3 py-2">{{ $variant->year_from }}–{{ $variant->year_to ?? 'now' }}</td>
                        <td class="px-3 py-2">{{ collect([$variant->engine_code, $variant->engine_capacity_cc ? $variant->engine_capacity_cc.' cc' : null])->filter()->implode(', ') ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $variant->transmission?->value ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $variant->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" wire:click="edit({{ $variant->id }})" class="text-zinc-600 hover:underline dark:text-zinc-300">Edit</button>
                            <button type="button" wire:click="delete({{ $variant->id }})"
                                wire:confirm="Delete this variant?" class="ml-3 text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-zinc-500">
                            Add your first variant to start linking parts to vehicles.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->variants->links() }}
</div>