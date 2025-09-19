<?php

namespace Aatis\Cache\Interface;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

interface CachePoolInterface extends CacheItemPoolInterface
{
    public static function getName(): string;

    public function sweep(): bool;

    public static function supports(CacheItemInterface $item): bool;
}
