<?php

namespace App\Http\Resources;

use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleModel
 */
class VehicleModelResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     name_ar: string|null,
     *     slug: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
        ];
    }
}
