<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;
use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\Cache\Trait\PoolTrait;
use Aatis\FileManager\Exception\DirectoryNotFoundException;
use Aatis\FileManager\Interface\FileManagerInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;

class CacheItemPool implements CachePoolInterface
{
    use PoolTrait;

    public const NAME = 'app';

    private const FILE_TEMPLATE = "%s\n%d\n%s";

    public static function getName(): string
    {
        return static::NAME;
    }

    private function getFilesExtension(): string
    {
        return '';
    }

    private function buildContent(CacheItem $item): string
    {
        return \sprintf(
            self::FILE_TEMPLATE,
            $item->getKey(),
            $item->getExpiration() ?? -1,
            serialize($item->get()),
        );
    }

    private function getParsedContent(string $path, bool $withValue = true): array
    {
        $content = $this->fileManager->read($path);
        if ('' === $content) {
            return [
                'key' => '',
                'expiration' => -1,
                ...($withValue ? ['value' => null] : []),
            ];
        }

        $parsedContent = \explode("\n", $content, $withValue ? 3 : 2);

        return [
            'key' => $parsedContent[0],
            'expiration' => (int) $parsedContent[1],
            ...($withValue ? ['value' => unserialize($parsedContent[2])] : [])
        ];
    }
}
