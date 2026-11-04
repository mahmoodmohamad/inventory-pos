<?php

use App\Enums\PriceType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sales\SaleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->other = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->set($this->shop);

    $this->service = app(SaleService::class);
    $this->owner = User::factory()->for($this->shop)->create(['role' => UserRole::Owner]);
    $this->cashier = User::factory()->for($this->shop)->create(['role' => UserRole::Cashier]);

    $this->product = Product::factory()->for($this->shop)->withStock(10, 50)
        ->create(['retail_price' => '100.00', 'wholesale_price' => '85.00']);
});

function draft(PriceType $type = PriceType::Retail): Sale
{
    return test()->service->createDraft(test()->cashier, null, $type);
}

it('numbers invoices sequentially per shop and independently across shops', function () {
    $a1 = draft();
    $a2 = draft();

    $this->tenant->run($this->other, function () {
        $user = User::factory()->for($this->other)->create();

        expect($this->service->createDraft($user, null, PriceType::Retail)->invoice_number)->toBe(1);
    });

    expect($a1->invoice_number)->toBe(1)->and($a2->invoice_number)->toBe(2);
});

it('rejects a customer from another shop', function () {
    $foreign = $this->tenant->run($this->other, fn () => Customer::factory()->for($this->other)->create());

    $this->service->createDraft($this->owner, $foreign->id, PriceType::Retail);
})->throws(ModelNotFoundException::class);

it('forbids an inactive user from creating a draft', function () {
    $inactive = User::factory()->for($this->shop)->create(['is_active' => false]);

    $this->service->createDraft($inactive, null, PriceType::Retail);
})->throws(AuthorizationException::class);

it('snapshots the product, the price tier and the cost', function () {
    $sale = draft();
    $item = $this->service->addItem($this->cashier, $sale, $this->product->id, '2');

    expect($item->product_name)->toBe($this->product->name)
        ->and($item->original_unit_price)->toBe('100.00')
        ->and($item->unit_price)->toBe('100.00')
        ->and($item->line_total)->toBe('200.00')
        ->and($item->unit_cost)->toBe('50.0000')
        ->and($item->price_overridden_by)->toBeNull();

    $sale->refresh();

    expect($sale->subtotal)->toBe('200.00')->and($sale->total_amount)->toBe('200.00');
});

it('uses the wholesale price on a wholesale sale', function () {
    $item = $this->service->addItem($this->cashier, draft(PriceType::Wholesale), $this->product->id);

    expect($item->original_unit_price)->toBe('85.00')->and($item->line_total)->toBe('85.00');
});

it('merges the same product at the same price into one line', function () {
    $sale = draft();
    $this->service->addItem($this->cashier, $sale, $this->product->id, '1');
    $this->service->addItem($this->cashier, $sale, $this->product->id, '2');

    expect($sale->items()->count())->toBe(1)
        ->and($sale->items()->first()->quantity)->toBe('3.000')
        ->and($sale->refresh()->total_amount)->toBe('300.00');
});

it('forbids a cashier from overriding the price and records the owner who does', function () {
    $sale = draft();

    expect(fn () => $this->service->addItem($this->cashier, $sale, $this->product->id, '1', '90.00'))
        ->toThrow(AuthorizationException::class)
        ->and($sale->items()->count())->toBe(0);

    $item = $this->service->addItem($this->owner, $sale, $this->product->id, '1', '90.00');

    expect($item->original_unit_price)->toBe('100.00')
        ->and($item->unit_price)->toBe('90.00')
        ->and($item->price_overridden_by)->toBe($this->owner->id);
});

it('lets only an override-permitted user change the price of an existing line', function () {
    $sale = draft();
    $item = $this->service->addItem($this->cashier, $sale, $this->product->id);

    expect(fn () => $this->service->setPrice($this->cashier, $sale, $item->id, '80.00'))
        ->toThrow(AuthorizationException::class);

    $this->service->setPrice($this->owner, $sale, $item->id, '80.00');
    expect($item->fresh()->price_overridden_by)->toBe($this->owner->id);

    // الرجوع للسعر الأصلي بيشيل المصرّح
    $this->service->setPrice($this->owner, $sale, $item->id, '100.00');
    expect($item->fresh()->price_overridden_by)->toBeNull();
});

it('cannot add a product of another shop or an inactive one', function () {
    $foreign = $this->tenant->run($this->other, fn () => Product::factory()->for($this->other)->create());
    $inactive = Product::factory()->for($this->shop)->create(['is_active' => false]);
    $sale = draft();

    expect(fn () => $this->service->addItem($this->cashier, $sale, $foreign->id))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => $this->service->addItem($this->cashier, $sale, $inactive->id))
        ->toThrow(ModelNotFoundException::class);
});

it('keeps the cost snapshot when the average cost changes later', function () {
    $item = $this->service->addItem($this->cashier, draft(), $this->product->id);

    $this->product->forceFill(['avg_cost' => '80'])->save();

    expect($item->fresh()->unit_cost)->toBe('50.0000');
});

it('rounds line totals half-up with exact decimals', function () {
    $p = Product::factory()->for($this->shop)->create(['retail_price' => '10.10']);

    $item = $this->service->addItem($this->cashier, draft(), $p->id, '0.055'); // 0.5555

    expect($item->line_total)->toBe('0.56');
});

it('applies line and invoice discounts and keeps total = subtotal - discount', function () {
    $sale = draft();
    $item = $this->service->addItem($this->cashier, $sale, $this->product->id, '2'); // 200

    $this->service->setItemDiscount($this->cashier, $sale, $item->id, '20.00');
    expect($item->fresh()->line_total)->toBe('180.00');

    $this->service->setDiscount($this->cashier, $sale, '30.00');
    $sale->refresh();

    expect($sale->subtotal)->toBe('180.00')
        ->and($sale->discount_amount)->toBe('30.00')
        ->and($sale->total_amount)->toBe('150.00');
});

it('rejects discounts bigger than the amount they apply to', function () {
    $sale = draft();
    $item = $this->service->addItem($this->cashier, $sale, $this->product->id, '2');

    expect(fn () => $this->service->setDiscount($this->cashier, $sale, '250.00'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->service->setItemDiscount($this->cashier, $sale, $item->id, '300.00'))
        ->toThrow(InvalidArgumentException::class);
});

it('removes a line, treats zero quantity as removal and clamps the invoice discount', function () {
    $sale = draft();
    $item = $this->service->addItem($this->cashier, $sale, $this->product->id, '1');
    $this->service->setDiscount($this->cashier, $sale, '50.00');

    $this->service->setQuantity($this->cashier, $sale, $item->id, '0');
    $sale->refresh();

    expect($sale->items()->count())->toBe(0)
        ->and($sale->subtotal)->toBe('0.00')
        ->and($sale->discount_amount)->toBe('0.00')
        ->and($sale->total_amount)->toBe('0.00');
});

it('rolls back and writes nothing on an invalid quantity', function () {
    $sale = draft();

    expect(fn () => $this->service->addItem($this->cashier, $sale, $this->product->id, 'abc'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->service->addItem($this->cashier, $sale, $this->product->id, '0'))
        ->toThrow(InvalidArgumentException::class)
        ->and($sale->items()->count())->toBe(0);
});

it('refuses to change a sale that is not a draft', function () {
    $sale = Sale::factory()->for($this->shop)->completed()->create();

    $this->service->addItem($this->cashier, $sale, $this->product->id);
})->throws(DomainException::class);

it('discards a draft with its items', function () {
    $sale = draft();
    $this->service->addItem($this->cashier, $sale, $this->product->id);

    $this->service->discardDraft($this->cashier, $sale);

    expect(Sale::find($sale->id))->toBeNull();
});
