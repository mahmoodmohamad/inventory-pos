<?php

// app/Models/ProductFitment.php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\ProductFitmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFitment extends Model
{
    /** @use HasFactory<ProductFitmentFactory> */
    use BelongsToShop, HasFactory;

    protected $fillable = ['vehicle_variant_id', 'notes'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<VehicleVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class, 'vehicle_variant_id');
    }
}
