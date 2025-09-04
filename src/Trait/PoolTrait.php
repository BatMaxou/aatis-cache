<?php

namespace Aatis\Cache\Trait;

use Aatis\Cache\Component\CacheItem;
use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\Cache\Service\CacheItemBuilder;
use Aatis\FileManager\Exception\DirectoryNotFoundException;
use Aatis\FileManager\Interface\FileManagerInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;

trait PoolTrait
{
    /** @var array<string, CacheItem> */
    private array $loadedKeys = [];

    /** @var array<string, CacheItem> */
    private array $deferedItems = [];

    public function __construct(
        protected readonly CacheItemBuilder $cacheItemBuilder,
        protected readonly FileManagerInterface $fileManager,
        protected readonly string $_document_root,
        protected readonly string $_cache_dir = '../var/cache',
        protected readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function getItem(string $key): CacheItemInterface
    {
        try {
            $path = $this->getPathFromKey($key);
            $item = null;
            $expiration = -1;

            if (isset($this->loadedKeys[$key])) {
                $item = $this->loadedKeys[$key];
                $expiration = $item->getExpiration();
            } else {
                if (!$this->fileManager->exists($path)) {
                    return $this->cacheItemBuilder->build($key, null, null);
                }

                $parsedContent = $this->getParsedContent($path);
                if ($parsedContent['key'] !== $key) {
                    return $this->cacheItemBuilder->build($key, null, null);
                }

                $expiration = $parsedContent['expiration'];
            }

            if (-1 !== $expiration && $expiration < time()) {
                $this->fileManager->deleteFile($path);

                return $this->cacheItemBuilder->build($key, null, null);
            }

            if (isset($this->loadedKeys[$key])) {
                unset($this->loadedKeys[$key]);
            }

            return $item ?? $this->cacheItemBuilder->build($key, $parsedContent['value'], (new \DateTimeImmutable())->setTimestamp($expiration), true);
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Failed to retrieve cache item {key}: {error}', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->cacheItemBuilder->build($key, null, null);
        }
    }

    /**
     * @param string[] $keys
     *
     * @return iterable<CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        $item = $this->getItem($key);
        if ($item->isHit() && $item instanceof CacheItem) {
            $this->loadedKeys[$key] = $item;

            return true;
        }

        return false;
    }

    public function deleteItem(string $key): bool
    {
        try {
            if (isset($this->loadedKeys[$key])) {
                unset($this->loadedKeys[$key]);
            }

            $path = $this->getPathFromKey($key);
            $this->fileManager->deleteFile($path);
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Failed to delete cache item {key}: {error}', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }

        return true;
    }

    /**
     * @param string[] $keys
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->deleteItem($key)) {
                return false;
            }
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        try {
            if (!$item instanceof CacheItem) {
                throw new \InvalidArgumentException(\sprintf('Cache item must be an instance of %s', CacheItem::class));
            }

            $path = $this->getPathFromKey($item->getKey());
            if (!$this->fileManager->exists($path)) {
                $this->fileManager->createFile($path, recursive: true);
            }

            $this->fileManager->write($path, $this->buildContent($item));
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Failed to save cache item {key}: {error}', [
                    'key' => $item->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        try {
            if (!$item instanceof CacheItem) {
                throw new \InvalidArgumentException(\sprintf('Cache item must be an instance of %s', CacheItem::class));
            }

            $this->deferedItems[$item->getKey()] = $item;
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Failed to save deferred cache item {key}: {error}', [
                    'key' => $item->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }

        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferedItems as $item) {
            if (!$this->save($item)) {
                return false;
            }

            unset($this->deferedItems[$item->getKey()]);
        }

        return true;
    }

    public function clear(): bool
    {
        try {
            $this->fileManager->deleteDirectory($this->getDirectoryPath(), true);
            $this->loadedKeys = [];
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Failed to clear cache: {error}', [
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }

        return true;
    }

    public function sweep(): bool
    {
        try {
            $files = $this->fileManager->getFolder($this->getDirectoryPath());
            foreach ($files as $file) {
                $path = $this->getPathFromFileName($file);
                $parsedContent = $this->getParsedContent($path, false);

                $expiration = $parsedContent['expiration'];
                if (-1 !== $expiration && $expiration < time()) {
                    $this->fileManager->deleteFile($path);
                }
            }
        } catch (DirectoryNotFoundException $e) {
            return true;
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('Failed to sweep cache: {error}', [
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }

        return true;
    }

    private function getDirectoryPath(): string
    {
        return \sprintf(
            '%s/%s/%s',
            $this->_document_root,
            $this->_cache_dir,
            static::getName(),
        );
    }

    private function getPathFromFileName(string $target): string
    {
        return \sprintf(
            '%s/%s%s',
            $this->getDirectoryPath(),
            $target,
            $this->getFilesExtension(),
        );
    }

    private function getPathFromKey(string $key): string
    {
        return $this->getPathFromFileName(hash('sha256', $key));
    }

    /**
     * @return $withValue ? array{
     *  key: string,
     *  expiration: int,
     *  value: mixed
     * } : array{
     *  key: string,
     *  expiration: int
     * }
     */
    abstract private function getParsedContent(string $path, bool $withValue = true): array;

    abstract private function getFilesExtension(): string;

    abstract private function buildContent(CacheItem $item): string;

    abstract public static function getName(): string;
}
