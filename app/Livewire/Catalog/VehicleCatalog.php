<?php

namespace App\Livewire\Catalog;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VehicleCatalog extends Component
{
    public function mount(): void
    {
        abort_unless(Gate::allows('manage-catalog'), 403);
    }

    public function render(): View
    {
        return view('livewire.catalog.vehicle-catalog');
    }
}
