<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\MetadataFactory;

use function array_shift;
use function ltrim;

/**
 * Generates the transformers ahead of time, for example when the application is deployed. Use the metadata factory
 * of the builder, so the code matches the metadata of the hydrator:
 *
 *     (new TransformerCompiler($builder->metadataFactory(), $cachePath))->compile([ProfileCreated::class]);
 */
final class TransformerCompiler
{
    private readonly TransformerFiles $files;

    public function __construct(
        private readonly MetadataFactory $metadataFactory,
        string $cachePath,
    ) {
        $this->files = new TransformerFiles($cachePath);
    }

    /**
     * Generates a transformer for every class and every nested class it references.
     *
     * @param list<class-string> $classes
     *
     * @return list<class-string> all classes a transformer was generated for
     *
     * @throws ClassNotGeneratable
     * @throws GeneratedTransformerNotWritable
     */
    public function compile(array $classes): array
    {
        $generator = new TransformerGenerator($this->metadataFactory);
        $queue = $classes;
        $done = [];
        $generated = [];

        while ($queue !== []) {
            /** @var class-string $class */
            $class = ltrim(array_shift($queue), '\\');

            if (isset($done[$class])) {
                continue;
            }

            $done[$class] = true;

            try {
                $metadata = $this->metadataFactory->metadata($class);
            } catch (ClassNotFound) {
                throw new ClassNotGeneratable($class, 'class not found');
            }

            $className = $this->files->className($metadata, $this->metadataFactory);
            $code = $generator->generate($class, $this->files->fqcn($className));

            if ($code === null) {
                continue;
            }

            $this->files->write($className, $code->code);
            $generated[] = $class;

            // nested classes are also hydrated on their own if they can not be inlined
            foreach ($code->classes as $nested) {
                $queue[] = $nested;
            }
        }

        return $generated;
    }
}
