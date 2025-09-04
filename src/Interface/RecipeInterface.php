<?php

namespace Aatis\Cache\Interface;

/**
 * @template Dish of object
 * @template Ingredients of array
 */
interface RecipeInterface
{
    /**
     * @param class-string<Dish> $class
     * @param Ingredients $ingredients
     * @param callable(): Dish|null $init
     */
    public function __construct(string $class, array $ingredients = [], ?callable $init = null);

    public function getClass(): string;

    /** @param callable(Dish, Ingredients): Dish $step */
    public function addStep(callable $step): static;

    /**
     * @return Dish
     */
    public function make(): object;

    /**
     * @return iterable<string, string>
     */
    public function getSteps(): iterable;

    public function getExpiration(): int;
}
