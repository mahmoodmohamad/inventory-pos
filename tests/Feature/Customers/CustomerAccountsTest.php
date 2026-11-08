<?php

use App\Enums\LedgerDirection;
use App\Enums\LedgerSource;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->otherShop = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->set($this->shop);
    $this->cashier = User::factory()->for($this->shop)->create(['role' => UserRole::Cashier]);
});

it('requires authentication and customer-account permission', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));

    $this->actingAs($this->cashier)
        ->get(route('customers.index'))
        ->assertOk();
});

it('lists only customers from the signed-in user shop', function () {
    Customer::factory()->for($this->shop)->create(['name' => 'Local Customer']);
    $foreign = $this->tenant->run($this->otherShop, fn () => Customer::factory()
        ->for($this->otherShop)
        ->create(['name' => 'Foreign Customer']));

    $this->actingAs($this->cashier)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Local Customer')
        ->assertDontSee('Foreign Customer');
});

it('shows the customer ledger totals and account activity', function () {
    $customer = Customer::factory()->for($this->shop)->create(['name' => 'Account Customer']);
    $payment = Payment::factory()->for($this->shop)->create([
        'sale_id' => null,
        'customer_id' => $customer->id,
        'received_by' => $this->cashier->id,
        'amount' => '50.00',
    ]);

    CustomerLedger::factory()->for($this->shop)->for($customer)->create([
        'created_by' => $this->cashier->id,
        'source' => LedgerSource::Adjustment,
        'direction' => LedgerDirection::Debit,
        'amount' => '200.00',
        'notes' => 'Invoice balance',
    ]);

    CustomerLedger::factory()->for($this->shop)->for($customer)->create([
        'created_by' => $this->cashier->id,
        'source' => LedgerSource::Payment,
        'direction' => LedgerDirection::Credit,
        'payment_id' => $payment->id,
        'amount' => '50.00',
        'notes' => 'Customer payment',
    ]);

    $this->actingAs($this->cashier)
        ->get(route('customers.index', ['customer' => $customer->id]))
        ->assertOk()
        ->assertSee('Account Customer')
        ->assertSee('200.00')
        ->assertSee('50.00')
        ->assertSee('150.00')
        ->assertSee('Invoice balance')
        ->assertSee('Customer payment');
});

it('does not reveal another shop customer account by query string', function () {
    $foreign = $this->tenant->run($this->otherShop, fn () => Customer::factory()
        ->for($this->otherShop)
        ->create());

    $this->actingAs($this->cashier)
        ->get(route('customers.index', ['customer' => $foreign->id]))
        ->assertNotFound();
});
