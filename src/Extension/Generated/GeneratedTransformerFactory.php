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
 * Loads the generated transformer of a class. If there is none, which also happens after the class changed, null is
 * returned and the class is transformed with reflection, unless the code may be generated on the fly.
 *
 * @internal registered by the {@see GeneratedTransformerExtension}
 */
final class GeneratedTransformerFactory implements ClassTransformerFactory
{
    public function __construct(
        private readonly TransformerFiles $files,
        private readonly bool $autoGenerate,
    ) {
    }

    public function create(ClassMetadata $metadata, StackHydrator $hydrator): ClassTransformer|null
    {
        if (!ClassPlanner::generatable($metadata)) {
            return null;
        }

        // the metadata of the hydrator, so the fingerprints and the generated code match the metadata at runtime
        $metadataFactory = new StackMetadataFactory($hydrator);
        $className = $this->files->className($metadata, $metadataFactory);
        $fqcn = $this->files->fqcn($className);

        if (!class_exists($fqcn, false)) {
            $file = $this->files->path($className);

            if (!is_file($file)) {
                if (!$this->autoGenerate) {
                    return null;
                }

                $code = (new TransformerGenerator($metadataFactory))->generate($metadata->className, $fqcn);

                if ($code === null) {
                    return null;
                }

                $this->files->write($className, $code->code);
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
