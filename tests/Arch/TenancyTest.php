<?php

use App\Models\Concerns\BelongsToShop;
use App\Models\Shop;
use App\Models\User;

arch('every tenant model uses BelongsToShop')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(BelongsToShop::class)
    ->ignoring([Shop::class, User::class]);
