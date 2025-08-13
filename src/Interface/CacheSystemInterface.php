<?php

namespace Aatis\Cache\Interface;

use Aatis\Cache\Service\CacheItemPool;
use Psr\Cache\CacheItemInterface;

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

    public function set(
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires = self::MINUTE * 10,
        string $pool = CacheItemPool::NAME,
    ): bool;

    public function defer(
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires = self::MINUTE * 10,
        string $pool = CacheItemPool::NAME,
    ): bool;

    public function getItem(string $key, string $pool = CacheItemPool::NAME): CacheItemInterface;

    /**
     * @param string[] $keys
     *
     * @return iterable<CacheItemInterface>
     */
    public function getItems(array $keys, string $pool = CacheItemPool::NAME): iterable;

    public function deleteItem(string $key, string $pool = CacheItemPool::NAME): bool;

    /**
     * @param string[] $keys
     */
    public function deleteItems(array $keys, string $pool = CacheItemPool::NAME): bool;

    /**
     * @param string[]|int $pools
     *
     * @return array<string, bool>
     */
    public function hasItem(string $key, array|int $pools = [CacheItemPool::NAME]): array;

    /**
     * @param string[]|int $pools
     *
     * @return array<string, bool>
     */
    public function commit(array|int $pools = [CacheItemPool::NAME]): array;

    /**
     * @param string[]|int $pools
     *
     * @return array<string, bool>
     */
    public function clear(array|int $pools = [CacheItemPool::NAME]): array;

    /**
     * @param string[]|int $pools
     *
     * @return array<string, bool>
     */
    public function sweep(array|int $pools = [CacheItemPool::NAME]): array;
}
