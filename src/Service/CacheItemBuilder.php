<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;

class CacheItemBuilder
{
    public function build(string $key, mixed $value, int $expires): CacheItem
    {
        return new CacheItem();
    }
}
