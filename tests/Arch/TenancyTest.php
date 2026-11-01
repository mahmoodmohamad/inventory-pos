<?php

use App\Models\Concerns\BelongsToShop;
use App\Models\Shop;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;

arch('every tenant model uses BelongsToShop')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(BelongsToShop::class)
    ->ignoring([
        Shop::class,
        User::class,
        VehicleMake::class,
        VehicleModel::class,
        'App\Models\Concerns',
        'App\Models\Scopes',
    ]);