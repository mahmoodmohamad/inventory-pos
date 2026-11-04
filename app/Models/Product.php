<?php

// app/Models/Product.php

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\ProductUnit;
use App\Models\Concerns\BelongsToShop;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $shop_id
 * @property ProductCondition $condition
 * @property ProductUnit $unit
 * @property numeric-string $retail_price
 * @property numeric-string $wholesale_price
 * @property numeric-string $stock_qty
 * @property numeric-string $avg_cost
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToShop, HasFactory, SoftDeletes;

    /**
     * stock_qty / avg_cost / last_cost مش هنا عمدًا:
     * بيتحدّثوا من الـ StockService بس (الخطوة 3.2).
     */
    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'part_number',
        'oem_number',
        'barcode',
        'condition',
        'unit',
        'retail_price',
        'wholesale_price',
        'min_stock_alert',
        'location',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'condition' => ProductCondition::class,
            'unit' => ProductUnit::class,
            'avg_cost' => 'decimal:4',
            'last_cost' => 'decimal:4',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'stock_qty' => 'decimal:3',
            'min_stock_alert' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * اكتب التوافق من هنا (->fitments()->create(...)) عشان hook الـ shop_id يشتغل.
     * attach() بيعدّي على الـ model events فمنستخدمهوش.
     *
     * @return HasMany<ProductFitment, $this>
     */
    public function fitments(): HasMany
    {
        return $this->hasMany(ProductFitment::class);
    }

    /**
     * بحث سريع: باركود (مطابقة تامة) + part/OEM (بداية الرقم، بيستفيد من الـ index) + اسم (يحتوي).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = addcslashes($term, '%_\\');

        $query->where(function (Builder $q) use ($term, $like): void {
            $q->where('barcode', $term)
                ->orWhere('part_number', 'like', $like.'%')
                ->orWhere('oem_number', 'like', $like.'%')
                ->orWhere('name', 'like', '%'.$like.'%');
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->where('min_stock_alert', '>', 0)
            ->whereColumn('stock_qty', '<=', 'min_stock_alert');
    }

    /** @return Attribute<float|null, never> */
    protected function marginPercent(): Attribute
    {
        return Attribute::get(function (): ?float {
            $retail = (float) $this->retail_price;
            $avg = (float) $this->avg_cost;

            return ($retail > 0 && $avg > 0)
                ? (($retail - $avg) / $retail) * 100
                : null;
        });
    }

    public function stockStatus(): string
    {
        $stock = (float) $this->stock_qty;
        $alert = (float) $this->min_stock_alert;

        return match (true) {
            $stock <= 0 => 'out',
            $alert > 0 && $stock <= $alert => 'low',
            default => 'ok',
        };
    }
}
