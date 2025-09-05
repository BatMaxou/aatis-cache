<?php

namespace Aatis\Cache\Component;

use Aatis\Cache\Exception\RecipeException;
use Aatis\Cache\Interface\RecipeInterface;
use Aatis\ClosureContent;

/**
 * @template Dish of object
 * @template Ingredients of array
 *
 * @implements RecipeInterface<Dish, Ingredients>
 */
class Recipe implements RecipeInterface
{
    /** @var array<callable> */
    private array $steps;

    public function __construct(
        private string $class,
        private array $ingredients = [],
        ?callable $init = null,
        private ?int $expiration = null,
    ) {
        $this->steps[] = $init ?? fn ($class, $ingredients) => new $class();
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function addStep(callable $step): static
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * @return iterable<string, string>
     */
    public function getSteps(): iterable
    {
        foreach ($this->steps as $step) {
            $content = ClosureContent::of($step);

            yield $content->getParameters() => (string) $content;
        }
    }

    public function getExpiration(): int
    {
        return $this->expiration ?? -1;
    }

    public function getIngredients(): array
    {
        return $this->ingredients;
    }

    public function make(): object
    {
        $dish = null;
        foreach ($this->steps as $index => $step) {
            if (is_string($step) && method_exists($this, $step)) {
                $step = $this->{$step}(...);
            }

            if (!is_callable($step)) {
                throw new RecipeException(\sprintf('Step %s is not callable', $step));
            }

            if (0 === $index) {
                $dish = $step($this->class, $this->ingredients);
                continue;
            }

            $step($dish, $this->ingredients);
        }

        return $dish;
    }
}
