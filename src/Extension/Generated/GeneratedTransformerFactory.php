<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\StackHydrator;
use Patchlevel\Hydrator\Transformer\ClassTransformer;
use Patchlevel\Hydrator\Transformer\ClassTransformerFactory;

use function assert;
use function class_exists;
use function is_file;

/**
 * Loads the generated transformer of a class, which the {@see GeneratedTransformerWarmer} wrote beforehand. If there
 * is none, for example because the class changed since the warmup, null is returned and the class is transformed
 * with reflection. Code is never generated here.
 *
 * @internal registered by the {@see GeneratedTransformerExtension}
 */
final class GeneratedTransformerFactory implements ClassTransformerFactory
{
    public function __construct(
        private readonly TransformerFiles $files,
    ) {
    }

    public function create(ClassMetadata $metadata, StackHydrator $hydrator): ClassTransformer|null
    {
        if (!TransformerFiles::generatable($metadata)) {
            return null;
        }

        $className = $this->files->className($metadata, $hydrator);
        $fqcn = $this->files->fqcn($className);

        if (!class_exists($fqcn, false)) {
            $file = $this->files->path($className);

            if (!is_file($file)) {
                return null;
            }

            require_once $file;
        }

        try {
            $transformer = new $fqcn($hydrator);
        } catch (OutdatedGeneratedTransformer) {
            return null;
        }

        assert($transformer instanceof ClassTransformer);

        return $transformer;
    }
}
