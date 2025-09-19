<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;
use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\Cache\Trait\PoolTrait;
use Psr\Cache\CacheItemInterface;

class CacheItemPool implements CachePoolInterface
{
    use PoolTrait;

    public const NAME = 'app';

    private const FILE_TEMPLATE = "%s\n%d\n%s";

    public static function supports(CacheItemInterface $item): bool
    {
        $value = $item->get();

        if (is_array($value)) {
            try {
                serialize($value);

                return true;
            } catch (\Exception) {
                return false;
            }
        }

        return is_scalar($value)
            || is_null($value)
            || is_object($value) && (
                $value instanceof \Serializable
                || method_exists($value, '__serialize')
                || method_exists($value, '__sleep')
            );
    }

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
            $result = [
                'identifier' => '',
                'expiration' => -1,
            ];

            if ($withValue) {
                $result['value'] = null;
            }

            return $result;
        }

        $parsedContent = \explode("\n", $content, $withValue ? 3 : 2);
        $result = [
            'identifier' => $this->getIdentifierFromKey($parsedContent[0]),
            'expiration' => (int) $parsedContent[1],
        ];

        if ($withValue) {
            $result['value'] = unserialize($parsedContent[2]);
        }

        return $result;
    }
}
