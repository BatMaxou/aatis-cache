<?php

namespace Aatis\Cache\Trait;

use Aatis\Cache\Component\Recipe;
use Aatis\Cache\Interface\RecipeInterface;

trait ImmutableRecipeBuilderTrait
{
    private const RECIPE_TEMPLATE = <<<'EOF'
    <?php
    %s
    class %s implements %s
    {
        private string $class = '%s';
        private array $ingredients = [%s];
    %s
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

    private const RECIPE_CONSTRUCTOR_TEMPLATE = <<<'EOF'
        public function __construct()
        {
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
    
    private function removeTypesFromParameters(string $parameters): string
    {
        return preg_replace('/[a-zA-Z0-9_\\\\]+\s+\$/', '$', $parameters) ?? $parameters;
    }

    /**
     * @param array<string, string>
     *
     * @return string[]
     */ 
    private function buildDependenciesFromImports(array $imports): array
    {
        $dependencies = [];
        foreach ($imports as $key => $import) {
            $exploded = \explode('/', $import);
            $dependencyKey = \sprintf('\\%s', \str_replace(['\'', ')', ';', '.php'], '', array_pop($exploded)));
            $dependencies[] = \sprintf('        $this->ingredients%s = new %s()->make();', $key, $dependencyKey);
        } 

        return $dependencies;
    }

    /**
     * @param string[] $recipe
     */
    private function buildConstructor(array $dependencies): string
    {
        if (empty($dependencies)) {
            return '';
        }

        $constructor = \sprintf(
            self::RECIPE_CONSTRUCTOR_TEMPLATE,
            \implode("\n", $dependencies),
        );

        return \sprintf("\n%s\n", $constructor);
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
                    $this->removeTypesFromParameters($parameters),
                    $recipe->getClass(),
                    \str_replace("\n", "\n        ", $content),
                );
            } else {
                $name = \sprintf('step_%d', $counter);
                $steps[$name] = \sprintf(
                    self::RECIPE_STEP_TEMPLATE,
                    \sprintf('step_%d', $counter),
                    $this->removeTypesFromParameters($parameters),
                    \str_replace("\n", "\n        ", $content),
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

    /**
     * @param RecipeInterface<object, mixed[]>
     *
     * @return array{imports: array<string, string>, ingredients: string}
     */
    private function buildRecipeIngredients(string $parentIdentifier, RecipeInterface $recipe): array
    {
        return $this->buildIngredients($parentIdentifier, $recipe->getIngredients(), $recipe->getExpiration());
    }

    /**
     * @param RecipeInterface<object, mixed[]>
     *
     * @return array{imports: array<string, string>, ingredients: string}
     */
    private function buildIngredients(string $parentIdentifier, array $ingredients, int $expiration, string $parentKey = ''): array
    {
        $imports = [];
        $builedIngredients = [];
        foreach ($ingredients as $key => $ingredient) {
            $build = $this->buildIngredient($parentIdentifier, $key, $ingredient, $expiration, $parentKey);
            if (null !== $build['ingredient']) {
                $builedIngredients[] = $build['ingredient'];
            }

            if (empty($build['imports'])) {
                continue;
            }

            $imports = [...$imports, ...$build['imports']];
        }

        return [
            'imports' => $imports,
            'ingredients' => \implode(', ', $builedIngredients),
        ];
    }

    /**
     * @return array{imports: array<string, string>, ingredient: string|null}
     */
    private function buildIngredient(string $parentIdentifier, string $key, mixed $ingredient, int $expiration, string $parentKey = ''): array
    {
        $imports = [];
        $buildedIngredient = null;

        if ($ingredient instanceof \DateTimeInterface) {
            $ingredient = new Recipe(
                $ingredient::class,
                ['value' => $ingredient->getTimestamp()],
                fn($class, $ingredients) => new $class()->setTimestamp($ingredients['value'])
            );
        }

        if ($ingredient instanceof RecipeInterface) {
            $ingredientKey = empty($parentKey) ? $key : \sprintf('%s.%s', $parentKey, $key);

            $nestedParts = \explode('.', $ingredientKey);
            $ingredientPath = '';
            foreach ($nestedParts as $part) {
                $ingredientPath .= \sprintf("['%s']", $part);
            }

            $imports[$ingredientPath] = $this->buildRecipeIngredient($parentIdentifier, $key, $ingredient, $expiration);
        } else if (is_array($ingredient)) {
            $arrayKey = empty($parentKey) ? $key : \sprintf('%s.%s', $parentKey, $key);
            $arrayIngredient = $this->buildArrayIngredient($parentIdentifier, $arrayKey, $ingredient, $expiration);
            $buildedIngredient = \sprintf("'%s' => [%s]", $key, $arrayIngredient['ingredients']);

            if (!empty($arrayIngredient['imports'])) {
                $imports = $arrayIngredient['imports'];
            }
        } else {
            $buildedIngredient = match (true) {
                null === $ingredient => \sprintf("'%s' => null", $key),
                is_string($ingredient) => \sprintf("'%s' => '%s'", $key, $ingredient),
                is_bool($ingredient) => \sprintf("'%s' => %s", $key, $ingredient ? 'true' : 'false'),
                is_int($ingredient) => \sprintf("'%s' => %d", $key, $ingredient),
                is_float($ingredient) => \sprintf("'%s' => %F", $key, $ingredient),
                default => throw new \InvalidArgumentException(\sprintf('Unsupported ingredient type %s for key %s', get_debug_type($ingredient), $key)),
            };
        }

        return [
            'imports' => $imports,
            'ingredient' => $buildedIngredient,
        ];
    }

    /**
     * @return array{imports: string[], ingredients: string}
     */
    private function buildArrayIngredient(string $parentIdentifier, string $key, array $ingredients, int $expiration): array
    {
        return $this->buildIngredients($parentIdentifier, $ingredients, $expiration, $key);
    }

    /**
     * @return string Returns the require_once statement 
     */
    abstract private function buildRecipeIngredient(string $parentIdentifier, string $key, mixed $ingredient, int $expiration): string;
}
