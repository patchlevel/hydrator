<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\MetadataFactory;

use function array_map;
use function array_pop;
use function explode;
use function implode;
use function ltrim;

/**
 * Generates a transformer with specialised hydrate/extract code for one class. Nested classes are inlined into the
 * same transformer where possible.
 *
 * The generator itself is stateless: every call plans the class with a fresh {@see ClassPlanner} and writes the code
 * with a fresh {@see CodeEmitter}.
 *
 * @internal use the {@see GeneratedTransformerExtension} and the {@see TransformerCompiler}
 */
final class TransformerGenerator
{
    /** Part of the file name, bump it whenever the generated code changes. */
    public const VERSION = 11;

    public function __construct(
        private readonly MetadataFactory $metadataFactory,
    ) {
    }

    /**
     * @param class-string $class
     *
     * @return GeneratedCode|null null if the class has a class normalizer and is never transformed
     *
     * @throws ClassNotGeneratable
     */
    public function generate(string $class, string $transformerFqcn): GeneratedCode|null
    {
        $planner = new ClassPlanner($this->metadataFactory);
        $planner->add($class);

        $plans = $planner->plans();

        if ($plans === []) {
            return null;
        }

        $parts = explode('\\', ltrim($transformerFqcn, '\\'));
        $className = array_pop($parts);
        $namespace = implode('\\', $parts);

        return new GeneratedCode(
            (new CodeEmitter($plans))->emit($namespace, $className),
            array_map(static fn (ClassPlan $plan): string => $plan->className(), $plans),
        );
    }
}
