<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;

class CacheItemBuilder
{
    public function build(string $key, mixed $value, int|\DateTimeInterface|\DateInterval|null $expires, bool $hit = false): CacheItem
    {
        $item = new CacheItem($key, $value, $hit);

        return $expires instanceof \DateTimeInterface
            ? $item->expiresAt($expires)
            : $item->expiresAfter($expires);
    }
}
