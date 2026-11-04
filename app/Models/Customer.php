<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
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

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<CustomerLedger, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedger::class);
    }
}
