<?php

namespace Aatis\Cache\Component;

use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface
{
    private ?\DateTimeInterface $expiration;

    public function __construct(
        private string $key,
        private mixed $value = null,
        private bool $hit = false,
    ) {
        $this->expiration = new \DateTimeImmutable('+10 minutes');
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->isHit() ? $this->value : null;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->expiration = $expiration;

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        $this->expiration = match (true) {
            is_int($time) => new \DateTimeImmutable(\sprintf('+%d seconds', $time)),
            $time instanceof \DateInterval => (new \DateTimeImmutable())->add($time),
            default => null,
        };

        return $this;
    }

    public function getExpiration(): ?int
    {
        return $this->expiration ? $this->expiration->getTimestamp() : null;
    }
}
