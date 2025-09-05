<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;
use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\Cache\Interface\ImmutableRecipeInterface;
use Aatis\Cache\Interface\RecipeInterface;
use Aatis\Cache\Trait\PoolTrait;

class CacheRecipePool implements CachePoolInterface
{
    use PoolTrait;

    public const NAME = 'recipe';

    private const RECIPE_TEMPLATE = <<<'EOF'
    <?php
    %s
    class %s implements %s
    {
        private string $class = '%s';
        private array $ingredients = [%s];

        public function getExpiration(): int
        {
            return %s;
        }

        public function make(): object
        {
            $dish = $this->init($this->class, $this->ingredients);
    %s

            return $dish;
        }

        public function getClass(): string
        {
            return $this->class;
        }

    %s
    }
    EOF;

    private const RECIPE_INIT_STEP_TEMPLATE = <<<'EOF'
        private function init(%s): %s
        {
            return %s
        }
    EOF;

    private const RECIPE_STEP_TEMPLATE = <<<'EOF'
        private function %s(%s): void
        {
            %s
        }
    EOF;

    private const RECIPE_STEP_CALL_TEMPLATE = <<<'EOF'
            $this->%s($dish, $this->ingredients);
    EOF;

    public static function getName(): string
    {
        return static::NAME;
    }

    private function getFilesExtension(): string
    {
        return '.php';
    }

    private function buildContent(CacheItem $item): string
    {
        $recipe = $item->get();
        if (!$recipe instanceof RecipeInterface) {
            throw new \InvalidArgumentException(\sprintf('%s pool only accepts %s instances', static::class, RecipeInterface::class));
        }

        $steps = $this->buildSteps($recipe);
        $ingredients = $this->buildIngredients($recipe);

        return \sprintf(
            self::RECIPE_TEMPLATE,
            $ingredients['imports'],
            $this->buildClassName($item->getKey()),
            ImmutableRecipeInterface::class,
            $recipe->getClass(),
            $ingredients['ingredients'],
            (string) $item->getExpiration(),
            $this->buildStepsCalls(array_keys($steps)),
            \implode("\n\n", array_values($steps)),
        );
    }

    /**
     * @template T of bool
     *
     * @param T $withValue
     *
     * @return (T is true ? array{
     *  key: string,
     *  expiration: int,
     *  value: ImmutableRecipeInterface<object>
     * } : array{
     *  key: string,
     *  expiration: int
     * })
     */
    private function getParsedContent(string $path, bool $withValue = true): array
    {
        require_once $path;

        $className = basename($path, $this->getFilesExtension());
        $recipe = new $className();

        if (!$recipe instanceof ImmutableRecipeInterface) {
            throw new \InvalidArgumentException(\sprintf('Class %s must implement %s', $className, ImmutableRecipeInterface::class));
        }

        $result = [
            'key' => $className,
            'expiration' => $recipe->getExpiration(),
        ];

        if ($withValue) {
            $result['value'] = $recipe;
        }

        return $result;
    }

    /**
     * @param RecipeInterface<object, mixed[]> $recipe
     *
     * @return array<string, string>
     */
    private function buildSteps(RecipeInterface $recipe): array
    {
        $counter = 0;
        $steps = [];
        foreach ($recipe->getSteps() as $parameters => $content) {
            if (0 === $counter) {
                $steps['init'] = \sprintf(
                    self::RECIPE_INIT_STEP_TEMPLATE,
                    $parameters,
                    $recipe->getClass(),
                    $content,
                );
            } else {
                $name = \sprintf('step_%d', $counter);
                $steps[$name] = \sprintf(
                    self::RECIPE_STEP_TEMPLATE,
                    \sprintf('step_%d', $counter),
                    $parameters,
                    $content,
                );
            }

            ++$counter;
        }

        return $steps;
    }

    /**
     * @param string[] $steps
     */
    private function buildStepsCalls(array $steps): string
    {
        $calls = [];
        foreach ($steps as $step) {
            if ('init' === $step) {
                continue;
            }

            $calls[] = \sprintf(self::RECIPE_STEP_CALL_TEMPLATE, $step);
        }

        return \implode("\n", $calls);
    }

    private function buildClassName(string $key): string
    {
        return \sprintf('_%sRecipe', preg_replace('/[^a-zA-Z0-9_\x80-\xff]/u', '_', $key));
    }

    private function getIdentifierFromKey(string $key): string
    {
        return $this->buildClassName($key);
    }

    /**
     * @param RecipeInterface<object, mixed[]> $recipe
     *
     * @return array{imports: string, ingredients: string}
     */
    private function buildIngredients(RecipeInterface $recipe): array
    {
        $imports = [];
        $ingredients = [];
        foreach ($recipe->getIngredients() as $key => $ingredient) {
            if (is_string($ingredient)) {
                $ingredients[] = \sprintf("'%s' => '%s'", $key, $ingredient);
                continue;
            }

            if (null === $ingredient) {
                $ingredients[] = null;
                continue;
            }

            if (is_bool($ingredient)) {
                $ingredients[] = $ingredient ? 'true' : 'false';
                continue;
            }
        }

        // TODO:
        // if (!empty($imports)) {
        //     $imports = ['', ...$imports, ''];
        // }

        return [
            'imports' => \implode("\n", $imports),
            'ingredients' => \implode(",\n", $ingredients),
        ];
    }

    /**
     * @return array{imports: string, ingredients: string}
     */
    private function buildObjectIngredient(object $ingredient): array
    {
        return [
            'imports' => '',
            'ingredients' => '',
        ];
    }

    /**
     * @param mixed[] $ingredient
     *
     * @return array{imports: string, ingredients: string}
     */
    private function buildArrayIngredient(array $ingredient): array
    {
        return [
            'imports' => '',
            'ingredients' => '',
        ];
    }
}
