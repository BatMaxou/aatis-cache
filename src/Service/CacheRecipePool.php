<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;
use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\Cache\Interface\ImmutableRecipeInterface;
use Aatis\Cache\Interface\RecipeInterface;
use Aatis\Cache\Trait\ImmutableRecipeBuilderTrait;
use Aatis\Cache\Trait\PoolTrait;
use Psr\Cache\CacheItemInterface;

class CacheRecipePool implements CachePoolInterface
{
    use ImmutableRecipeBuilderTrait;
    use PoolTrait;

    public const NAME = 'recipe';

    public static function supports(CacheItemInterface $item): bool
    {
        return $item->get() instanceof RecipeInterface;
    }

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
        $identifier = $this->getIdentifierFromKey($item->getKey());
        $ingredients = $this->buildRecipeIngredients($identifier, $recipe);
        $imports = $ingredients['imports'];
        $dependencies = $this->buildDependenciesFromImports($imports);

        if (!empty($imports)) {
            $imports = ['', ...array_values($imports), ''];
        }

        return \sprintf(
            self::RECIPE_TEMPLATE,
            \implode("\n", $imports),
            $identifier,
            ImmutableRecipeInterface::class,
            $recipe->getClass(),
            $ingredients['ingredients'],
            $this->buildConstructor($dependencies),
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
     *  identifier: string,
     *  expiration: int,
     *  value: ImmutableRecipeInterface<object>
     * } : array{
     *  identifier: string,
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
            'identifier' => $className,
            'expiration' => $recipe->getExpiration(),
        ];

        if ($withValue) {
            $result['value'] = $recipe;
        }

        return $result;
    }

    private function buildClassName(string $key): string
    {
        return \sprintf('_%sRecipe', preg_replace('/[^a-zA-Z0-9_\x80-\xff]/u', '_', $key));
    }

    private function getIdentifierFromKey(string $key): string
    {
        return $this->buildClassName($key);
    }

    private function buildRecipeIngredient(string $parentIdentifier, string $key, RecipeInterface $ingredient, int $expiration): string
    {
        $recipeKey = \sprintf('%s %s %s', $parentIdentifier, $key, $ingredient->getClass());
        $this->save($this->cacheItemBuilder->build($recipeKey, $ingredient, $expiration, true));

        return \sprintf("require_once('%s');", $this->getPathFromKey($recipeKey));
    }
}
