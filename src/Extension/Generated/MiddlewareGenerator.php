<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\MetadataFactory;

use function array_pop;
use function explode;
use function implode;
use function ltrim;

/**
 * Generates a middleware which contains specialised hydrate/extract code for a fixed set of classes.
 *
 * The generator itself is stateless: every dump plans the classes with a fresh {@see ClassPlanner} and
 * writes the code with a fresh {@see CodeEmitter}.
 *
 * @internal use the {@see GeneratedMiddlewareExtension}
 */
final class MiddlewareGenerator
{
    /** Part of the cache key, bump it whenever the generated code changes. */
    public const VERSION = 6;

    public function __construct(
        private readonly MetadataFactory $metadataFactory,
    ) {
    }

    /**
     * @param list<class-string> $classes
     *
     * @throws ClassNotGeneratable
     */
    public function dump(array $classes, string $middlewareFqcn): string
    {
        $planner = new ClassPlanner($this->metadataFactory);

        foreach ($classes as $class) {
            $planner->add($class);
        }

        $parts = explode('\\', ltrim($middlewareFqcn, '\\'));
        $className = array_pop($parts);
        $namespace = implode('\\', $parts);

        return (new CodeEmitter($planner->plans()))->emit($namespace, $className);
    }
}
