# Aatis Cache

Caching library for PHP which provides caching capabilities using the PSR-6 and PSR-16 standards.

## Installation

```bash
composer require aatis/cache
```

## Dependencies

- [`aatis/file-manager`](https://github.com/BatMaxou/aatis-file-manager)
- [`aatis/dependency-injection`](https://github.com/BatMaxou/aatis-dependency-injection)
- [`aatis/tag`](https://github.com/BatMaxou/aatis-tag)

## Usage

### CacheItem

Representation of a cached item.

It value can be set with `set()` and retrieve it with `get()`.

#### Hit

A cache item can be `hit` or not, which determine if the item was found in the cache or not.

> [!NOTE]
> An item considered as not `hit` will always return `null` when calling `get()`.

#### Expiration

Expiration can be set with `expiresAt()` or `expiresAfter()` methods.

> [!NOTE]
> By default the expiration is set to 10 minutes

### CacheItemBuilder

To simplify the creation of cache items, you can use the `CacheItemBuilder` service.

```php
$cacheItem1 = $cacheItemBuilder->build('cache_key_1', 'cached_value_1'); // 10 minutes
$cacheItem2 = $cacheItemBuilder->build('cache_key_2', 'cached_value_2', 3600); // 1 hour
$cacheItem3 = $cacheItemBuilder->build('cache_key_3', 'cached_value_3', new \DateTime('+1 day')); // 1 day
$cacheItem4 = $cacheItemBuilder->build('cache_key_4', 'cached_value_4', null); // permanent
```

> [!NOTE]
> By default, `hit` is set to `false`.

### CacheSystem

#### CacheSystemInterface

This interface have builtin duration constants like `SECOND`, `DAY`, `BISECTILE_YEAR`, which can be used to set expiration of cache items.

#### CacheSystem service

CacheSystem is the main service of this bundle, it contains all the `CachePoolInterface` and provides the following methods:

- `set` -> create or override a cache item
- `defer` -> plan the creation or the override of a cache item
- `getItem` -> retrieve an item by it key
- `getItems` -> retrieve items by their keys
- `deleteItem` -> delete an item by it key
- `deleteItems` -> delete items by their keys
- `hasItem` -> return if an item with the key provided exists
- `commit` -> flush all items set with `defer`
- `clear` -> delete all items
- `sweep` -> delete all expired items

For each of these methods it is possible to request a specific pool by it name or use `CacheSystemInterface::All_POOL` to request all the pools registred.

```php
$cacheSystem->getItem('cache_key', Pool::NAME);
```

> [!NOTE]
> By default `CacheItemPool` will be used.

### CachePool

A pool is a space where cache items are stored. Each pool have a name define as a constant and it own logic to store items.

#### Default Pool

`CacheItemPool` service is set to be the default pool, it allows to store serializable data into text files.

Each data must can be pass to [serialize()](https://www.php.net/manual/en/function.serialize.php) PHP method.

#### Create a custom pool

Each pool must implements `CachePoolInterface` to be used with `CacheSystem` service.

```php
use Aatis\Cache\Interface\CachePoolInterface;

class CustomCachePool implements CachePoolInterface
{
    // ...
}
```
