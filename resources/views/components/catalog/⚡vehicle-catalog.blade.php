<div style="display: flex; flex-direction: column; gap: 20px;">

    {{-- Header --}}
    <div style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px;">
        <div>
            <h1 class="text-xl font-medium">Vehicle catalog</h1>
            <p class="text-sm text-zinc-500">Makes and models shared by all shops</p>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
            <input type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by make or model"
                style="width: 100%; max-width: 260px;"
                class="rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">

            <button type="button"
                wire:click="openMakeModal"
                class="rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                + Add make
            </button>
        </div>
    </div>

    {{-- Statistics --}}
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;"
            class="rounded-lg border border-zinc-200 text-sm dark:border-zinc-700">
            <span class="font-medium">{{ $catalog->count() }}</span>
            <span class="text-zinc-500">makes</span>
        </span>

        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;"
            class="rounded-lg border border-zinc-200 text-sm dark:border-zinc-700">
            <span class="font-medium">{{ $modelsTotal }}</span>
            <span class="text-zinc-500">models</span>
        </span>

        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;"
            class="rounded-lg border border-zinc-200 text-sm dark:border-zinc-700">
            <span class="font-medium">{{ array_sum($variantCounts) }}</span>
            <span class="text-zinc-500">variants</span>
        </span>
    </div>

    {{-- Filters and display controls --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;">
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
            <button type="button"
                wire:click="$set('statusFilter', 'all')"
                class="rounded-lg border px-3 py-1.5 text-sm {{ $statusFilter === 'all' ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300' }}">
                All
            </button>

            <button type="button"
                wire:click="$set('statusFilter', 'active')"
                class="rounded-lg border px-3 py-1.5 text-sm {{ $statusFilter === 'active' ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300' }}">
                Active
            </button>

            <button type="button"
                wire:click="$set('statusFilter', 'inactive')"
                class="rounded-lg border px-3 py-1.5 text-sm {{ $statusFilter === 'inactive' ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300' }}">
                Inactive
            </button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
            <button type="button" wire:click="expandAll"
                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                Expand all
            </button>

            <button type="button" wire:click="collapseAll"
                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                Collapse all
            </button>
        </div>
    </div>

    {{-- Catalog cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; align-items: start;">

        @forelse ($catalog as $row)
            <div wire:key="make-{{ $row['make']->id }}"
                style="padding: 16px;"
                class="rounded-xl border border-zinc-200 dark:border-zinc-700">

                {{-- Make header --}}
                <div style="display: flex; align-items: center; gap: 12px;">

                    <div style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 9999px; font-size: 13px; font-weight: 500; flex-shrink: 0;"
                        class="bg-zinc-100 dark:bg-zinc-800">
                        {{ mb_strtoupper(mb_substr($row['make']->name, 0, 2)) }}
                    </div>

                    <div style="flex: 1; min-width: 0;">
                        <p class="font-medium">{{ $row['make']->name }}</p>
                        <p class="text-sm text-zinc-500">
                            {{ $row['models']->count() }}
                            {{ $row['models']->count() === 1 ? 'model' : 'models' }}
                        </p>
                    </div>

                    @unless ($row['make']->is_active)
                        <span style="padding: 2px 8px;"
                            class="rounded-md bg-zinc-100 text-xs text-zinc-500 dark:bg-zinc-800">
                            Inactive
                        </span>
                    @endunless
                </div>

                {{-- Make actions --}}
                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px;">
                    <button type="button"
                        wire:click="openModelModal({{ $row['make']->id }})"
                        class="rounded-md border border-zinc-200 px-2.5 py-1 text-xs text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        + Add model
                    </button>

                    <button type="button"
                        wire:click="editMake({{ $row['make']->id }})"
                        class="rounded-md border border-zinc-200 px-2.5 py-1 text-xs text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Edit
                    </button>

                    <button type="button"
                        wire:click="toggleMakeStatus({{ $row['make']->id }})"
                        class="rounded-md border border-zinc-200 px-2.5 py-1 text-xs text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        {{ $row['make']->is_active ? 'Deactivate' : 'Activate' }}
                    </button>

                    <button type="button"
                        wire:click="askDelete('make', {{ $row['make']->id }})"
                        class="rounded-md border border-zinc-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50 dark:border-zinc-700 dark:hover:bg-red-950">
                        Delete
                    </button>
                </div>

                {{-- Models --}}
                @if (in_array($row['make']->id, $expanded ?? []))
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 16px; padding-top: 12px;"
                        class="border-t border-zinc-100 dark:border-zinc-800">

                        @forelse ($row['models'] as $model)
                            <div wire:key="model-{{ $model->id }}"
                                style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">

                                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-width: 0;">
                                    <span style="{{ $model->is_active ? '' : 'opacity: 0.5;' }}"
                                        class="text-sm">
                                        {{ $model->name }}
                                    </span>

                                    @if ($model->name_ar)
                                        <span class="text-xs text-zinc-400">{{ $model->name_ar }}</span>
                                    @endif

                                    @if (($variantCounts[$model->id] ?? 0) > 0)
                                        <span style="padding: 0 6px; font-size: 12px;"
                                            class="rounded bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ $variantCounts[$model->id] }}
                                        </span>
                                    @endif

                                    @unless ($model->is_active)
                                        <span class="text-xs text-zinc-400">Inactive</span>
                                    @endunless
                                </div>

                                <div style="display: flex; flex-wrap: wrap; gap: 4px; flex-shrink: 0;">
                                    <button type="button"
                                        wire:click="editModel({{ $model->id }})"
                                        class="rounded-md px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        Edit
                                    </button>

                                    <button type="button"
                                        wire:click="toggleModelStatus({{ $model->id }})"
                                        class="rounded-md px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        {{ $model->is_active ? 'Disable' : 'Enable' }}
                                    </button>

                                    <button type="button"
                                        wire:click="askDelete('model', {{ $model->id }})"
                                        class="rounded-md px-2 py-1 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-950">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">No models yet.</p>
                        @endforelse
                    </div>
                @else
                    <button type="button"
                        wire:click="$set('expanded', array_merge($expanded ?? [], [{{ $row['make']->id }}]))"
                        class="mt-3 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-white">
                        Show models
                    </button>
                @endif
            </div>

        @empty
            <p style="grid-column: 1 / -1; padding: 24px 0; text-align: center;"
                class="text-sm text-zinc-500">
                No makes match your search.
            </p>
        @endforelse
    </div>

    {{-- Make modal --}}
    @if ($showMakeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="$set('showMakeModal', false)">

            <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                wire:click.stop>

                <div class="mb-5 flex items-center justify-between">
                    <h2 class="font-medium">{{ $editingMakeId ? 'Edit make' : 'Add make' }}</h2>
                    <button type="button" wire:click="$set('showMakeModal', false)"
                        class="text-zinc-400 hover:text-zinc-900 dark:hover:text-white">✕</button>
                </div>

                <form wire:submit="saveMake" class="flex flex-col gap-4">
                    <div>
                        <label class="mb-1 block text-sm text-zinc-600 dark:text-zinc-300">Make name</label>
                        <input type="text" wire:model="makeName"
                            class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                        @error('makeName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="makeIsActive"
                            class="rounded border-zinc-300">
                        Active
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showMakeModal', false)"
                            class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">
                            Save make
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Model modal --}}
    @if ($showModelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="$set('showModelModal', false)">

            <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                wire:click.stop>

                <div class="mb-5 flex items-center justify-between">
                    <h2 class="font-medium">{{ $editingModelId ? 'Edit model' : 'Add model' }}</h2>
                    <button type="button" wire:click="$set('showModelModal', false)"
                        class="text-zinc-400 hover:text-zinc-900 dark:hover:text-white">✕</button>
                </div>

                <form wire:submit="saveModel" class="flex flex-col gap-4">
                    <div>
                        <label class="mb-1 block text-sm text-zinc-600 dark:text-zinc-300">Model name</label>
                        <input type="text" wire:model="modelName"
                            class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                        @error('modelName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-zinc-600 dark:text-zinc-300">Arabic name</label>
                        <input type="text" wire:model="modelNameAr"
                            dir="auto"
                            class="w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm dark:border-zinc-700">
                        @error('modelNameAr')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="modelIsActive"
                            class="rounded border-zinc-300">
                        Active
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModelModal', false)"
                            class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">
                            Save model
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Delete confirmation --}}
    @if ($confirmAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="cancelDelete">

            <div class="w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                wire:click.stop>

                <h2 class="font-medium">Confirm deletion</h2>
                <p class="mt-2 text-sm text-zinc-500">
                    {{ $confirmMessage ?? 'Are you sure you want to delete this item?' }}
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete"
                        class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                        Cancel
                    </button>

                    <button type="button" wire:click="confirmDelete"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Notifications --}}
    <div
        x-data="{ show: false, message: '' }"
        x-on:notify.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 3000)"
        x-show="show"
        x-transition
        x-cloak
        style="position: fixed; right: 20px; bottom: 20px; z-index: 100;"
        class="rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
    >
        <span x-text="message"></span>
    </div>

</div>
