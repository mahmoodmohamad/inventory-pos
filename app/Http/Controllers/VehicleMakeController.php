
<?php

namespace App\Http\Controllers;

use App\Models\VehicleMake;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class VehicleMakeController extends Controller
{
    /**
     * Display active vehicle makes and their active models.
     */
    public function index(): View
    {
        $makes = VehicleMake::query()
            ->where('is_active', true)
            ->with(['models' => fn (HasMany $query) => $query
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('name')
            ->paginate(15);

        return view('vehicle-makes.index', compact('makes'));
    }
}
