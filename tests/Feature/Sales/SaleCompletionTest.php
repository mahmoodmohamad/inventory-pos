<?php

declare(strict_types=1);

use App\Enums\LedgerDirection;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Sales\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sc_shop(bool $allowNegative = true): array
{
    $shop = Shop::factory()->create(['settings' => ['allow_negative_stock' => $allowNegative]]);
    $user = User::factory()->create(['shop_id' => $shop->id, 'role' => UserRole::Cashier]);
    app(TenantContext::class)->set($shop);
    test()->actingAs($user);

    return [$shop, $user];
}

function sc_product(Shop $shop, string $stock = '10', string $cost = '50'): Product
{
    $p = Product::factory()->create(['shop_id' => $shop->id, 'retail_price' => '100.00', 'wholesale_price' => '80.00']);
    if ($stock !== '0') {
        app(StockService::class)->openingBalance($p, $stock, $cost);
    }

    return $p->refresh();
}

function sc_draft(User $user, array $lines, ?int $customerId = null): Sale
{
    $svc = app(SaleService::class);
    $sale = $svc->createDraft($user, $customerId, PriceType::Retail);
    foreach ($lines as [$product, $qty]) {
        $svc->addItem($user, $sale, $product->id, $qty);
    }

    return $sale->refresh();
}

function sc_customer(Shop $shop): Customer
{
    return Customer::factory()->create(['shop_id' => $shop->id]);
}

it('completes a sale, deducts stock and freezes cost at completion time', function () {
    [$shop, $user] = sc_shop();
    $product = sc_product($shop, '10', '50');
    $sale = sc_draft($user, [[$product, '2']]);

    // شراء بين الإضافة والإكمال: المتوسط يبقى 60
    app(StockService::class)->purchase($product, '10', '70');

    app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '200');

    $sale->refresh();
    expect($sale->status)->toBe(SaleStatus::Completed)
        ->and($sale->completed_by)->toBe($user->id);
    expect((string) $sale->items()->first()->unit_cost)->toBe('60.0000');
    expect((string) $product->refresh()->stock_qty)->toBe('18.000');

    $movement = StockMovement::query()
        ->where('type', StockMovementType::Sale->value)
        ->where('reference_id', $sale->id)
        ->where('reference_type', $sale->getMorphClass())
        ->first();
    expect($movement)->not->toBeNull();
});

it('records only the invoice total for cash overpayment', function () {
    [$shop, $user] = sc_shop();
    $sale = sc_draft($user, [[sc_product($shop), '2']]);

    app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '250');

    expect(Payment::query()->count())->toBe(1)
        ->and((string) Payment::query()->first()->amount)->toBe('200.00')
        ->and(CustomerLedger::query()->count())->toBe(0);
});

it('rejects overpayment by non-cash methods', function () {
    [$shop, $user] = sc_shop();
    $product = sc_product($shop);
    $sale = sc_draft($user, [[$product, '2']]);

    expect(fn () => app(SaleService::class)->complete($user, $sale, PaymentMethod::Card, '250'))
        ->toThrow(DomainException::class);

    expect($sale->refresh()->status)->toBe(SaleStatus::Draft)
        ->and((string) $product->refresh()->stock_qty)->toBe('10.000');
});

it('posts invoice debit and payment credit for a partial payment', function () {
    [$shop, $user] = sc_shop();
    $customer = sc_customer($shop);
    $sale = sc_draft($user, [[sc_product($shop), '2']], $customer->id);

    app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '50');

    $entries = CustomerLedger::query()->where('customer_id', $customer->id)->get();
    expect($entries)->toHaveCount(2);

    $debit = $entries->where('direction', LedgerDirection::Debit)->sum(fn ($e) => (float) $e->amount);
    $credit = $entries->where('direction', LedgerDirection::Credit)->sum(fn ($e) => (float) $e->amount);
    expect($debit)->toBe(200.0)->and($credit)->toBe(50.0);
    expect(Payment::query()->count())->toBe(1);
});

it('records a full credit sale with a debit entry and no payment', function () {
    [$shop, $user] = sc_shop();
    $customer = sc_customer($shop);
    $sale = sc_draft($user, [[sc_product($shop), '1']], $customer->id);

    app(SaleService::class)->complete($user, $sale, null, '0');

    expect(Payment::query()->count())->toBe(0)
        ->and(CustomerLedger::query()->count())->toBe(1)
        ->and(CustomerLedger::query()->first()->direction)->toBe(LedgerDirection::Debit);
});

it('records a fully paid anonymous sale without ledger entries', function () {
    [$shop, $user] = sc_shop();
    $sale = sc_draft($user, [[sc_product($shop), '1']]);

    app(SaleService::class)->complete($user, $sale, PaymentMethod::Card, '100');

    expect(Payment::query()->count())->toBe(1)->and(CustomerLedger::query()->count())->toBe(0);
});

it('rejects a remaining balance without a customer', function () {
    [$shop, $user] = sc_shop();
    $sale = sc_draft($user, [[sc_product($shop), '1']]);

    expect(fn () => app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '10'))
        ->toThrow(DomainException::class);
    expect($sale->refresh()->status)->toBe(SaleStatus::Draft);
});

it('rejects a remaining balance for an inactive customer', function () {
    [$shop, $user] = sc_shop();
    $customer = sc_customer($shop);
    $sale = sc_draft($user, [[sc_product($shop), '1']], $customer->id);
    $customer->update(['is_active' => false]);

    expect(fn () => app(SaleService::class)->complete($user, $sale, null, '0'))
        ->toThrow(DomainException::class);
});

it('rejects empty sales and double completion', function () {
    [$shop, $user] = sc_shop();
    $empty = sc_draft($user, []);
    expect(fn () => app(SaleService::class)->complete($user, $empty, null, '0'))
        ->toThrow(DomainException::class);

    $sale = sc_draft($user, [[sc_product($shop), '1']]);
    app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '100');

    expect(fn () => app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '100'))
        ->toThrow(DomainException::class);
    expect(Payment::query()->count())->toBe(1);
});

it('rejects inactive products at completion', function () {
    [$shop, $user] = sc_shop();
    $product = sc_product($shop);
    $sale = sc_draft($user, [[$product, '1']]);
    $product->update(['is_active' => false]);

    expect(fn () => app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '100'))
        ->toThrow(DomainException::class);
});

it('respects allow_negative_stock', function () {
    [$shop, $user] = sc_shop(allowNegative: false);
    $product = sc_product($shop, '1');
    $sale = sc_draft($user, [[$product, '2']]);

    expect(fn () => app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '200'))
        ->toThrow(DomainException::class);
    expect((string) $product->refresh()->stock_qty)->toBe('1.000');

    $shop->update(['settings' => ['allow_negative_stock' => true]]);
    app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '200');
    expect((string) $product->refresh()->stock_qty)->toBe('-1.000');
});

it('rolls everything back when a later item fails', function () {
    [$shop, $user] = sc_shop(allowNegative: false);
    $customer = sc_customer($shop);
    $a = sc_product($shop, '10');
    $b = sc_product($shop, '1');
    $sale = sc_draft($user, [[$a, '2'], [$b, '5']], $customer->id);

    expect(fn () => app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '100'))
        ->toThrow(DomainException::class);

    expect((string) $a->refresh()->stock_qty)->toBe('10.000')
        ->and((string) $b->refresh()->stock_qty)->toBe('1.000')
        ->and(Payment::query()->count())->toBe(0)
        ->and(CustomerLedger::query()->count())->toBe(0)
        ->and($sale->refresh()->status)->toBe(SaleStatus::Draft)
        ->and(StockMovement::query()->where('type', StockMovementType::Sale->value)->count())->toBe(0);
});

it('blocks other shops and inactive users', function () {
    [$shopA, $userA] = sc_shop();
    $sale = sc_draft($userA, [[sc_product($shopA), '1']]);

    $shopB = Shop::factory()->create();
    $userB = User::factory()->create(['shop_id' => $shopB->id, 'role' => UserRole::Owner]);
    app(TenantContext::class)->set($shopB);

    expect(fn () => app(SaleService::class)->complete($userB, $sale, PaymentMethod::Cash, '100'))
        ->toThrow(AuthorizationException::class);

    app(TenantContext::class)->set($shopA);
    $userA->update(['is_active' => false]);
    expect(fn () => app(SaleService::class)->complete($userA->refresh(), $sale, PaymentMethod::Cash, '100'))
        ->toThrow(AuthorizationException::class);
});

it('locks the sale and items after completion', function () {
    [$shop, $user] = sc_shop();
    $product = sc_product($shop);
    $sale = sc_draft($user, [[$product, '1']]);
    app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '100');

    expect(fn () => app(SaleService::class)->addItem($user, $sale->refresh(), $product->id, '1'))
        ->toThrow(DomainException::class);
    expect(fn () => $sale->items()->first()->delete())->toThrow(LogicException::class);
    expect(fn () => $sale->refresh()->update(['notes' => 'x']))->toThrow(LogicException::class);
});
it('records the actor on sale movements', function () {
    [$shop, $user] = sc_shop();
    $sale = sc_draft($user, [[sc_product($shop), '1']]);

    app(SaleService::class)->complete($user, $sale, PaymentMethod::Cash, '100');

    expect(
        StockMovement::query()->where('type', StockMovementType::Sale->value)->first()->user_id
    )->toBe($user->id);
});