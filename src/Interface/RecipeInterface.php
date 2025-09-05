<?php

namespace Aatis\Cache\Interface;

/**
 * @template Dish of object
 * @template Ingredients of array
 *
 * @extends ImmutableRecipeInterface<Dish>
 */
interface RecipeInterface extends ImmutableRecipeInterface
{
    /**
     * @param class-string<Dish> $class
     * @param Ingredients $ingredients
     * @param callable(): Dish|null $init
     */
    public function __construct(string $class, array $ingredients = [], ?callable $init = null);

    /** @param callable(Dish, Ingredients): Dish $step */
    public function addStep(callable $step): static;

    /** @return iterable<string, string> */
    public function getSteps(): iterable;

    /** @return Ingredients */
    public function getIngredients(): array;
}
