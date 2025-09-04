<?php

namespace Aatis\Cache\Service;

use Aatis\Cache\Component\CacheItem;
use Aatis\Cache\Component\Recipe;
use Aatis\Cache\Interface\CachePoolInterface;
use Aatis\Cache\Interface\RecipeInterface;
use Aatis\Cache\Trait\PoolTrait;

class CacheRecipePool implements CachePoolInterface
{
    use PoolTrait;

    public const NAME = 'recipe';

    private const RECIPE_TEMPLATE = <<<'EOF'
    <?php

    class %s extends %s
    {
        private string $class;
        private array $ingredients;
        private array $steps = [%s];

        public function __construct(
            string $class = '%s',
            array $ingredients = [],
            ?callable $init = null,
        ) {
            $this->class = $class;
            $this->ingredients = $ingredients;
        }

        public function getExpiration(): int
        {
            return %s;
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

        return \sprintf(
            self::RECIPE_TEMPLATE,
            $this->buildClassName($item->getKey()),
            Recipe::class,
            \implode(', ', array_keys($steps)),
            $recipe->getClass(),
            (string) $item->getExpiration(),
            \implode("\n\n", array_values($steps)),
        );
    }

    private function getParsedContent(string $path, bool $withValue = true): array
    {
        require_once $path;

        dd((new \_Aatis_Tester_Common_Service_WriterRecipe())->getClass());

        $className = basename($path, $this->getFilesExtension());
        dd($className);
        $recipe = new $className();
        if (!$recipe instanceof RecipeInterface) {
            throw new \InvalidArgumentException(\sprintf('Class %s must implement %s', $className, RecipeInterface::class));
        }

        return [
            'key' => $recipe->getClass(),
            'expiration' => $recipe->getExpiration(),
            ...($withValue ? ['value' => $recipe] : [])
        ];
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

    private function getPathFromKey(string $key): string
    {
        return $this->getPathFromFileName($this->buildClassName($key));
    }
}
