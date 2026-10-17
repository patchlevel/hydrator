<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;

use function array_pop;
use function explode;
use function implode;
use function ltrim;

/**
 * Generates the mapper of a class with specialised hydrate/extract code. It only returns the code, writing it is
 * left to the {@see GeneratedMapperWarmer}.
 *
 * @internal use the {@see GeneratedMapperWarmer}
 */
final class MapperGenerator
{
    /**
     * @param ClassMetadata<T> $metadata
     *
     * @throws ClassNotGeneratable
     *
     * @template T of object
     */
    public function generate(ClassMetadata $metadata, string $fqcn): GeneratedCode
    {
        if (!ClassPlanner::generatable($metadata)) {
            throw new ClassNotGeneratable($metadata->className, 'the class is internal, abstract, anonymous, an enum or an interface');
        }

        if ($metadata->normalizer !== null) {
            throw new ClassNotGeneratable($metadata->className, 'the class has a class normalizer');
        }

        $plan = (new ClassPlanner())->plan($metadata);

        $parts = explode('\\', ltrim($fqcn, '\\'));
        $className = array_pop($parts);
        $namespace = implode('\\', $parts);

        $nested = [];

        foreach ($plan->nested() as $class) {
            $nested[] = $class->class;
        }

        return new GeneratedCode(
            (new CodeEmitter($plan))->emit($namespace, $className),
            $nested,
        );
    }
}
