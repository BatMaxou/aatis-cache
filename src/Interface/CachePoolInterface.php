<?php

namespace Aatis\Cache\Interface;

use Psr\Cache\CacheItemPoolInterface;

interface CachePoolInterface extends CacheItemPoolInterface
{
    public static function getName(): string;

    public function sweep(): bool;
}
