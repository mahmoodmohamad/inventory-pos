<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Purchases\PurchaseService;
use App\Services\StockService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pu_setup(UserRole $role = UserRole::Owner): array
{
    $shop = Shop::factory()->create();
    $user = User::factory()->create(['shop_id' => $shop->id, 'role' => $role]);
    app(TenantContext::class)->set($shop);
    $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
    $product = Product::factory()->create(['shop_id' => $shop->id]);

    return [$shop, $user, $supplier, $product];
}

function pu_line(Product $p, string $qty, string $cost): array
{
    return ['product_id' => $p->id, 'quantity' => $qty, 'unit_cost' => $cost];
}

it('receives a purchase, updates stock, WAC and last cost', function () {
    [, $user, $supplier, $product] = pu_setup();
    app(StockService::class)->openingBalance($product, '10', '50');

    $purchase = app(PurchaseService::class)->receive($user, $supplier->id, [pu_line($product, '10', '70')]);

    $product->refresh();
    expect((string) $product->stock_qty)->toBe('20.000')
        ->and((string) $product->avg_cost)->toBe('60.0000')
        ->and((string) $product->last_cost)->toBe('70.0000')
        ->and((string) $purchase->total_amount)->toBe('700.00')
        ->and($purchase->invoice_number)->toBe(1)
        ->and($purchase->items()->count())->toBe(1);

    $movement = StockMovement::query()->where('type', StockMovementType::Purchase->value)
        ->where('reference_id', $purchase->id)->first();
    expect($movement)->not->toBeNull();
});

it('numbers purchases sequentially per shop', function () {
    [, $user, $supplier, $product] = pu_setup();
    $svc = app(PurchaseService::class);

    $a = $svc->receive($user, $supplier->id, [pu_line($product, '1', '10')]);
    $b = $svc->receive($user, $supplier->id, [pu_line($product, '1', '10')]);

    expect([$a->invoice_number, $b->invoice_number])->toBe([1, 2]);
});

it('tracks supplier balance with partial payment at receipt and later payment', function () {
    [, $user, $supplier, $product] = pu_setup();
    $svc = app(PurchaseService::class);

    $svc->receive($user, $supplier->id, [pu_line($product, '10', '100')], PaymentMethod::Cash, '300');
    expect($svc->balance($supplier->id))->toBe('700.00');

    $svc->pay($user, $supplier->id, '200', PaymentMethod::BankTransfer);
    expect($svc->balance($supplier->id))->toBe('500.00')
        ->and(SupplierPayment::query()->count())->toBe(2);
});

it('rejects overpayment at receipt and on later payment', function () {
    [, $user, $supplier, $product] = pu_setup();
    $svc = app(PurchaseService::class);

    expect(fn () => $svc->receive($user, $supplier->id, [pu_line($product, '1', '100')], PaymentMethod::Cash, '150'))
        ->toThrow(DomainException::class);
    expect(Purchase::query()->count())->toBe(0);

    $svc->receive($user, $supplier->id, [pu_line($product, '1', '100')]);
    expect(fn () => $svc->pay($user, $supplier->id, '101', PaymentMethod::Cash))
        ->toThrow(DomainException::class);
});

it('requires a payment method when paid is above zero', function () {
    [, $user, $supplier, $product] = pu_setup();

    expect(fn () => app(PurchaseService::class)->receive($user, $supplier->id, [pu_line($product, '1', '100')], null, '10'))
        ->toThrow(DomainException::class);
});

it('rejects empty, zero-quantity and malformed lines', function () {
    [, $user, $supplier, $product] = pu_setup();
    $svc = app(PurchaseService::class);

    expect(fn () => $svc->receive($user, $supplier->id, []))->toThrow(DomainException::class);
    expect(fn () => $svc->receive($user, $supplier->id, [pu_line($product, '0', '10')]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->receive($user, $supplier->id, [pu_line($product, '1', '1e3')]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->receive($user, $supplier->id, [pu_line($product, '1', '10.12345')]))->toThrow(InvalidArgumentException::class);
});

it('rejects inactive and foreign suppliers', function () {
    [, $user, $supplier, $product] = pu_setup();
    $svc = app(PurchaseService::class);

    $supplier->update(['is_active' => false]);
    expect(fn () => $svc->receive($user, $supplier->id, [pu_line($product, '1', '10')]))
        ->toThrow(ModelNotFoundException::class);

    $other = Shop::factory()->create();
    $foreign = app(TenantContext::class)->run($other, fn () => Supplier::factory()->create(['shop_id' => $other->id]));
    expect(fn () => $svc->receive($user, $foreign->id, [pu_line($product, '1', '10')]))
        ->toThrow(ModelNotFoundException::class);
});

it('rolls back everything when a later line fails', function () {
    [, $user, $supplier, $product] = pu_setup();

    expect(fn () => app(PurchaseService::class)->receive($user, $supplier->id, [
        pu_line($product, '5', '10'),
        ['product_id' => 999999, 'quantity' => '1', 'unit_cost' => '10'],
    ], PaymentMethod::Cash, '10'))->toThrow(ModelNotFoundException::class);

    expect((string) $product->refresh()->stock_qty)->toBe('0.000')
        ->and(Purchase::query()->count())->toBe(0)
        ->and(SupplierPayment::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0);
});

it('denies cashiers', function () {
    [, $cashier, $supplier, $product] = pu_setup(UserRole::Cashier);

    expect(fn () => app(PurchaseService::class)->receive($cashier, $supplier->id, [pu_line($product, '1', '10')]))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(PurchaseService::class)->pay($cashier, $supplier->id, '10', PaymentMethod::Cash))
        ->toThrow(AuthorizationException::class);
});

it('keeps purchases, items and payments immutable', function () {
    [, $user, $supplier, $product] = pu_setup();
    $purchase = app(PurchaseService::class)->receive($user, $supplier->id, [pu_line($product, '1', '10')], PaymentMethod::Cash, '10');

    expect(fn () => $purchase->update(['notes' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $purchase->delete())->toThrow(LogicException::class);
    expect(fn () => $purchase->items()->first()->delete())->toThrow(LogicException::class);
    expect(fn () => SupplierPayment::query()->first()->delete())->toThrow(LogicException::class);
});
