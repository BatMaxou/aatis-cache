<?php

namespace Aatis\Cache\Interface;

/**
 * @template Dish of object
 */
interface ImmutableRecipeInterface
{
    /** @return class-string<Dish> */
    public function getClass(): string;

    public function getExpiration(): int;

    /** @return Dish */
    public function make(): object;
}
