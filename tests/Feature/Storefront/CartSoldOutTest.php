<?php

use App\Models\Product;
use App\Models\Shop;

it('drops a cart line once the product sells out, without exposing stock', function () {
    $shop = Shop::factory()->create(['settings' => ['storefront_enabled' => true]]);
    $p = Product::factory()->for($shop)->withStock(5)->create(['name' => 'Alpha Pads']);

    $this->post(url("/s/{$shop->slug}/cart"), ['product_id' => $p->id]);
    $this->get(url("/s/{$shop->slug}/cart"))->assertSee('Alpha Pads');

    $p->forceFill(['stock_qty' => 0])->save();

    $this->get(url("/s/{$shop->slug}/cart"))->assertDontSee('Alpha Pads')->assertSee('السلة فاضية');
});
