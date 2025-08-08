<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Interface\CachePoolInterface;

class CacheItemPool implements CachePoolInterface
{
    public const string NAME = 'default';

    public function __construct(
        private readonly string $_document_root,
        private readonly string $_cache_dir = 'var/cache/',
    ) {}

    public static function getName(): string
    {
        return self::NAME;
    }
}
