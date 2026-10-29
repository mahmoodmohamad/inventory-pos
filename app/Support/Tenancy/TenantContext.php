<?php

namespace App\Support\Tenancy;

use App\Exceptions\MissingTenantException;
use App\Models\Shop;
use Closure;

final class TenantContext
{
    private ?int $shopId = null;

    public function set(Shop|int $shop): void
    {
        $this->shopId = $shop instanceof Shop ? (int) $shop->getKey() : $shop;
    }

    public function forget(): void
    {
        $this->shopId = null;
    }

    public function id(): ?int
    {
        return $this->shopId;
    }

    public function hasShop(): bool
    {
        return $this->shopId !== null;
    }

    public function idOrFail(): int
    {
        return $this->shopId ?? throw new MissingTenantException;
    }

    /** نفّذ كود جوه محل معيّن، وبعدها ارجع للسياق القديم */
    public function run(Shop|int $shop, Closure $callback): mixed
    {
        $previous = $this->shopId;
        $this->set($shop);

        try {
            return $callback();
        } finally {
            $this->shopId = $previous;
        }
    }
}
