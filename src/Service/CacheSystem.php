<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\DependencyInjection\Component\Service;
use Aatis\DependencyInjection\Enum\ServiceTagOption;
use Aatis\DependencyInjection\Interface\ServiceSubscriberInterface;
use Aatis\DependencyInjection\Trait\ServiceSubscriberTrait;
use Aatis\Tag\Interface\TagBuilderInterface;
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
    }

    public function set(
        string $key,
        mixed $value,
        int $expires = CacheSystemInterface::MINUTE * 10,
        bool $defered = false,
        string $pool = CacheItemPool::NAME,
    ): bool {
        $pools = $this->provide(['pools' => [$pool]]);
        if (empty($pools)) {
            return false;
        }

        $item = $this->cacheItemBuilder->build($key, $value, $expires);
        $pool = $pools[0];

        return $defered ? $pool->saveDeferred($item) : $pool->save($item);
    }

    public function commit(string $pool = CacheItemPool::NAME): bool
    {
        $pools = $this->provide(['pools' => [$pool]]);
        if (empty($pools)) {
            return false;
        }

        return $pools[0]->commit();
    }

    public function get(string $key, string $pool = CacheItemPool::NAME): mixed
    {
        $pools = $this->provide(['pools' => [$pool]]);
        if (empty($pools)) {
            return null;
        }

        $item = $pools[0]->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        return null;
    }

    /**
     * @param string[] $pools
     */
    public function clear(array|int $pools = [CacheItemPool::NAME]): bool
    {
        return $this->doAction($pools, $this->clearPool(...));
    }

    /**
     * @param string[] $pools
     */
    public function sweep(array|int $pools = [CacheItemPool::NAME]): bool
    {
        return $this->doAction($pools, $this->sweepPool(...));
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
     * @param string[] $pools
     */
    private function doAction(array|int $pools, callable $callback): bool
    {
        if (is_int($pools)) {
            if (CacheSystemInterface::ALL_POOLS !== $pools) {
                return false;
            }

            $this->explorePools($callback);

            return true;
        }

        $pools = $this->provide(['pools' => $pools]);
        foreach ($pools as $pool) {
            if (!$callback($pool)) {
                return false;
            }
        }

        return true;
    }

    private function explorePools(callable $callback): bool
    {
        foreach ($this->provide(['all' => true]) as $pool) {
            if ($callback($pool)) {
                return false;
            }
        }

        return true;
    }

    private function clearPool(CachePoolInterface $pool): bool
    {
        return $pool->clear();
    }

    private function sweepPool(CachePoolInterface $pool): bool
    {
        return $pool->sweep();
    }
}
