<?php

namespace App\Http\Controllers;

use App\Enums\ProductCondition;
use App\Http\Requests\StorefrontSearchRequest;
use App\Models\Shop;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use App\Services\Storefront\StorefrontCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(StorefrontSearchRequest $request, StorefrontCatalog $catalog): View
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('storefront_shop');
        $filters = $request->validated();

        $makes = $catalog->makes();
        $models = $catalog->modelsOf((int) ($filters['make'] ?? 0));
        $variants = $catalog->variantsOf((int) ($filters['model'] ?? 0));

        return view('storefront.index', [
            'shop' => $shop,
            'filters' => $filters,
            'products' => $catalog->search($filters),
            'makes' => $makes,
            'models' => $models,
            'variants' => $variants,
            'conditions' => ProductCondition::cases(),
            'carLabel' => $this->carLabel($filters, $makes, $models, $variants),
            'whatsapp' => $this->whatsappNumber($shop->phone),
        ]);
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
            $parts[] = $variant->year_from.'–'.($variant->year_to ?? 'الآن');
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /** رقم مصري محلي (01xxxxxxxxx) إلى صيغة wa.me الدولية، أو null لو الرقم غير صالح. */
    private function whatsappNumber(?string $phone): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '20'.substr($digits, 1);
        }

        return strlen($digits) >= 10 ? $digits : null;
    }
}
