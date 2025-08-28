<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\Cache\Interface\CacheSystemInterface;
use Aatis\DependencyInjection\Component\Service;
use Aatis\DependencyInjection\Enum\ServiceTagOption;
use Aatis\DependencyInjection\Interface\ServiceSubscriberInterface;
use Aatis\DependencyInjection\Trait\ServiceSubscriberTrait;
use Aatis\Tag\Interface\TagBuilderInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Container\ContainerInterface;

/**
 * @template CachePoolService of Service<CachePoolInterface>
 *
 * @phpstan-type Context array{
 *  pools?: string[],
 *  all?: bool,
 * }
 */
class CacheSystem implements CacheSystemInterface, ServiceSubscriberInterface
{
    /**
     * @use ServiceSubscriberTrait<CachePoolService, CachePoolInterface, Context>
     */
    use ServiceSubscriberTrait {
        __construct as initServiceSubscriber;
    }

    public function __construct(
        private readonly CacheItemBuilder $cacheItemBuilder,
        ContainerInterface $container,
    ) {
        $this->initServiceSubscriber($container);
    }

    public static function getSubscribedServices(TagBuilderInterface $tagBuilder): iterable
    {
        yield $tagBuilder->buildFromInterface(CachePoolInterface::class, [ServiceTagOption::SERVICE_TARGETED]);
        yield $tagBuilder->buildFromName(CacheItemPool::class, [ServiceTagOption::SERVICE_TARGETED, ServiceTagOption::FROM_CLASS]);
        yield $tagBuilder->buildFromName(CacheRecipePool::class, [ServiceTagOption::SERVICE_TARGETED, ServiceTagOption::FROM_CLASS]);
    }

    public function set(
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires = CacheSystemInterface::MINUTE * 10,
        string $pool = CacheItemPool::NAME,
    ): bool {
        return $this->doAction(
            [$pool],
            fn (CachePoolInterface $pool) => $this->savePoolItem($pool, $key, $value, $expires)
        )[$pool] ?? false;
    }

    public function defer(
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires = CacheSystemInterface::MINUTE * 10,
        string $pool = CacheItemPool::NAME,
    ): bool {
        return $this->doAction(
            [$pool],
            fn (CachePoolInterface $pool) => $this->savePoolItem($pool, $key, $value, $expires, true)
        )[$pool] ?? false;
    }

    public function getItem(string $key, string $pool = CacheItemPool::NAME): CacheItemInterface
    {
        return $this->doAction(
            [$pool],
            fn (CachePoolInterface $pool) => $pool->getItem($key),
        )[$pool] ?? $this->cacheItemBuilder->build($key, null, null);
    }

    public function getItems(array $keys, string $pool = CacheItemPool::NAME): iterable
    {
        return $this->doAction(
            [$pool],
            fn (CachePoolInterface $pool) => $pool->getItems($keys),
        )[$pool] ?? [];
    }

    public function deleteItem(string $key, string $pool = CacheItemPool::NAME): bool
    {
        return $this->doAction(
            [$pool],
            fn (CachePoolInterface $pool) => $pool->deleteItem($key),
        )[$pool] ?? false;
    }

    public function deleteItems(array $keys, string $pool = CacheItemPool::NAME): bool
    {
        return $this->doAction(
            [$pool],
            fn (CachePoolInterface $pool) => $pool->deleteItems($keys),
        )[$pool] ?? false;
    }

    public function hasItem(string $key, array|int $pools = [CacheItemPool::NAME]): array
    {
        return $this->doAction(
            $pools,
            fn (CachePoolInterface $pool) => $pool->hasItem($key),
        );
    }

    public function commit(int|array $pools = [CacheItemPool::NAME]): array
    {
        return $this->doAction($pools, fn (CachePoolInterface $pool) => $pool->commit());
    }

    public function clear(array|int $pools = [CacheItemPool::NAME]): array
    {
        return $this->doAction($pools, fn (CachePoolInterface $pool) => $pool->clear());
    }

    public function sweep(array|int $pools = [CacheItemPool::NAME]): array
    {
        return $this->doAction($pools, fn (CachePoolInterface $pool) => $pool->sweep());
    }

    /**
     * @param Service<CachePoolInterface> $service
     * @param Context $ctx
     */
    protected function pick(mixed $service, array $ctx): bool
    {
        return in_array($service->getClass()::getName(), $ctx['pools'] ?? [])
            || (isset($ctx['all']) && true === $ctx['all']);
    }

    /**
     * @param Service<CachePoolInterface> $service
     * @param Context $ctx
     *
     * @return CachePoolInterface
     */
    protected function transformOut(mixed $service, array $ctx): mixed
    {
        /** @var CachePoolInterface $pool */
        $pool = $service->getInstance() ?? $this->serviceStack->get($service->getClass());

        return $pool;
    }

    /**
     * @template T
     *
     * @param string[]|int $pools
     * @param callable(CachePoolInterface): T $callback
     *
     * @return array<string, T>
     */
    private function doAction(array|int $pools, callable $callback): array
    {
        if (is_int($pools)) {
            if (CacheSystemInterface::ALL_POOLS !== $pools) {
                return [];
            }

            return $this->explorePools($callback);
        }

        $pools = $this->provide(['pools' => $pools]);
        $results = [];
        foreach ($pools as $pool) {
            $results[$pool::getName()] = $callback($pool);
        }

        return $results;
    }

    /**
     * @return array<string, bool>
     */
    private function explorePools(callable $callback): array
    {
        $results = [];
        foreach ($this->provide(['all' => true]) as $pool) {
            $results[$pool::getName()] = $callback($pool);
        }

        return $results;
    }

    private function savePoolItem(
        CachePoolInterface $pool,
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires,
        bool $defered = false,
    ): bool {
        $item = $this->cacheItemBuilder->build($key, $value, $expires, true);

        return $defered ? $pool->saveDeferred($item) : $pool->save($item);
    }
}
