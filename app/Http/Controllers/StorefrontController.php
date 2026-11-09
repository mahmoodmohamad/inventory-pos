<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductCondition;
use App\Http\Requests\StorefrontSearchRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use App\Services\Storefront\StorefrontCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(StorefrontSearchRequest $request, StorefrontCatalog $catalog): View
    {
        $shop = $this->shop($request);

        // make → model → variant: كل مستوى بيتقبل بس لو تابع للمستوى اللي فوقه.
        $makes = $catalog->makes();
        $makeId = $this->selectedId($makes, $request->validated('make'));

        $models = $catalog->modelsOf($makeId);
        $modelId = $this->selectedId($models, $request->validated('model'));

        $variants = $catalog->variantsOf($modelId);
        $variantId = $this->selectedId($variants, $request->validated('variant'));

        $filters = [
            ...$request->validated(),
            'make' => $makeId ?: null,
            'model' => $modelId ?: null,
            'variant' => $variantId ?: null,
        ];

        return view('storefront.index', [
            'shop' => $shop,
            'filters' => $filters,
            'products' => $catalog->search($filters),
            'makes' => $makes,
            'models' => $models,
            'variants' => $variants,
            'conditions' => ProductCondition::cases(),
            'carLabel' => $this->carLabel($filters, $makes, $models, $variants),
            'whatsapp' => $shop->whatsappNumber(),
        ]);
    }

    public function show(Request $request, string $shopSlug, string $productId): View
    {
        $shop = $this->shop($request);

        $product = Product::query()
            ->where('is_active', true)
            ->with(['brand', 'category', 'fitments.variant.vehicleModel.make'])
            ->findOrFail($productId);

        return view('storefront.show', [
            'shop' => $shop,
            'product' => $product,
            'fitments' => $product->fitments,
            'inStock' => (float) $product->stock_qty > 0,
            'whatsapp' => $shop->whatsappNumber(),
        ]);
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('storefront_shop');
    }

    /**
     * يرجّع الـ id المطلوب لو موجود في الخيارات المسموحة، وإلا 0.
     *
     * @param  Collection<int, VehicleMake|VehicleModel|VehicleVariant>  $options
     */
    private function selectedId(Collection $options, mixed $requested): int
    {
        $id = (int) $requested;

        return $id > 0 && $options->contains('id', $id) ? $id : 0;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, VehicleMake>  $makes
     * @param  Collection<int, VehicleModel>  $models
     * @param  Collection<int, VehicleVariant>  $variants
     */
    private function carLabel(array $filters, Collection $makes, Collection $models, Collection $variants): ?string
    {
        $parts = [];

        if ($make = $makes->firstWhere('id', (int) ($filters['make'] ?? 0))) {
            $parts[] = $make->name;
        }

        if ($model = $models->firstWhere('id', (int) ($filters['model'] ?? 0))) {
            $parts[] = $model->name;
        }

        if ($variant = $variants->firstWhere('id', (int) ($filters['variant'] ?? 0))) {
            $parts[] = $variant->year_from.'–'.($variant->year_to ?? __('lang.present'));
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
