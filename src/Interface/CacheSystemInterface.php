<?php

namespace Aatis\Cache\Service;

interface CacheSystemInterface
{
    public const ALL_POOLS = 1;

    public const SECOND = 1;
    public const MINUTE = 60;
    public const HOUR = 3600;
    public const DAY = 86400;
    public const WEEK = 604800;
    public const MONTH = 2592000;
    public const YEAR = 31536000;
    public const BISECTILE_YEAR = 31622400;

    public function set(string $key, mixed $value, int $expires, bool $defered, string $pool): bool;

    public function get(string $key, string $pool): mixed;
}
