
<?php

use App\Models\VehicleMake;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $makes = VehicleMake::query()
            ->where('is_active', true)
            ->where('name', 'like', '%' . $this->search . '%')
            ->with(['models' => fn (HasMany $query) => $query
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('name')
            ->paginate(12);

        return view('pages.vehicle-makes', compact('makes'));
    }
};
?>

<style>
    .vehicle-makes-grid {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 1.5rem;
        width: 100%;
    }

    .vehicle-make-card {
        min-width: 0;
        width: 100%;
        height: 100%;
    }

    @media (max-width: 1023px) {
        .vehicle-makes-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    }

    @media (max-width: 639px) {
        .vehicle-makes-grid {
            grid-template-columns: minmax(0, 1fr) !important;
        }
    }
</style>

<div class="w-full space-y-6 p-4 sm:p-6">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                Vehicle Makes
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Browse vehicle manufacturers and their available models.
            </p>
        </div>

        <span class="inline-flex w-fit items-center rounded-lg bg-green-50 px-3 py-2 text-sm font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
            {{ $makes->total() }} Active Makes
        </span>
    </div>

    {{-- Search --}}
    <div class="max-w-md">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search vehicle makes..."
            class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
        />
    </div>

    {{-- Three-column grid --}}
    @if ($makes->isNotEmpty())

        <div class="vehicle-makes-grid">

            @foreach ($makes as $make)

                <article
                    wire:key="vehicle-make-{{ $make->id }}"
                    class="vehicle-make-card flex flex-col rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
                >

                    {{-- Make name --}}
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                            {{ $make->name }}
                        </h2>

                        <span class="shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-400">
                            Active
                        </span>
                    </div>

                    {{-- Model count --}}
                    <div class="mt-5 flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">
                            Available Models
                        </span>

                        <span class="flex h-7 min-w-7 items-center justify-center rounded-full bg-zinc-100 px-2 text-sm font-bold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                            {{ $make->models->count() }}
                        </span>
                    </div>

                    {{-- Models --}}
                    <div class="mt-4 flex flex-wrap gap-2">
                        @forelse ($make->models as $model)
                            <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs font-medium text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $model->name }}
                            </span>
                        @empty
                            <span class="text-sm text-zinc-400">
                                No active models available.
                            </span>
                        @endforelse
                    </div>

                </article>

            @endforeach

        </div>

        <div class="pt-4">
            {{ $makes->links() }}
        </div>

    @else

        <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                No vehicle makes found
            </h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                Try a different search term.
            </p>
        </div>

    @endif

</div>
