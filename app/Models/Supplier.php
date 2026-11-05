<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use BelongsToShop, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'alternate_phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
