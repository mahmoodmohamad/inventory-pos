<?php

use App\Models\Concerns\BelongsToShop;

arch('every tenant model uses BelongsToShop')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(BelongsToShop::class)
    ->ignoring([
        'App\Models\Shop',
        'App\Models\User',
        'App\Models\VehicleMake',
        'App\Models\VehicleModel',
        'App\Models\VehicleVariant',
        'App\Models\Concerns',
        'App\Models\Scopes',
    ]);
