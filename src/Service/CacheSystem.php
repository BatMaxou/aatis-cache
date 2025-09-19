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
        ContainerInterface $container,
        private readonly CacheItemBuilder $cacheItemBuilder,
        private readonly string $defaultPool = CacheItemPool::NAME,
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
        string|int $pool = CacheSystemInterface::SYSTEM_DEFAULT_POOL,
    ): bool {
        return $this->doAction(
            $pool,
            fn (CachePoolInterface $pool) => $pool->save($this->prepareItemForPool($key, $value, $expires)),
        )[$this->getResultPoolName($pool)] ?? false;
    }

    public function defer(
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires = CacheSystemInterface::MINUTE * 10,
        string|int $pool = CacheSystemInterface::SYSTEM_DEFAULT_POOL,
    ): bool {
        return $this->doAction(
            $pool,
            fn (CachePoolInterface $pool) => $pool->saveDeferred($this->prepareItemForPool($key, $value, $expires)),
        )[$this->getResultPoolName($pool)] ?? false;
    }

    public function getItem(string $key, string|int $pool = CacheSystemInterface::SYSTEM_DEFAULT_POOL): CacheItemInterface
    {
        return $this->doAction(
            $pool,
            fn (CachePoolInterface $pool) => $pool->getItem($key),
        )[$this->getResultPoolName($pool)] ?? $this->cacheItemBuilder->build($key, null, null);
    }

    public function getItems(array $keys, string|int $pool = CacheSystemInterface::SYSTEM_DEFAULT_POOL): iterable
    {
        return $this->doAction(
            $pool,
            fn (CachePoolInterface $pool) => $pool->getItems($keys),
        )[$this->getResultPoolName($pool)] ?? [];
    }

    public function deleteItem(string $key, string|int $pool = CacheSystemInterface::SYSTEM_DEFAULT_POOL): bool
    {
        return $this->doAction(
            $pool,
            fn (CachePoolInterface $pool) => $pool->deleteItem($key),
        )[$this->getResultPoolName($pool)] ?? false;
    }

    public function deleteItems(array $keys, string|int $pool = CacheSystemInterface::SYSTEM_DEFAULT_POOL): bool
    {
        return $this->doAction(
            $pool,
            fn (CachePoolInterface $pool) => $pool->deleteItems($keys),
        )[$this->getResultPoolName($pool)] ?? false;
    }

    public function hasItem(string $key, array|int $pools = CacheSystemInterface::ALL_POOLS): array
    {
        return $this->doAction($pools, fn (CachePoolInterface $pool) => $pool->hasItem($key));
    }

    public function commit(int|string|array $pools = CacheSystemInterface::ALL_POOLS): array
    {
        return $this->doAction($pools, fn (CachePoolInterface $pool) => $pool->commit());
    }

    public function clear(array|string|int $pools = CacheSystemInterface::ALL_POOLS): array
    {
        return $this->doAction($pools, fn (CachePoolInterface $pool) => $pool->clear());
    }

    public function sweep(array|string|int $pools = CacheSystemInterface::ALL_POOLS): array
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
     * @param callable(CachePoolInterface): T $callback
     *
     * @return array<string, T>
     */
    private function doAction(array|string|int $targets, callable $callback): array
    {
        if (is_int($targets)) {
            return match ($targets) {
                CacheSystemInterface::ALL_POOLS => $this->explorePools($callback, ['all' => true]),
                CacheSystemInterface::SYSTEM_DEFAULT_POOL => $this->doAction($this->defaultPool, $callback),
                default => [],
            };
        }

        return $this->explorePools($callback, ['pools' => is_array($targets) ? $targets : [$targets]]);
    }

    private function getResultPoolName(string|int $pool): string
    {
        return is_string($pool) ? $pool : $this->defaultPool;
    }

    /**
     * @template T
     *
     * @param callable(CachePoolInterface): T $callback
     * @param Context $context
     *
     * @return array<string, T>
     */
    private function explorePools(callable $callback, array $context = []): array
    {
        $results = [];
        foreach ($this->provide($context) as $pool) {
            $results[$pool::getName()] = $callback($pool);
        }

        return $results;
    }

    private function prepareItemForPool(
        string $key,
        mixed $value,
        int|\DateTimeInterface $expires,
    ): CacheItemInterface {
        return $this->cacheItemBuilder->build($key, $value, $expires, true);
    }
}
