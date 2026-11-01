<?php

namespace App\Http\Resources;

use App\Models\VehicleMake;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleMake
 */
class VehicleMakeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'models' => VehicleModelResource::collection($this->whenLoaded('models')),
        ];
    }
}
