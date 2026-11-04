<?php

use App\Enums\LedgerDirection;
use App\Enums\LedgerSource;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\MissingTenantException;
use App\Exceptions\TenantViolationException;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\ShopSequence;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

const FK_FAIL = 'foreign key constraint fails';

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);

    // بيانات محل B (بتتعمل جوه سياقه)
    $this->customerB = $this->tenant->run($this->shopB, fn () => Customer::factory()->for($this->shopB)->create());
    $this->saleB = $this->tenant->run($this->shopB, fn () => Sale::factory()->for($this->shopB)->create());
    $this->productB = $this->tenant->run($this->shopB, fn () => Product::factory()->for($this->shopB)->create());
    $this->userB = User::factory()->for($this->shopB)->create();
    $this->paymentB = $this->tenant->run($this->shopB, fn () => Payment::factory()->for($this->shopB)
        ->create(['sale_id' => $this->saleB->id]));

    // محل A هو السياق الحالي
    $this->tenant->set($this->shopA);
    $this->userA = User::factory()->for($this->shopA)->create();
    $this->customerA = Customer::factory()->for($this->shopA)->create();
});

// ---------- Tenant isolation (Eloquent) ----------

it('assigns the current shop and ignores a client supplied shop_id', function () {
    $customer = Customer::query()->create(['name' => 'X', 'shop_id' => $this->shopB->id]);

    expect($customer->shop_id)->toBe($this->shopA->id);
});

it('fails closed without tenant context', function () {
    $this->tenant->forget();

    Sale::query()->count();
})->throws(MissingTenantException::class);

it('hides sales and customers of another shop even with a known id', function () {
    expect(Sale::find($this->saleB->id))->toBeNull()
        ->and(Customer::find($this->customerB->id))->toBeNull()
        ->and(Sale::count())->toBe(0);
});

it('blocks writes to another shop sale even with scopes disabled', function () {
    $found = Sale::withoutGlobalScopes()->findOrFail($this->saleB->id);

    expect(fn () => $found->update(['notes' => 'hacked']))->toThrow(TenantViolationException::class);
});

// ---------- Composite foreign keys (database level) ----------

it('rejects a customer from another shop on a sale', function () {
    Sale::factory()->for($this->shopA)->create(['customer_id' => $this->customerB->id]);
})->throws(QueryException::class, FK_FAIL);

it('rejects a creator from another shop on a sale', function () {
    Sale::factory()->for($this->shopA)->create(['created_by' => $this->userB->id]);
})->throws(QueryException::class, FK_FAIL);

it('rejects a sale from another shop on a sale item', function () {
    SaleItem::factory()->for($this->shopA)->create(['sale_id' => $this->saleB->id]);
})->throws(QueryException::class, FK_FAIL);

it('rejects a product from another shop on a sale item', function () {
    SaleItem::factory()->for($this->shopA)->create(['product_id' => $this->productB->id]);
})->throws(QueryException::class, FK_FAIL);

it('rejects a price override user from another shop', function () {
    SaleItem::factory()->for($this->shopA)->create([
        'unit_price' => '90.00',
        'price_overridden_by' => $this->userB->id,
    ]);
})->throws(QueryException::class, FK_FAIL);

it('rejects a sale, a customer or a receiver from another shop on a payment', function () {
    expect(fn () => Payment::factory()->for($this->shopA)->create(['sale_id' => $this->saleB->id]))
        ->toThrow(QueryException::class, FK_FAIL);

    expect(fn () => Payment::factory()->for($this->shopA)->create(['sale_id' => null, 'customer_id' => $this->customerB->id]))
        ->toThrow(QueryException::class, FK_FAIL);

    expect(fn () => Payment::factory()->for($this->shopA)->create(['received_by' => $this->userB->id]))
        ->toThrow(QueryException::class, FK_FAIL);
});

it('rejects a customer, a sale or a payment from another shop on a ledger entry', function () {
    expect(fn () => CustomerLedger::factory()->for($this->shopA)->create(['customer_id' => $this->customerB->id]))
        ->toThrow(QueryException::class, FK_FAIL);

    expect(fn () => CustomerLedger::factory()->for($this->shopA)->create([
        'customer_id' => $this->customerA->id, 'sale_id' => $this->saleB->id,
    ]))->toThrow(QueryException::class, FK_FAIL);

    expect(fn () => CustomerLedger::factory()->for($this->shopA)->create([
        'customer_id' => $this->customerA->id,
        'payment_id' => $this->paymentB->id,
        'source' => LedgerSource::Payment,
    ]))->toThrow(QueryException::class, FK_FAIL);
});

it('restricts force deleting a customer that has sales', function () {
    Sale::factory()->for($this->shopA)->create(['customer_id' => $this->customerA->id]);

    $this->customerA->forceDelete();
})->throws(QueryException::class, FK_FAIL);

// ---------- Check constraints ----------

it('rejects an unknown sale status or price type', function () {
    $sale = Sale::factory()->for($this->shopA)->create();

    expect(fn () => DB::table('sales')->where('id', $sale->id)->update(['status' => 'bogus']))
        ->toThrow(QueryException::class, 'sales_status_valid');

    expect(fn () => DB::table('sales')->where('id', $sale->id)->update(['price_type' => 'bogus']))
        ->toThrow(QueryException::class, 'sales_price_type_valid');
});

it('rejects sale totals that do not match subtotal minus discount', function () {
    $sale = Sale::factory()->for($this->shopA)->create();

    expect(fn () => DB::table('sales')->where('id', $sale->id)->update(['total_amount' => '999.00']))
        ->toThrow(QueryException::class, 'sales_amounts_valid');

    expect(fn () => DB::table('sales')->where('id', $sale->id)->update(['discount_amount' => '150.00', 'total_amount' => '-50.00']))
        ->toThrow(QueryException::class, 'sales_amounts_valid');
});

it('requires timestamps on completed and cancelled sales', function () {
    $sale = Sale::factory()->for($this->shopA)->create();

    expect(fn () => DB::table('sales')->where('id', $sale->id)->update(['status' => 'completed']))
        ->toThrow(QueryException::class, 'sales_completed_has_timestamp');

    expect(fn () => DB::table('sales')->where('id', $sale->id)->update(['status' => 'cancelled']))
        ->toThrow(QueryException::class, 'sales_cancelled_has_timestamp');
});

it('rejects a zero quantity and negative amounts on sale items', function () {
    $item = SaleItem::factory()->for($this->shopA)->create();

    expect(fn () => DB::table('sale_items')->where('id', $item->id)->update(['quantity' => 0]))
        ->toThrow(QueryException::class, 'sale_items_quantity_positive');

    expect(fn () => DB::table('sale_items')->where('id', $item->id)->update(['unit_cost' => -1]))
        ->toThrow(QueryException::class, 'sale_items_amounts_valid');
});

it('requires an authorising user when the applied price differs from the original', function () {
    $item = SaleItem::factory()->for($this->shopA)->create();

    expect(fn () => DB::table('sale_items')->where('id', $item->id)->update(['unit_price' => '90.00']))
        ->toThrow(QueryException::class, 'sale_items_override_requires_user');

    DB::table('sale_items')->where('id', $item->id)
        ->update(['unit_price' => '90.00', 'price_overridden_by' => $this->userA->id]);

    expect($item->fresh()->unit_price)->toBe('90.00');
});

it('rejects non positive payments, unknown methods and payments without a target', function () {
    $payment = Payment::factory()->for($this->shopA)->create();

    expect(fn () => DB::table('payments')->where('id', $payment->id)->update(['amount' => 0]))
        ->toThrow(QueryException::class, 'payments_amount_positive');

    expect(fn () => DB::table('payments')->where('id', $payment->id)->update(['method' => 'bitcoin']))
        ->toThrow(QueryException::class, 'payments_method_valid');

    expect(fn () => DB::table('payments')->where('id', $payment->id)->update(['sale_id' => null]))
        ->toThrow(QueryException::class, 'payments_has_target');
});

it('rejects ledger entries with invalid source, direction, amount or missing references', function () {
    $entry = CustomerLedger::factory()->for($this->shopA)->create(['customer_id' => $this->customerA->id]);

    expect(fn () => DB::table('customer_ledgers')->where('id', $entry->id)->update(['source' => 'bogus']))
        ->toThrow(QueryException::class, 'customer_ledgers_source_valid');

    expect(fn () => DB::table('customer_ledgers')->where('id', $entry->id)->update(['direction' => 'sideways']))
        ->toThrow(QueryException::class, 'customer_ledgers_direction_valid');

    expect(fn () => DB::table('customer_ledgers')->where('id', $entry->id)->update(['amount' => 0]))
        ->toThrow(QueryException::class, 'customer_ledgers_amount_positive');

    expect(fn () => DB::table('customer_ledgers')->where('id', $entry->id)->update(['source' => 'invoice']))
        ->toThrow(QueryException::class, 'customer_ledgers_sale_required');

    expect(fn () => DB::table('customer_ledgers')->where('id', $entry->id)->update(['source' => 'payment']))
        ->toThrow(QueryException::class, 'customer_ledgers_payment_required');
});

// ---------- Invoice numbers and sequences ----------

it('prevents duplicate invoice numbers within a shop but allows them across shops', function () {
    Sale::factory()->for($this->shopA)->create(['invoice_number' => 7]);

    $this->tenant->run($this->shopB, fn () => Sale::factory()->for($this->shopB)->create(['invoice_number' => 7]));

    expect(fn () => Sale::factory()->for($this->shopA)->create(['invoice_number' => 7]))
        ->toThrow(QueryException::class, 'Duplicate entry');
});

it('keeps one sequence row per key per shop', function () {
    ShopSequence::query()->create(['sequence_key' => 'sales_invoice', 'last_number' => 0]);

    $this->tenant->run($this->shopB, fn () => ShopSequence::query()
        ->create(['sequence_key' => 'sales_invoice', 'last_number' => 0]));

    expect(ShopSequence::query()->count())->toBe(1)
        ->and(fn () => ShopSequence::query()->create(['sequence_key' => 'sales_invoice', 'last_number' => 0]))
        ->toThrow(QueryException::class, 'Duplicate entry');
});

// ---------- Completed invoice protection ----------

it('locks a completed sale but allows cancelling it once', function () {
    $sale = Sale::factory()->for($this->shopA)->completed()->create();

    expect(fn () => $sale->update(['notes' => 'edit']))->toThrow(LogicException::class);

    // المحاولة الفاشلة سابت notes dirty على الـ instance، فنبدأ من نسخة نضيفة
    $sale = $sale->fresh();

    $sale->forceFill([
        'status' => SaleStatus::Cancelled,
        'cancelled_at' => now(),
        'cancellation_reason' => 'خطأ في الإدخال',
    ])->save();

    expect($sale->fresh()->status)->toBe(SaleStatus::Cancelled);

    $sale = $sale->fresh();

    expect(fn () => $sale->forceFill(['notes' => 'again'])->save())->toThrow(LogicException::class);
});

it('does not let a completed sale change its totals', function () {
    $sale = Sale::factory()->for($this->shopA)->completed()->create();

    $sale->forceFill(['subtotal' => '10.00', 'total_amount' => '10.00'])->save();
})->throws(LogicException::class);

it('only deletes draft sales', function () {
    $completed = Sale::factory()->for($this->shopA)->completed()->create();
    $draft = Sale::factory()->for($this->shopA)->create();

    expect(fn () => $completed->delete())->toThrow(LogicException::class);

    $draft->delete();

    expect(Sale::find($draft->id))->toBeNull();
});

it('blocks adding, editing or removing items once the sale is completed', function () {
    $sale = Sale::factory()->for($this->shopA)->create();
    $item = SaleItem::factory()->for($this->shopA)->create(['sale_id' => $sale->id]);

    $item->update(['quantity' => '2.000']); // مسودة: مسموح

    $sale->forceFill(['status' => SaleStatus::Completed, 'completed_at' => now()])->save();

    expect(fn () => $item->update(['quantity' => '3.000']))->toThrow(LogicException::class)
        ->and(fn () => $item->delete())->toThrow(LogicException::class)
        ->and(fn () => SaleItem::factory()->for($this->shopA)->create(['sale_id' => $sale->id]))
        ->toThrow(LogicException::class);
});

// ---------- Payments and ledger ----------

it('keeps payments immutable but allows them on a completed sale', function () {
    $sale = Sale::factory()->for($this->shopA)->completed()->create();
    $payment = Payment::factory()->for($this->shopA)->create(['sale_id' => $sale->id]);

    expect($payment->method)->toBe(PaymentMethod::Cash)
        ->and(fn () => $payment->update(['notes' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $payment->delete())->toThrow(LogicException::class);
});

it('accepts a customer payment that is not linked to an invoice', function () {
    $payment = Payment::factory()->for($this->shopA)->create([
        'sale_id' => null,
        'customer_id' => $this->customerA->id,
    ]);

    expect($payment->sale_id)->toBeNull()
        ->and($payment->customer->is($this->customerA))->toBeTrue();
});

it('keeps ledger entries immutable', function () {
    $entry = CustomerLedger::factory()->for($this->shopA)->create(['customer_id' => $this->customerA->id]);

    expect(fn () => $entry->update(['amount' => '1.00']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('allows one invoice entry and one cancellation entry per sale', function () {
    $sale = Sale::factory()->for($this->shopA)->create(['customer_id' => $this->customerA->id]);

    $make = fn (LedgerSource $source, LedgerDirection $dir) => CustomerLedger::factory()->for($this->shopA)->create([
        'customer_id' => $this->customerA->id,
        'sale_id' => $sale->id,
        'source' => $source,
        'direction' => $dir,
        'amount' => '100.00',
    ]);

    $make(LedgerSource::Invoice, LedgerDirection::Debit);
    $make(LedgerSource::Cancellation, LedgerDirection::Credit); // قيد تصحيح مقابل

    expect(fn () => $make(LedgerSource::Invoice, LedgerDirection::Debit))
        ->toThrow(QueryException::class, 'Duplicate entry')
        ->and(fn () => $make(LedgerSource::Cancellation, LedgerDirection::Credit))
        ->toThrow(QueryException::class, 'Duplicate entry')
        ->and(CustomerLedger::query()->count())->toBe(2);
});

it('allows many payment entries on one sale but only one entry per payment', function () {
    $sale = Sale::factory()->for($this->shopA)->create(['customer_id' => $this->customerA->id]);
    $p1 = Payment::factory()->for($this->shopA)->create(['sale_id' => $sale->id]);
    $p2 = Payment::factory()->for($this->shopA)->create(['sale_id' => $sale->id]);

    $entry = fn (Payment $p) => CustomerLedger::factory()->for($this->shopA)->create([
        'customer_id' => $this->customerA->id,
        'sale_id' => $sale->id,
        'payment_id' => $p->id,
        'source' => LedgerSource::Payment,
        'direction' => LedgerDirection::Credit,
        'amount' => '40.00',
    ]);

    $entry($p1);
    $entry($p2);

    expect(fn () => $entry($p1))->toThrow(QueryException::class, 'Duplicate entry')
        ->and($sale->ledgerEntries()->count())->toBe(2);
});

// ---------- Relationships and money precision ----------

it('links sale items to their sale and product with snapshots', function () {
    $product = Product::factory()->for($this->shopA)->create();
    $sale = Sale::factory()->for($this->shopA)->create(['customer_id' => $this->customerA->id]);
    $item = SaleItem::factory()->for($this->shopA)->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
    ]);

    expect($sale->items)->toHaveCount(1)
        ->and($item->product->is($product))->toBeTrue()
        ->and($item->sale->is($sale))->toBeTrue()
        ->and($sale->customer->is($this->customerA))->toBeTrue()
        ->and($item->unit_cost)->toBe('60.0000');
});

it('links payments and ledger entries to customers and sales', function () {
    $sale = Sale::factory()->for($this->shopA)->create(['customer_id' => $this->customerA->id]);
    $payment = Payment::factory()->for($this->shopA)->create(['sale_id' => $sale->id]);
    $entry = CustomerLedger::factory()->for($this->shopA)->create([
        'customer_id' => $this->customerA->id,
        'sale_id' => $sale->id,
        'payment_id' => $payment->id,
        'source' => LedgerSource::Payment,
        'direction' => LedgerDirection::Credit,
    ]);

    expect($sale->payments)->toHaveCount(1)
        ->and($payment->ledgerEntry->is($entry))->toBeTrue()
        ->and($entry->customer->is($this->customerA))->toBeTrue()
        ->and($this->customerA->ledgerEntries)->toHaveCount(1)
        ->and($entry->direction)->toBe(LedgerDirection::Credit);
});

it('stores money as exact decimal strings', function () {
    $sale = Sale::factory()->for($this->shopA)->create([
        'subtotal' => '100.10',
        'discount_amount' => '0.30',
        'total_amount' => '99.80',
    ]);

    expect($sale->fresh()->total_amount)->toBe('99.80');
});
