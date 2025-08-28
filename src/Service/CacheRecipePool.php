<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;
use Aatis\Cache\Interface\RecipeInterface;

class CacheRecipePool extends CacheItemPool
{
    public const NAME = 'recipe';
    public const FILE_TEMPLATE = "%s\n%d\n%s";

    /** @var array<string, CacheItem> */
    private array $loadedKeys = [];

    /** @var array<string, CacheItem> */
    private array $deferedItems = [];

    private const RECIPE_TEMPLATE = <<<'EOF'
    <?php

    class %s extends Aatis\Cache\Component\Recipe
    {
        /** @var string[] */
        private array $steps = [%s];

        public function __construct(
            private string $class = '%s',
            private array $ingredients = [],
            ?callable $init = null,
        ) {
        }

        public function make(): object
        {
            $dish = $this->init($this->ingredients);
            foreach ($this->steps as $step) {
                $this->{$step}($dish, $this->ingredients);
            }

            return $dish;
        }

    %s
    }
    EOF;

    private const RECIPE_STEP_TEMPLATE = <<<'EOF'
        private function %s(%s): object
        {
            return %s
        }
    EOF;

    protected function getFilesExtension(): string
    {
        return '.php';
    }

    protected function buildContent(CacheItem $item): string
    {
        $recipe = $item->get();
        if (!$recipe instanceof RecipeInterface) {
            throw new \InvalidArgumentException(\sprintf('%s pool only accepts %s instances', static::class, RecipeInterface::class));
        }

        $steps = $this->buildSteps($recipe);

        return \sprintf(
            self::RECIPE_TEMPLATE,
            $this->buildClassName($item->getKey()),
            \implode(', ', array_keys($steps)),
            $recipe->getClass(),
            \implode("\n\n", array_values($steps)),
        );
    }

    /** @return array<string, string> */
    private function buildSteps(RecipeInterface $recipe): array
    {
        $counter = 0;
        $steps = [];
        foreach ($recipe->getSteps() as $parameters => $content) {
            $name = 0 === $counter ? 'init' : \sprintf('step_%d', $counter);
            ++$counter;

            $steps[\sprintf("'%s'", $name)] = \sprintf(
                self::RECIPE_STEP_TEMPLATE,
                $name,
                $parameters,
                $content,
            );
        }

        return $steps;
    }

    private function buildClassName(string $key): string
    {
        return \sprintf('_%sRecipe', preg_replace('/[^a-zA-Z0-9_\x80-\xff]/u', '_', $key));
    }
}
