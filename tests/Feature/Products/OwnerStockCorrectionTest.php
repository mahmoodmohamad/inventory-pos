<?php

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Livewire\Forms\ProductForm;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductSaveService;
use App\Support\Tenancy\TenantContext;
use Livewire\Component;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    app(TenantContext::class)->set($this->shop);
    $this->product = Product::factory()->for($this->shop)->withStock(10)->create();
});

function formFor(Product $p, string $newQty): ProductForm
{
    $form = new ProductForm(new class extends Component {}, 'form');
    $form->fillFrom($p);
    $form->new_stock_qty = $newQty;

    return $form;
}

it('lets the owner correct the quantity through a ledger adjustment', function () {
    $owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);

    app(ProductSaveService::class)->save($owner, formFor($this->product, '7'), $this->product, null);

    expect((float) $this->product->fresh()->stock_qty)->toBe(7.0)
        ->and(StockMovement::where('type', StockMovementType::Adjustment)->first()->quantity)->toEqual('-3.000');
});

it('ignores the quantity when a manager sends it', function () {
    $manager = User::factory()->for($this->shop)->create(['role' => UserRole::Manager]);

    app(ProductSaveService::class)->save($manager, formFor($this->product, '99'), $this->product, null);

    expect((float) $this->product->fresh()->stock_qty)->toBe(10.0)
        ->and(StockMovement::count())->toBe(0);
});
