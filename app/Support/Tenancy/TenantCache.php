<?php

namespace App\Support\Tenancy;

use Closure;
use Illuminate\Support\Facades\Cache;

final class TenantCache
{
    public function __construct(private TenantContext $tenant) {}

    public function key(string $key): string
    {
        return 'shop:'.$this->tenant->idOrFail().':'.$key;
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return Cache::remember($this->key($key), $seconds, $callback);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->key($key));
    }
}
