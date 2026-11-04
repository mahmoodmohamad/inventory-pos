<?php

// tests/Feature/Storefront/StorefrontCartTest.php

use App\Models\Product;
use App\Models\Shop;

function cartShop(array $overrides = []): Shop
{
    return Shop::factory()->create([
        'phone' => '01012345678',
        'settings' => ['storefront_enabled' => true],
        ...$overrides,
    ]);
}

function cartUrl(Shop $shop, string $path = 'cart'): string
{
    return url("/s/{$shop->slug}/{$path}");
}

it('adds an in-stock product and shows it in the cart', function () {
    $shop = cartShop();
    $p = Product::factory()->for($shop)->withStock(5)->create(['name' => 'Alpha Pads', 'retail_price' => 200]);

    $this->post(cartUrl($shop), ['product_id' => $p->id, 'qty' => 2])->assertSessionHas('status');

    $this->get(cartUrl($shop))->assertOk()->assertSee('Alpha Pads')->assertSee('400');
});

it('refuses out-of-stock and inactive products', function () {
    $shop = cartShop();
    $out = Product::factory()->for($shop)->create(['name' => 'No Stock']);
    $off = Product::factory()->for($shop)->withStock(5)->create(['name' => 'Off', 'is_active' => false]);

    $this->post(cartUrl($shop), ['product_id' => $out->id]);
    $this->post(cartUrl($shop), ['product_id' => $off->id]);

    $this->get(cartUrl($shop))->assertDontSee('No Stock')->assertDontSee('Off');
});

it('cannot add a product of another shop', function () {
    $a = cartShop();
    $b = cartShop();
    $foreign = Product::factory()->for($b)->withStock(5)->create(['name' => 'Bravo Filter']);

    $this->post(cartUrl($a), ['product_id' => $foreign->id]);

    $this->get(cartUrl($a))->assertDontSee('Bravo Filter');
});

it('keeps a separate cart per shop', function () {
    $a = cartShop();
    $b = cartShop();
    $pa = Product::factory()->for($a)->withStock(5)->create(['name' => 'Alpha Pads']);

    $this->post(cartUrl($a), ['product_id' => $pa->id]);

    $this->get(cartUrl($b))->assertDontSee('Alpha Pads');
});

it('updates the quantity and removes a line when qty is zero', function () {
    $shop = cartShop();
    $p = Product::factory()->for($shop)->withStock(5)->create(['name' => 'Alpha Pads']);

    $this->post(cartUrl($shop), ['product_id' => $p->id]);
    $this->patch(cartUrl($shop, "cart/{$p->id}"), ['qty' => 0]);

    $this->get(cartUrl($shop))->assertDontSee('Alpha Pads')->assertSee('السلة فاضية');
});

it('builds a whatsapp link without leaking cost or stock', function () {
    $shop = cartShop();
    $p = Product::factory()->for($shop)->withStock(4321, 987.65)->create([
        'name' => 'Secret Part', 'retail_price' => 250, 'wholesale_price' => 111.11,
    ]);

    $this->post(cartUrl($shop), ['product_id' => $p->id, 'qty' => 2]);

    $this->get(cartUrl($shop))
        ->assertOk()
        ->assertSee('https://wa.me/201012345678', false)
        ->assertDontSee('987.65')
        ->assertDontSee('111.11')
        ->assertDontSee('4321');
});

it('rejects an invalid quantity', function () {
    $shop = cartShop();
    $p = Product::factory()->for($shop)->withStock(5)->create();

    $this->post(cartUrl($shop), ['product_id' => $p->id, 'qty' => 1000])->assertSessionHasErrors('qty');
});
