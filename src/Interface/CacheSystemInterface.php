<?php

namespace Aatis\Cache\Interface;

use Psr\Cache\CacheItemInterface;

interface CacheSystemInterface
{
    public const ALL_POOLS = 1;
    public const SYSTEM_DEFAULT_POOL = 2;

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
        string|int $pool = self::SYSTEM_DEFAULT_POOL
    ): bool;

    public function defer(
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires = self::MINUTE * 10,
        string|int $pool = self::SYSTEM_DEFAULT_POOL
    ): bool;

    public function getItem(string $key, string|int $pool = self::SYSTEM_DEFAULT_POOL): CacheItemInterface;

    /**
     * @param string[] $keys
     *
     * @return iterable<CacheItemInterface>
     */
    public function getItems(array $keys, string|int $pool = self::SYSTEM_DEFAULT_POOL): iterable;

    public function deleteItem(string $key, string|int $pool = self::SYSTEM_DEFAULT_POOL): bool;

    /**
     * @param string[] $keys
     */
    public function deleteItems(array $keys, string|int $pool = self::SYSTEM_DEFAULT_POOL): bool;

    /**
     * @param string[]|int $pools
     *
     * @return array<string, bool>
     */
    public function hasItem(string $key, array|int $pools = self::ALL_POOLS): array;

    /**
     * @param string[]|string|int $pools
     *
     * @return array<string, bool>
     */
    public function commit(array|string|int $pools = self::ALL_POOLS): array;

    /**
     * @param string[]|string|int $pools
     *
     * @return array<string, bool>
     */
    public function clear(array|string|int $pools = self::ALL_POOLS): array;

    /**
     * @param string[]|string|int $pools
     *
     * @return array<string, bool>
     */
    public function sweep(array|string|int $pools = self::ALL_POOLS): array;
}
