<?php

namespace App\Http\Controllers;

use App\Http\Resources\VehicleMakeResource;
use App\Models\VehicleMake;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleMakeController extends Controller
{
    /**
     * الكتالوج المشترك: الشركات النشطة وموديلاتها النشطة، مرتبة بالاسم.
     */
    public function index(): AnonymousResourceCollection
    {
        $makes = VehicleMake::query()
            ->where('is_active', true)
            ->with(['models' => fn (HasMany $query) => $query
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        return VehicleMakeResource::collection($makes);
    }
}