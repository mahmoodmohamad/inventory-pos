<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\Transmission;
use Database\Factories\VehicleVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleVariant extends Model
{
    /** @use HasFactory<VehicleVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'vehicle_model_id',
        'name',
        'name_ar',
        'slug',
        'year_from',
        'year_to',
        'engine_code',
        'engine_capacity_cc',
        'fuel_type',
        'transmission',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
            'engine_capacity_cc' => 'integer',
            'fuel_type' => FuelType::class,
            'transmission' => Transmission::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<VehicleModel, $this>
     */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }
}
