<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Enums\ProductCondition;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProductForm extends Form
{
    public ?int $editingId = null;

    public ?int $duplicateFromId = null;

    public bool $copyFitments = true;

    public ?float $editingStock = null;

    public ?float $editingAvgCost = null;

    public string $name = '';

    public string $part_number = '';

    public string $oem_number = '';

    public string $barcode = '';

    public string $condition = 'original';

    public string $unit = 'piece';

    public string $category_id = '';

    public string $brand_id = '';

    public string $newBrand = '';

    public string $retail_price = '';

    public string $wholesale_price = '';

    public string $min_stock_alert = '0';

    public string $location = '';

    public string $notes = '';

    public bool $is_active = true;

    public string $opening_qty = '';

    public string $opening_cost = '';

    public string $new_stock_qty = '';

    public string $stock_note = '';

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        $shopId = app(TenantContext::class)->idOrFail();

        return [
            'name' => ['required', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'oem_number' => ['nullable', 'string', 'max:100'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode')->where('shop_id', $shopId)->ignore($this->editingId),
            ],
            'condition' => ['required', Rule::enum(ProductCondition::class)],
            'new_stock_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'stock_note' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('shop_id', $shopId)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('shop_id', $shopId)],
            'newBrand' => ['nullable', 'string', 'max:255'],
            'retail_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'wholesale_price' => [
                'required', 'numeric', 'min:0', 'max:9999999999',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_numeric($value)
                        && is_numeric($this->retail_price)
                        && bccomp((string) $value, (string) $this->retail_price, 2) === 1
                    ) {
                        $fail(__('سعر الجملة لا يمكن أن يزيد عن سعر القطاعي.'));
                    }
                },
            ],
            'min_stock_alert' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'opening_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'opening_cost' => [
                'nullable', 'numeric', 'min:0',
                Rule::requiredIf(fn () => $this->editingId === null && (float) $this->opening_qty > 0),
            ],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'barcode.unique' => __('الباركود مستخدم في منتج آخر.'),
            'wholesale_price.lte' => __('سعر الجملة لا يمكن أن يزيد عن سعر القطاعي.'),
            'opening_cost.required' => __('اكتب تكلفة الوحدة للرصيد الافتتاحي.'),
        ];
    }

    public function fillFrom(Product $product): void
    {
        $this->name = $product->name;
        $this->part_number = $product->part_number ?? '';
        $this->oem_number = $product->oem_number ?? '';
        $this->condition = $product->condition->value;
        $this->unit = $product->unit->value;
        $this->category_id = $product->category_id === null ? '' : (string) $product->category_id;
        $this->brand_id = $product->brand_id === null ? '' : (string) $product->brand_id;
        $this->retail_price = (string) $product->retail_price;
        $this->wholesale_price = (string) $product->wholesale_price;
        $this->min_stock_alert = (string) $product->min_stock_alert;
        $this->location = $product->location ?? '';
        $this->notes = $product->notes ?? '';
        $this->is_active = $product->is_active;
    }

    /**
     * الـ attributes اللي بتتكتب في جدول products (الـ brand بيتحدد في الـ Service).
     *
     * @return array<string, mixed>
     */
    public function payload(?int $brandId): array
    {
        return [
            'name' => trim($this->name),
            'part_number' => filled($this->part_number) ? trim($this->part_number) : null,
            'oem_number' => filled($this->oem_number) ? trim($this->oem_number) : null,
            'barcode' => filled($this->barcode) ? trim($this->barcode) : null,
            'condition' => $this->condition,
            'unit' => $this->unit,
            'category_id' => filled($this->category_id) ? (int) $this->category_id : null,
            'brand_id' => $brandId,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'min_stock_alert' => $this->min_stock_alert,
            'location' => filled($this->location) ? trim($this->location) : null,
            'notes' => filled($this->notes) ? trim($this->notes) : null,
            'is_active' => $this->is_active,
        ];
    }
}
